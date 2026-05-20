import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Color Classification AI",
  description: "Detect dominant colors using a trained MobileNetV2 model",
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
