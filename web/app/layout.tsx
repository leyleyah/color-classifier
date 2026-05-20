import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL("https://colorcheck.vercel.app"),
  title: "Color Check — Color Classification AI",
  description:
    "Upload an image to detect its dominant color. Powered by color_classifier.h5.",
  openGraph: {
    title: "Color Check",
    url: "https://colorcheck.vercel.app",
    siteName: "Color Check",
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
