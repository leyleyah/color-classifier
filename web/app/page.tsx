"use client";

import { useState } from "react";

type PredictionItem = {
  color: string;
  confidence: number;
  hex: string;
};

type Result = {
  success: boolean;
  color?: string;
  confidence?: number;
  hex?: string;
  top_predictions?: PredictionItem[];
  note?: string;
  dominant_colors?: PredictionItem[];
  error?: string;
};

export default function Home() {
  const [preview, setPreview] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<Result | null>(null);
  const [error, setError] = useState("");

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError("");
    setResult(null);

    const form = e.currentTarget;
    const input = form.elements.namedItem("image") as HTMLInputElement;
    const file = input?.files?.[0];
    if (!file) {
      setError("Please choose an image first.");
      return;
    }

    const body = new FormData();
    body.append("image", file);

    setLoading(true);
    try {
      const res = await fetch("/api/predict", { method: "POST", body });
      const data: Result = await res.json();
      if (!data.success) {
        setError(data.error || "Prediction failed.");
        return;
      }
      setResult(data);
    } catch {
      setError("Network error. Check MODEL_API_URL and that the Python API is running.");
    } finally {
      setLoading(false);
    }
  }

  function onFileChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) {
      setPreview(null);
      return;
    }
    setPreview(URL.createObjectURL(file));
  }

  return (
    <div className="container">
      <h1>Color Classification AI</h1>
      <p className="subtitle">
        Upload an image to detect its dominant color (trained on Kaggle color dataset)
      </p>
      <p className="hint hint-ok">
        Powered by <code>color_classifier.h5</code> — UI on Vercel, ML API on Render.
      </p>

      <form onSubmit={onSubmit}>
        <input
          type="file"
          name="image"
          accept="image/*"
          required
          onChange={onFileChange}
        />
        {preview && (
          <div className="preview-box">
            <img src={preview} alt="Preview" />
          </div>
        )}
        <button type="submit" disabled={loading}>
          {loading ? "Please wait…" : "Predict Color"}
        </button>
      </form>

      {loading && (
        <div className="loading-overlay">
          <div className="loading-card">
            <div className="spinner" />
            <p>
              <strong>Running prediction…</strong>
            </p>
          </div>
        </div>
      )}

      {error && (
        <div className="result-box error-box">
          <h2>Error</h2>
          <p>{error}</p>
        </div>
      )}

      {result?.success && result.color && (
        <div className="result-box">
          <h2>Prediction Result</h2>
          <div className="color-result">
            <span
              className="color-swatch"
              style={{ backgroundColor: result.hex }}
            />
            <div>
              <p className="main-prediction">
                {result.color.charAt(0).toUpperCase() + result.color.slice(1)}
                <span className="confidence">
                  {Number(result.confidence).toFixed(2)}% confidence
                </span>
              </p>
              <p className="model-note">Model: color_classifier.h5</p>
            </div>
          </div>
          {result.note && <p className="model-note multicolor-note">{result.note}</p>}
          {result.top_predictions && (
            <div className="top-list">
              <h3>Top predictions</h3>
              <ul>
                {result.top_predictions.map((item) => (
                  <li key={item.color}>
                    <span
                      className="mini-swatch"
                      style={{ backgroundColor: item.hex }}
                    />
                    {item.color.charAt(0).toUpperCase() + item.color.slice(1)} —{" "}
                    {item.confidence.toFixed(2)}%
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
