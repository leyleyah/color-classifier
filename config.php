<?php

/**
 * Path to the project virtualenv Python (Windows).
 */
function get_venv_python_path(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
}

/**
 * Optional: python_bootstrap.ini with python=C:\...\python.exe (non–Store install).
 */
function read_bootstrap_python(): ?string
{
    $ini = __DIR__ . DIRECTORY_SEPARATOR . 'python_bootstrap.ini';
    if (!is_file($ini)) {
        return null;
    }

    $parsed = @parse_ini_file($ini, false, INI_SCANNER_RAW);
    if (!is_array($parsed) || empty($parsed['python'])) {
        return null;
    }

    $path = trim((string) $parsed['python'], " \t\r\n\"'");
    if ($path === '' || stripos($path, 'WindowsApps') !== false) {
        return null;
    }

    return is_file($path) ? $path : null;
}

/**
 * Find a real Python interpreter (not the Microsoft Store stub) for venv creation.
 */
function discover_base_python(): ?string
{
    $fromIni = read_bootstrap_python();
    if ($fromIni !== null) {
        return $fromIni;
    }

    $tryExec = static function (string $prefix): ?string {
        $out = [];
        $code = 1;
        $cmd = $prefix . ' -c "import sys; print(sys.executable)" 2>nul';
        @exec($cmd, $out, $code);
        if ($code !== 0 || empty($out[0])) {
            return null;
        }
        $exe = trim($out[0]);
        if ($exe === '' || stripos($exe, 'WindowsApps') !== false) {
            return null;
        }

        return is_file($exe) ? $exe : null;
    };

    foreach (['py -3.11', 'py -3.12', 'py -3.13', 'py -3'] as $py) {
        $found = $tryExec($py);
        if ($found !== null) {
            return $found;
        }
    }

    $localApp = getenv('LOCALAPPDATA');
    if (is_string($localApp) && $localApp !== '') {
        foreach (['Python311', 'Python312', 'Python313'] as $ver) {
            $p = $localApp . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . $ver . DIRECTORY_SEPARATOR . 'python.exe';
            if (is_file($p)) {
                return $p;
            }
        }
    }

    $home = (string) getenv('HOMEDRIVE') . (string) getenv('HOMEPATH');
    if ($home !== '' && is_dir($home)) {
        foreach (['Python311', 'Python312', 'Python313'] as $ver) {
            $p = $home . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . $ver . DIRECTORY_SEPARATOR . 'python.exe';
            if (is_file($p)) {
                return $p;
            }
        }
    }

    foreach (['C:\\Program Files\\Python311\\python.exe', 'C:\\Program Files\\Python312\\python.exe'] as $p) {
        if (is_file($p)) {
            return $p;
        }
    }

    return null;
}

/**
 * Creates venv + installs requirements if missing (first request may take several minutes).
 *
 * @return array{ok: bool, message: string}
 */
function ensure_project_venv(): array
{
    $venvPy = get_venv_python_path();
    if (is_file($venvPy)) {
        return ['ok' => true, 'message' => ''];
    }

    $uploads = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploads)) {
        @mkdir($uploads, 0777, true);
    }

    $lockFile = $uploads . DIRECTORY_SEPARATOR . '.venv_bootstrap.lock';
    $lockHandle = @fopen($lockFile, 'c+');
    if ($lockHandle !== false) {
        flock($lockHandle, LOCK_EX);
    }

    try {
        if (is_file($venvPy)) {
            return ['ok' => true, 'message' => ''];
        }

        $bootstrap = discover_base_python();
        if ($bootstrap === null) {
            return [
                'ok' => false,
                'message' => 'Python environment is missing. Automatic setup could not find Python (Apache often has no PATH to py.exe). '
                    . 'Fix: (1) Copy python_bootstrap.ini.example to python_bootstrap.ini and set python= to your full python.exe path (python.org install), or (2) Run setup.bat once from File Explorer so venv is created in this folder.',
            ];
        }

        $venvDir = __DIR__ . DIRECTORY_SEPARATOR . 'venv';
        $req = __DIR__ . DIRECTORY_SEPARATOR . 'requirements.txt';

        $out1 = [];
        $code1 = 1;
        $cmdVenv = '"' . $bootstrap . '" -m venv "' . $venvDir . '" 2>&1';
        @exec($cmdVenv, $out1, $code1);

        if ($code1 !== 0 || !is_file($venvPy)) {
            $tail = implode("\n", array_slice($out1, -15));

            return [
                'ok' => false,
                'message' => 'Could not create venv. ' . ($tail !== '' ? $tail : 'Exit code ' . (int) $code1),
            ];
        }

        $out2 = [];
        $code2 = 1;
        $cmdPip = '"' . $venvPy . '" -m pip install --upgrade pip -r "' . $req . '" 2>&1';
        @exec($cmdPip, $out2, $code2);

        if ($code2 !== 0) {
            $tail = implode("\n", array_slice($out2, -20));

            return [
                'ok' => false,
                'message' => 'venv was created but pip install failed. ' . substr($tail, 0, 1200),
            ];
        }

        return ['ok' => true, 'message' => ''];
    } finally {
        if ($lockHandle !== false) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}

/**
 * Resolves Python for non-Windows or fallback (venv preferred).
 */
function get_python_executable(): string
{
    $venvPy = get_venv_python_path();
    if (is_file($venvPy)) {
        return $venvPy;
    }

    $checkScript = __DIR__ . DIRECTORY_SEPARATOR . 'check_deps.py';

    $candidates = [
        'C:\\wamp64\\www\\Rice Type Classification\\venv\\Scripts\\python.exe',
        'py -3.11',
    ];

    foreach ($candidates as $candidate) {
        if (str_contains($candidate, 'python.exe') && !is_file($candidate)) {
            continue;
        }

        $versionCmd = '"' . $candidate . '" --version';
        $versionOut = shell_exec($versionCmd);
        if (empty($versionOut) || stripos($versionOut, 'Python') === false) {
            continue;
        }

        $importCmd = '"' . $candidate . '" "' . $checkScript . '"';
        exec($importCmd, $dummy, $importCode);
        if ($importCode !== 0) {
            continue;
        }

        return $candidate;
    }

    return $venvPy;
}

/**
 * Base URL for optional model_server.py (set COLOR_MODEL_SERVER to override, e.g. http://127.0.0.1:8765).
 */
function get_model_server_base_url(): string
{
    $env = getenv('COLOR_MODEL_SERVER');
    if (is_string($env) && trim($env) !== '' && trim($env) !== '0') {
        return rtrim(trim($env), '/');
    }

    return 'http://127.0.0.1:8765';
}

/**
 * Fast path: long-lived Python process keeps the model in RAM.
 *
 * @return array{ok: bool, stdout: string, stderr: string, exit_code: int, message: string}|null
 */
function try_model_server_predict(string $imageAbsolutePath): ?array
{
    $url = get_model_server_base_url() . '/predict';
    $payload = json_encode(['image' => $imageAbsolutePath], JSON_UNESCAPED_SLASHES);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 300,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);

    if ($raw === false || $raw === '') {
        return null;
    }

    $statusLine = isset($http_response_header[0]) ? (string) $http_response_header[0] : '';
    if ($statusLine !== '' && strpos($statusLine, '200') === false) {
        return null;
    }

    $raw = trim((string) $raw);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    return [
        'ok' => true,
        'stdout' => $raw,
        'stderr' => '',
        'exit_code' => 0,
        'message' => '',
    ];
}

/**
 * Runs predict.py from the project root.
 *
 * @return array{ok: bool, stdout: string, stderr: string, exit_code: int, message: string}
 */
function run_color_prediction(string $imageAbsolutePath): array
{
    $base = __DIR__;
    $script = $base . DIRECTORY_SEPARATOR . 'predict.py';
    $launcher = $base . DIRECTORY_SEPARATOR . 'run_predict.cmd';

    if (!is_file($script)) {
        return [
            'ok' => false,
            'stdout' => '',
            'stderr' => '',
            'exit_code' => -1,
            'message' => 'predict.py is missing from the project folder.',
        ];
    }

    if (!is_file($imageAbsolutePath)) {
        return [
            'ok' => false,
            'stdout' => '',
            'stderr' => '',
            'exit_code' => -1,
            'message' => 'Uploaded image file is missing.',
        ];
    }

    $ensure = ensure_project_venv();
    if (!$ensure['ok']) {
        return [
            'ok' => false,
            'stdout' => '',
            'stderr' => '',
            'exit_code' => -1,
            'message' => $ensure['message'],
        ];
    }

    if (!is_file($launcher)) {
        return [
            'ok' => false,
            'stdout' => '',
            'stderr' => '',
            'exit_code' => -1,
            'message' => 'run_predict.cmd is missing from the project folder.',
        ];
    }

    $httpResult = try_model_server_predict($imageAbsolutePath);
    if ($httpResult !== null) {
        return $httpResult;
    }

    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = false;

    if (PHP_OS_FAMILY === 'Windows') {
        // CMD rule for paths with spaces: cmd /c ""C:\path with spaces\file.cmd" "arg""
        $bat = str_replace('/', '\\', $launcher);
        $img = str_replace('/', '\\', $imageAbsolutePath);
        $cmdline = 'cmd /c ""' . $bat . '" "' . $img . '""';
        $process = @proc_open($cmdline, $descriptorspec, $pipes, $base);
    } else {
        $python = get_python_executable();
        if (!is_file($python)) {
            return [
                'ok' => false,
                'stdout' => '',
                'stderr' => '',
                'exit_code' => -1,
                'message' => 'Python was not found. Create venv and install requirements.',
            ];
        }

        $command = [$python, $script, $imageAbsolutePath];
        $options = [];
        if (PHP_VERSION_ID >= 70400) {
            $options['bypass_shell'] = true;
        }

        $process = @proc_open($command, $descriptorspec, $pipes, $base, null, $options);
    }

    if ($process === false || !is_resource($process)) {
        return [
            'ok' => false,
            'stdout' => '',
            'stderr' => '',
            'exit_code' => -1,
            'message' => 'Could not start Python. Check php.ini: proc_open must not be in disable_functions.',
        ];
    }

    fclose($pipes[0]);

    $stdout = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $stdout = trim($stdout);
    $stderr = trim($stderr);

    // Launcher exit 2 = venv missing after ensure (race) or deleted mid-request
    if (PHP_OS_FAMILY === 'Windows' && $exitCode === 2 && $stdout === '') {
        return [
            'ok' => false,
            'stdout' => '',
            'stderr' => $stderr,
            'exit_code' => $exitCode,
            'message' => 'venv\\Scripts\\python.exe is missing. Run setup.bat or wait for automatic install to finish.',
        ];
    }

    return [
        'ok' => true,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'exit_code' => $exitCode,
        'message' => '',
    ];
}
