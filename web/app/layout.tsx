import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "ColorCheck — Color Classification AI",
  description: "ColorCheck: detect dominant colors using a trained MobileNetV2 model",
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
