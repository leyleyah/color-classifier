<?php
/**
 * JSON API for color prediction (used by the web UI via fetch).
 */
header('Content-Type: application/json; charset=utf-8');
@ini_set('max_execution_time', '600');
@ini_set('default_socket_timeout', '300');

require_once __DIR__ . '/config.php';

function api_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(405, ['success' => false, 'error' => 'POST required.']);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMax = ini_get('post_max_size');
$postMaxBytes = parse_ini_size($postMax);

if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    api_json(413, [
        'success' => false,
        'error' => 'Image is too large. Maximum upload is ' . $postMax . '. Try a smaller photo.',
    ]);
}

if (empty($_FILES['image'])) {
    $hint = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && $contentLength > 0)
        ? ' Upload may exceed server post_max_size (' . $postMax . ').'
        : '';
    api_json(400, [
        'success' => false,
        'error' => 'No image received.' . $hint,
    ]);
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in the form.',
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension.',
    ];
    $code = (int) $file['error'];
    api_json(400, [
        'success' => false,
        'error' => $messages[$code] ?? ('Upload failed (code ' . $code . ').'),
    ]);
}

$allowedTypes = [
    'image/jpeg',
    'image/jpg',
    'image/pjpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/bmp',
    'image/x-png',
];

$mimeType = detect_upload_mime($file['tmp_name']);
if ($mimeType === '' || $mimeType === 'application/octet-stream') {
    $mimeType = detect_mime_by_extension($file['name']) ?: $mimeType;
}

if (!in_array($mimeType, $allowedTypes, true)) {
    api_json(400, [
        'success' => false,
        'error' => 'Unsupported file type (' . ($mimeType ?: 'unknown') . '). Use JPG, PNG, GIF, WEBP, or BMP.',
    ]);
}

$targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
$safeExt = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? $extension : 'jpg';
$safeName = 'upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
$targetFile = $targetDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
    api_json(500, ['success' => false, 'error' => 'Failed to save uploaded image.']);
}

$run = run_color_prediction($targetFile);

if (empty($run['ok'])) {
    api_json(500, ['success' => false, 'error' => $run['message']]);
}

if ($run['stdout'] === '') {
    $hint = $run['stderr'] !== '' ? substr($run['stderr'], 0, 400) : ('Exit code ' . (int) $run['exit_code']);
    api_json(500, [
        'success' => false,
        'error' => 'Prediction produced no output. ' . $hint,
    ]);
}

$lines = preg_split('/\r\n|\r|\n/', $run['stdout']);
$jsonLine = '';
foreach (array_reverse($lines) as $line) {
    $line = trim($line);
    if ($line !== '' && $line[0] === '{') {
        $jsonLine = $line;
        break;
    }
}

$decoded = json_decode($jsonLine !== '' ? $jsonLine : $run['stdout'], true);
if (!is_array($decoded)) {
    api_json(500, [
        'success' => false,
        'error' => 'Invalid prediction response.',
        'raw' => substr($run['stdout'], 0, 500),
    ]);
}

if (empty($decoded['success'])) {
    api_json(500, [
        'success' => false,
        'error' => $decoded['error'] ?? 'Prediction failed.',
    ]);
}

$decoded['image_url'] = 'uploads/' . $safeName;
api_json(200, $decoded);

/**
 * @return int bytes, or 0 if unknown
 */
function parse_ini_size(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $num = (float) $value;
    return match ($unit) {
        'g' => (int) ($num * 1024 * 1024 * 1024),
        'm' => (int) ($num * 1024 * 1024),
        'k' => (int) ($num * 1024),
        default => (int) $num,
    };
}

function detect_upload_mime(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    $mime = @mime_content_type($path);

    return is_string($mime) ? $mime : '';
}

function detect_mime_by_extension(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        default => '',
    };
}
