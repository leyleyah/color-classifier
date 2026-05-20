"""
Local inference server: loads the Keras model once and serves POST /predict.
Keeps the browser from waiting on a full TensorFlow cold start on every upload.

Listen: 127.0.0.1:8765 (localhost only)

Start: start_model_server.bat  (or: venv\\Scripts\\python.exe model_server.py)
"""
from __future__ import annotations

import json
import os
import sys
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlparse

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOADS_DIR = os.path.realpath(os.path.join(BASE_DIR, "uploads"))

PORT = int(os.environ.get("COLOR_MODEL_PORT", "8765"))


def _under_uploads(path: str) -> bool:
    try:
        rp = os.path.realpath(path)
    except OSError:
        return False
    uploads = os.path.realpath(UPLOADS_DIR)
    if not os.path.isdir(uploads):
        return False
    common = os.path.commonpath([rp, uploads])
    return common == uploads


class PredictHandler(BaseHTTPRequestHandler):
    def log_message(self, format: str, *args) -> None:  # noqa: A003
        pass

    def do_POST(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)
        if parsed.path.rstrip("/") != "/predict":
            self.send_error(404, "Not Found")
            return

        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0 or length > 30 * 1024 * 1024:
            self._json(400, {"success": False, "error": "Invalid Content-Length"})
            return

        raw = self.rfile.read(length)
        try:
            data = json.loads(raw.decode("utf-8"))
        except json.JSONDecodeError:
            self._json(400, {"success": False, "error": "Invalid JSON"})
            return

        image_path = data.get("image")
        if not isinstance(image_path, str) or not image_path:
            self._json(400, {"success": False, "error": "Missing image path"})
            return

        if not os.path.isfile(image_path) or not _under_uploads(image_path):
            self._json(403, {"success": False, "error": "Image path not allowed"})
            return

        try:
            import predict as pred_module

            result = pred_module.predict(image_path)
            self._json(200, result)
        except Exception as exc:  # noqa: BLE001
            self._json(200, {"success": False, "error": str(exc)})

    def _json(self, status: int, payload: dict) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


def main() -> None:
    os.chdir(BASE_DIR)
    # Warm import + model load before first HTTP request
    import predict as pred_module  # noqa: F401

    pred_module.get_model()

    server = HTTPServer(("127.0.0.1", PORT), PredictHandler)
    print(f"Color model server ready at http://127.0.0.1:{PORT}/predict", flush=True)
    print("Press Ctrl+C to stop.", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
