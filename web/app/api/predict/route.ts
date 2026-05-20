import { NextRequest, NextResponse } from "next/server";

export const maxDuration = 60;

function getApiBase(): string {
  const url = process.env.MODEL_API_URL?.trim();
  if (!url) {
    throw new Error(
      "MODEL_API_URL is not set. Deploy the Python API on Render and add the URL in Vercel environment variables."
    );
  }
  return url.replace(/\/$/, "");
}

export async function POST(request: NextRequest) {
  try {
    const formData = await request.formData();
    const file = formData.get("image");

    if (!file || !(file instanceof Blob)) {
      return NextResponse.json(
        { success: false, error: "No image uploaded." },
        { status: 400 }
      );
    }

    const apiForm = new FormData();
    apiForm.append("file", file, "upload.jpg");

    const apiRes = await fetch(`${getApiBase()}/predict`, {
      method: "POST",
      body: apiForm,
    });

    const data = await apiRes.json().catch(() => null);

    if (!apiRes.ok) {
      return NextResponse.json(
        {
          success: false,
          error: data?.detail || data?.error || `API error ${apiRes.status}`,
        },
        { status: apiRes.status }
      );
    }

    return NextResponse.json(data);
  } catch (err) {
    const message = err instanceof Error ? err.message : "Prediction failed.";
    return NextResponse.json({ success: false, error: message }, { status: 500 });
  }
}
