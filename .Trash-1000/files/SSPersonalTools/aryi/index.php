<?php
declare(strict_types=1);

// ============================================================
// 1. BLINDAJE DE EJECUCIÓN Y PERMISOS DE MICRÓFONO (i7 / 32GB / 6GB VRAM)
// ============================================================
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('max_input_time', '-1');
ini_set('memory_limit', '512M');

ob_start();
session_start();

// Permitir explícitamente el uso del micrófono en el navegador
header("Permissions-Policy: microphone=(self), autoplay=(self)");

// ============================================================
// 2. CONFIGURACIÓN DEL SISTEMA Y ENDPOINTS (WINDOWS DOCKER)
// ============================================================
$PASSWORD_SECRETA     = "ss-tfh-free";
$DOCKER_IP            = '100.115.1.71';
$DEFAULT_OLLAMA_MODEL = 'qwen2.5-coder:7b';

$API_STT         = "http://{$DOCKER_IP}:8000/v1/audio/transcriptions";
$API_OLLAMA_GEN  = "http://{$DOCKER_IP}:11434/api/generate";
$API_OLLAMA_TAGS = "http://{$DOCKER_IP}:11434/api/tags";
$API_OLLAMA_PS   = "http://{$DOCKER_IP}:11434/api/ps";
$API_TTS         = "http://{$DOCKER_IP}:5050/tts";
$API_SD          = "http://{$DOCKER_IP}:7860/sdapi/v1/txt2img";

$tempDir = sys_get_temp_dir();
if (!is_writable($tempDir)) {
    $tempDir = __DIR__;
}
$TELEMETRY_FILE = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'aryi_queue_telemetry_v6.json';

// ============================================================
// 3. MOTOR DE BLINDAJE JSON Y STREAMING ANTI-524 (CLOUDFLARE)
// ============================================================
$IS_API_REQUEST   = isset($_GET['action']);
$STREAM_MODE_OPEN = false;

function sendSafeJson(array $payload, int $httpCode = 200): never {
    global $STREAM_MODE_OPEN;
    if ($STREAM_MODE_OPEN) {
        echo "\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
        exit;
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo ($json !== false) ? $json : '{"ok":false,"error":"Error interno serializando JSON."}';
    exit;
}

function openAnti524Stream(string $ticketId, string $phaseLabel): void {
    global $STREAM_MODE_OPEN;
    $STREAM_MODE_OPEN = true;

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }

    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');
    }

    echo json_encode([
        'type'   => 'init',
        'ok'     => true,
        'ticket' => $ticketId,
        'phase'  => $phaseLabel,
        'pad'    => str_repeat('.', 4096)
    ], JSON_UNESCAPED_UNICODE) . "\n";

    if (ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

function emitStreamLine(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

if ($IS_API_REQUEST) {
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(function (Throwable $e): void {
        sendSafeJson(['type' => 'error', 'ok' => false, 'error' => 'Excepción en servidor: ' . $e->getMessage()], 500);
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => 'Error fatal de PHP: ' . $err['message']], 500);
        }
    });
}

// ============================================================
// 4. MOTOR DE COLA ESTRICTA (FIFO) Y TELEMETRÍA
// ============================================================
function getDefaultTelemetryState(): array {
    return [
        'queue'   => [],
        'metrics' => [
            'stt'    => ['avg_ms' => 3200,  'last_ms' => 3200,  'count' => 1, 'label' => 'Whisper STT'],
            'ollama' => ['avg_ms' => 12000, 'last_ms' => 12000, 'count' => 1, 'label' => 'Ollama LLM'],
            'tts'    => ['avg_ms' => 4200,  'last_ms' => 4200,  'count' => 1, 'label' => 'Motor Voz TTS'],
            'sd'     => ['avg_ms' => 25000, 'last_ms' => 25000, 'count' => 1, 'label' => 'Stable Diffusion']
        ]
    ];
}

function mutateTelemetryState(string $file, callable $callback): array {
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        $dummy = getDefaultTelemetryState();
        $res   = $callback($dummy, microtime(true));
        return is_array($res) ? $res : $dummy;
    }

    flock($fp, LOCK_EX);
    $raw   = stream_get_contents($fp);
    $state = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    if (!is_array($state) || !isset($state['queue'], $state['metrics'])) {
        $state = getDefaultTelemetryState();
    }

    $now = microtime(true);
    foreach ($state['queue'] as $id => $item) {
        $lastBeat = (float)($item['updated_at'] ?? $now);
        $status   = (string)($item['status'] ?? 'queued');
        $timeout  = ($status === 'processing') ? 25.0 : 10.0;
        if (($now - $lastBeat) > $timeout) {
            unset($state['queue'][$id]);
        }
    }

    uasort($state['queue'], fn($a, $b) => ($a['created_at'] <=> $b['created_at']));

    $firstKey = array_key_first($state['queue']);
    if ($firstKey !== null && $state['queue'][$firstKey]['status'] === 'queued') {
        $state['queue'][$firstKey]['status']      = 'ready';
        $state['queue'][$firstKey]['promoted_at'] = $now;
    }

    $result = $callback($state, $now);

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string)json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return is_array($result) ? $result : $state;
}

function purgeOperatorTickets(string $file, string $operatorId): void {
    if ($operatorId === '') return;
    mutateTelemetryState($file, function (&$state, $now) use ($operatorId) {
        foreach ($state['queue'] as $id => $item) {
            if (($item['operator_id'] ?? '') === $operatorId) {
                unset($state['queue'][$id]);
            }
        }
        uasort($state['queue'], fn($a, $b) => ($a['created_at'] <=> $b['created_at']));
        $firstKey = array_key_first($state['queue']);
        if ($firstKey !== null && $state['queue'][$firstKey]['status'] === 'queued') {
            $state['queue'][$firstKey]['status']      = 'ready';
            $state['queue'][$firstKey]['promoted_at'] = $now;
        }
        return $state;
    });
}

function touchAndCheckTicket(string $file, string $ticketId): bool {
    $valid = false;
    mutateTelemetryState($file, function (&$state, $now) use ($ticketId, &$valid) {
        if (isset($state['queue'][$ticketId])) {
            $state['queue'][$ticketId]['updated_at'] = $now;
            $valid = true;
        }
        return $state;
    });
    return $valid;
}

function buildTelemetrySnapshot(string $file, string $ticketId): array {
    return mutateTelemetryState($file, function (&$state, $now) use ($ticketId) {
        if ($ticketId !== '' && isset($state['queue'][$ticketId])) {
            $state['queue'][$ticketId]['updated_at'] = $now;
        }

        uasort($state['queue'], fn($a, $b) => ($a['created_at'] <=> $b['created_at']));
        $queueList  = array_values($state['queue']);
        $totalQueue = count($queueList);
        $position   = 0;
        $etaMs      = 0.0;
        $myItem     = null;
        $firstItem  = $queueList[0] ?? null;

        foreach ($queueList as $idx => $item) {
            $taskKey = $item['task'] ?? 'ollama';
            $taskAvg = (float)($state['metrics'][$taskKey]['avg_ms'] ?? 10000);

            if ($item['status'] === 'processing' && !empty($item['started_at'])) {
                $elapsedCurrent = ($now - (float)$item['started_at']) * 1000.0;
                $remaining      = max(2000.0, $taskAvg - $elapsedCurrent);
            } else {
                $remaining = $taskAvg;
            }

            if ($item['id'] === $ticketId) {
                $position = $idx + 1;
                $myItem   = $item;
                $etaMs   += $remaining;
                break;
            } else {
                $etaMs += $remaining;
            }
        }

        $activeTaskLabel = 'GPU Libre';
        if ($firstItem) {
            $tKey            = $firstItem['task'] ?? 'ollama';
            $tName           = $state['metrics'][$tKey]['label'] ?? strtoupper($tKey);
            $activeTaskLabel = "Procesando petición #1 ({$tName})";
        }

        return [
            'ok'                => true,
            'ticket'            => $ticketId,
            'status'            => $myItem['status'] ?? 'none',
            'turn'              => $position,
            'can_execute'       => ($position === 1 && in_array($myItem['status'] ?? '', ['ready', 'processing'], true)),
            'queue_total'       => $totalQueue,
            'active_task_label' => $activeTaskLabel,
            'elapsed_ms'        => $myItem ? round(($now - (float)$myItem['created_at']) * 1000) : 0,
            'eta_ms'            => round($etaMs),
            'metrics'           => $state['metrics']
        ];
    });
}

function finishTicketAndRecordMetric(string $file, string $ticketId, string $taskType, float $durationMs): void {
    mutateTelemetryState($file, function (&$state, $now) use ($ticketId, $taskType, $durationMs) {
        unset($state['queue'][$ticketId]);

        if (isset($state['metrics'][$taskType]) && $durationMs > 80) {
            $oldAvg = (float)$state['metrics'][$taskType]['avg_ms'];
            $count  = (int)$state['metrics'][$taskType]['count'];
            $newAvg = ($count <= 1) ? $durationMs : (($oldAvg * 0.7) + ($durationMs * 0.3));
            $state['metrics'][$taskType]['avg_ms']  = round($newAvg);
            $state['metrics'][$taskType]['last_ms'] = round($durationMs);
            $state['metrics'][$taskType]['count']   = $count + 1;
        }

        uasort($state['queue'], fn($a, $b) => ($a['created_at'] <=> $b['created_at']));
        $firstKey = array_key_first($state['queue']);
        if ($firstKey !== null && $state['queue'][$firstKey]['status'] === 'queued') {
            $state['queue'][$firstKey]['status']      = 'ready';
            $state['queue'][$firstKey]['promoted_at'] = $now;
        }
        return $state;
    });
}

function lockServerTurnForExecution(string $file, string $ticketId, string $operatorId, string $taskType): void {
    $lastPing = microtime(true);
    while (true) {
        if (connection_aborted()) {
            purgeOperatorTickets($file, $operatorId);
            exit;
        }

        $allowed   = false;
        $cancelled = false;
        $turnPos   = 1;

        mutateTelemetryState($file, function (&$state, $now) use ($ticketId, $taskType, &$allowed, &$cancelled, &$turnPos) {
            if (!isset($state['queue'][$ticketId])) {
                $cancelled = true;
                return $state;
            }
            $state['queue'][$ticketId]['updated_at'] = $now;
            uasort($state['queue'], fn($a, $b) => ($a['created_at'] <=> $b['created_at']));
            $keys    = array_keys($state['queue']);
            $turnPos = (int)array_search($ticketId, $keys, true) + 1;

            if ($turnPos === 1) {
                $state['queue'][$ticketId]['status']     = 'processing';
                $state['queue'][$ticketId]['task']       = $taskType;
                $state['queue'][$ticketId]['started_at'] = $state['queue'][$ticketId]['started_at'] ?? $now;
                $allowed = true;
            }
            return $state;
        });

        if ($cancelled) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => 'Tu turno fue cancelado o la sesión se cerró.'], 409);
        }
        if ($allowed) {
            break;
        }

        if ((microtime(true) - $lastPing) >= 2.5) {
            $lastPing = microtime(true);
            emitStreamLine(['type' => 'ping', 'turn' => $turnPos, 'phase' => "Esperando liberación de turno (#{$turnPos})..."]);
        }
        usleep(300000);
    }
}

// ============================================================
// 5. DETECCIÓN AUTOMÁTICA DE MODELOS OLLAMA Y GESTIÓN DE 6GB VRAM
// ============================================================
function discoverAllOllamaModels(string $apiTagsUrl, string $fallbackDefault): array {
    $ch = curl_init($apiTagsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 6
    ]);
    $raw      = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $models = [];
    if ($raw !== false && $httpCode === 200) {
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded) && isset($decoded['models']) && is_array($decoded['models'])) {
            foreach ($decoded['models'] as $m) {
                $name      = (string)($m['name'] ?? '');
                $sizeBytes = (int)($m['size'] ?? 0);
                $sizeGbNum = $sizeBytes > 0 ? round($sizeBytes / (1024 * 1024 * 1024), 2) : 0.0;
                $sizeGbStr = $sizeGbNum > 0 ? "{$sizeGbNum} GB" : '';
                $quant     = (string)($m['details']['quantization_level'] ?? '');
                $paramSize = (string)($m['details']['parameter_size'] ?? '');
                $family    = (string)($m['details']['family'] ?? '');
                $metaParts = array_filter([$paramSize, $quant, $sizeGbStr]);
                if ($name !== '') {
                    $models[] = [
                        'name'       => $name,
                        'label'      => $name . (!empty($metaParts) ? ' (' . implode(' • ', $metaParts) . ')' : ''),
                        'size_gb'    => $sizeGbNum,
                        'param_size' => $paramSize,
                        'quant'      => $quant,
                        'family'     => $family,
                        'fits_vram'  => ($sizeGbNum > 0 && $sizeGbNum <= 5.8)
                    ];
                }
            }
        }
    }

    if (empty($models)) {
        $models[] = [
            'name'       => $fallbackDefault,
            'label'      => $fallbackDefault . ' (Sin conexión / Fallback)',
            'size_gb'    => 0,
            'param_size' => '',
            'quant'      => '',
            'family'     => '',
            'fits_vram'  => true
        ];
    }

    return [
        'online' => ($raw !== false && $httpCode === 200),
        'models' => $models
    ];
}

function getOllamaLoadedModels(string $apiPsUrl): array {
    $ch = curl_init($apiPsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => 4
    ]);
    $raw      = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $loaded = [];
    if ($raw !== false && $httpCode === 200) {
        $data = json_decode((string)$raw, true);
        if (isset($data['models']) && is_array($data['models'])) {
            foreach ($data['models'] as $m) {
                if (!empty($m['name'])) {
                    $sizeVram = (int)($m['size_vram'] ?? $m['size'] ?? 0);
                    $loaded[] = [
                        'name'    => (string)$m['name'],
                        'vram_gb' => $sizeVram > 0 ? round($sizeVram / (1024 * 1024 * 1024), 2) : 0
                    ];
                }
            }
        }
    }
    return $loaded;
}

function unloadAllOllamaModelsFromVram(string $apiPsUrl, string $apiGenUrl, string $fallbackModel = ''): array {
    $loaded = getOllamaLoadedModels($apiPsUrl);
    $names  = array_column($loaded, 'name');
    if ($fallbackModel !== '' && !in_array($fallbackModel, $names, true)) {
        $names[] = $fallbackModel;
    }

    $unloaded = [];
    foreach ($names as $modelName) {
        $ch = curl_init($apiGenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['model' => $modelName, 'keep_alive' => 0]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 10
        ]);
        curl_exec($ch);
        curl_close($ch);
        $unloaded[] = $modelName;
    }
    return $unloaded;
}

function checkDockerPort(string $ip, int $port, float $timeoutSec = 1.2): array {
    $t0 = microtime(true);
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeoutSec);
    $ms = round((microtime(true) - $t0) * 1000);
    if ($fp) {
        fclose($fp);
        return ['online' => true, 'ms' => $ms];
    }
    return ['online' => false, 'ms' => null];
}

// ============================================================
// 6. SISTEMA DE LOGIN Y EVACUACIÓN DE TURNO AL SALIR
// ============================================================
$current_url = strtok($_SERVER["REQUEST_URI"] ?? '/', '?');

if (empty($_SESSION['operator_id'])) {
    $_SESSION['operator_id'] = 'op_' . bin2hex(random_bytes(8));
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$operator_id = (string)$_SESSION['operator_id'];
$csrf_token  = (string)$_SESSION['csrf_token'];

if (isset($_GET['salir'])) {
    purgeOperatorTickets($TELEMETRY_FILE, $operator_id);
    $_SESSION = [];
    session_destroy();
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header("Location: " . $current_url);
    exit;
}

$error_msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !$IS_API_REQUEST) {
    $intentos = (int)($_SESSION['login_attempts'] ?? 0);
    $bloqueo  = (int)($_SESSION['lockout_time'] ?? 0);

    if ($intentos >= 5 && (time() - $bloqueo) < 60) {
        $restante  = 60 - (time() - $bloqueo);
        $error_msg = "Demasiados intentos. Espera {$restante} segundos.";
    } elseif (hash_equals($PASSWORD_SECRETA, (string)$_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['logeado']        = true;
        $_SESSION['login_attempts'] = 0;
        $_SESSION['operator_id']    = 'op_' . bin2hex(random_bytes(8));
        $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header("Location: " . $current_url);
        exit;
    } else {
        $_SESSION['login_attempts'] = $intentos + 1;
        $_SESSION['lockout_time']   = time();
        $error_msg = "Contraseña incorrecta.";
    }
}

$autenticado = isset($_SESSION['logeado']) && $_SESSION['logeado'] === true;

session_write_close();

// ============================================================
// 7. CONTROLADOR DE ENDPOINTS API
// ============================================================
if ($IS_API_REQUEST) {
    if (!$autenticado) {
        sendSafeJson(['ok' => false, 'error' => 'Sesión cerrada o expirada. Inicia sesión nuevamente.'], 401);
    }

    $action = (string)$_GET['action'];

    if ($action === 'telemetry') {
        $ticketId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['ticket'] ?? ''));
        $snap     = buildTelemetrySnapshot($TELEMETRY_FILE, $ticketId);
        if (isset($_GET['include_vram'])) {
            $snap['vram_models'] = getOllamaLoadedModels($API_OLLAMA_PS);
        }
        sendSafeJson($snap);
    }

    if ($action === 'health') {
        sendSafeJson([
            'ok'       => true,
            'services' => [
                'stt'    => checkDockerPort($DOCKER_IP, 8000),
                'ollama' => checkDockerPort($DOCKER_IP, 11434),
                'tts'    => checkDockerPort($DOCKER_IP, 5050),
                'sd'     => checkDockerPort($DOCKER_IP, 7860)
            ],
            'vram_models' => getOllamaLoadedModels($API_OLLAMA_PS)
        ]);
    }

    if ($action === 'models') {
        $discovered = discoverAllOllamaModels($API_OLLAMA_TAGS, $DEFAULT_OLLAMA_MODEL);
        sendSafeJson([
            'ok'            => true,
            'default_model' => $discovered['models'][0]['name'] ?? $DEFAULT_OLLAMA_MODEL,
            'models'        => $discovered['models'],
            'vram_models'   => getOllamaLoadedModels($API_OLLAMA_PS),
            'online'        => $discovered['online']
        ]);
    }

    if ($action === 'leave_queue') {
        $ticketId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['ticket'] ?? $_POST['ticket'] ?? ''));
        mutateTelemetryState($TELEMETRY_FILE, function (&$state, $now) use ($ticketId, $operator_id) {
            if ($ticketId !== '' && isset($state['queue'][$ticketId])) {
                unset($state['queue'][$ticketId]);
            } else {
                foreach ($state['queue'] as $id => $item) {
                    if (($item['operator_id'] ?? '') === $operator_id) {
                        unset($state['queue'][$id]);
                    }
                }
            }
            uasort($state['queue'], fn($a, $b) => ($a['created_at'] <=> $b['created_at']));
            $firstKey = array_key_first($state['queue']);
            if ($firstKey !== null && $state['queue'][$firstKey]['status'] === 'queued') {
                $state['queue'][$firstKey]['status']      = 'ready';
                $state['queue'][$firstKey]['promoted_at'] = $now;
            }
            return $state;
        });
        sendSafeJson(['ok' => true, 'message' => 'Turno liberado correctamente.']);
    }

    $headerCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($csrf_token, $headerCsrf)) {
        sendSafeJson(['ok' => false, 'error' => 'Token CSRF inválido. Recarga la página.'], 403);
    }

    if ($action === 'free_vram') {
        $input    = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $fallback = trim((string)($input['model'] ?? $DEFAULT_OLLAMA_MODEL));
        $unloaded = unloadAllOllamaModelsFromVram($API_OLLAMA_PS, $API_OLLAMA_GEN, $fallback);
        sendSafeJson([
            'ok'          => true,
            'unloaded'    => $unloaded,
            'vram_models' => getOllamaLoadedModels($API_OLLAMA_PS),
            'message'     => 'Memoria VRAM de Ollama liberada (keep_alive: 0).'
        ]);
    }

    if ($action === 'enqueue') {
        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $task  = (string)($input['task'] ?? 'ollama');
        if (!in_array($task, ['stt', 'ollama', 'tts', 'sd'], true)) {
            $task = 'ollama';
        }
        $ticketId = 'tk_' . bin2hex(random_bytes(6));

        mutateTelemetryState($TELEMETRY_FILE, function (&$state, $now) use ($ticketId, $operator_id, $task) {
            $isEmpty = empty($state['queue']);
            $state['queue'][$ticketId] = [
                'id'          => $ticketId,
                'operator_id' => $operator_id,
                'task'        => $task,
                'status'      => $isEmpty ? 'ready' : 'queued',
                'created_at'  => $now,
                'started_at'  => null,
                'updated_at'  => $now
            ];
            return $state;
        });

        sendSafeJson(buildTelemetrySnapshot($TELEMETRY_FILE, $ticketId));
    }

    $ticketId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_SERVER['HTTP_X_TICKET_ID'] ?? ''));
    if ($ticketId === '') {
        sendSafeJson(['ok' => false, 'error' => 'Falta identificador de turno (Ticket ID).'], 400);
    }

    $makeAnti524ProgressCallback = function (string $phaseText, float $t0, float &$lastOutputTime) use ($TELEMETRY_FILE, $ticketId): Closure {
        $lastTicketTouch = 0.0;
        return function ($resource, $dlTotal = 0, $dlNow = 0, $ulTotal = 0, $ulNow = 0) use ($TELEMETRY_FILE, $ticketId, $phaseText, $t0, &$lastOutputTime, &$lastTicketTouch): int {
            if (connection_aborted()) {
                return 1;
            }
            $now = microtime(true);

            if (($now - $lastTicketTouch) >= 1.2) {
                $lastTicketTouch = $now;
                if (!touchAndCheckTicket($TELEMETRY_FILE, $ticketId)) {
                    return 1;
                }
            }

            if (($now - $lastOutputTime) >= 3.0) {
                $lastOutputTime = $now;
                emitStreamLine([
                    'type'       => 'ping',
                    'phase'      => $phaseText,
                    'elapsed_ms' => round(($now - $t0) * 1000)
                ]);
            }
            return 0;
        };
    };

    // 7.7 EJECUCIÓN STT: WHISPER
    if ($action === 'stt') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'stt', 0);
            sendSafeJson(['ok' => false, 'error' => 'No se recibió un archivo de audio válido.'], 400);
        }

        openAnti524Stream($ticketId, 'Iniciando transcripción en Whisper STT...');
        lockServerTurnForExecution($TELEMETRY_FILE, $ticketId, $operator_id, 'stt');

        $t0             = microtime(true);
        $lastOutputTime = $t0;

        $cfile = new CURLFile(
            $_FILES['file']['tmp_name'],
            $_FILES['file']['type'] ?: 'audio/webm',
            $_FILES['file']['name'] ?: 'voice.webm'
        );

        $ch = curl_init($API_STT);
        curl_setopt_array($ch, [
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => ['file' => $cfile],
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_CONNECTTIMEOUT   => 0,
            CURLOPT_TIMEOUT          => 0,
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => $makeAnti524ProgressCallback('Transcribiendo voz en Whisper STT...', $t0, $lastOutputTime)
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $durationMs = (microtime(true) - $t0) * 1000.0;
        finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'stt', $durationMs);

        if ($response === false || $httpCode !== 200) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => "Error en Whisper STT (HTTP {$httpCode})" . ($curlErr ? ": {$curlErr}" : '')]);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => 'Whisper devolvió una respuesta inválida.']);
        }

        sendSafeJson([
            'type'        => 'result',
            'ok'          => true,
            'text'        => (string)($decoded['text'] ?? ''),
            'duration_ms' => round($durationMs)
        ]);
    }

    // 7.8 EJECUCIÓN LLM: OLLAMA (Soporta liberación automática después de ejecutar en modo Arena)
    if ($action === 'ollama') {
        $input        = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $prompt       = trim((string)($input['prompt'] ?? ''));
        $systemPrompt = trim((string)($input['system'] ?? ''));
        $model        = trim((string)($input['model'] ?? $DEFAULT_OLLAMA_MODEL));
        $useStream    = (bool)($input['stream'] ?? true);
        $unloadAfter  = (bool)($input['unload_after'] ?? false);
        $temperature  = max(0.0, min(1.5, (float)($input['temperature'] ?? 0.7)));
        $numCtx       = max(512, min(8192, (int)($input['num_ctx'] ?? 2048)));

        if ($model === '') {
            $model = $DEFAULT_OLLAMA_MODEL;
        }
        if ($prompt === '') {
            finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'ollama', 0);
            sendSafeJson(['ok' => false, 'error' => 'El prompt para Ollama está vacío.'], 400);
        }

        openAnti524Stream($ticketId, "Cargando {$model} en VRAM/RAM...");
        lockServerTurnForExecution($TELEMETRY_FILE, $ticketId, $operator_id, 'ollama');

        $t0             = microtime(true);
        $lastOutputTime = $t0;
        $lineBuffer     = '';

        $reqBody = [
            'model'      => $model,
            'prompt'     => $prompt,
            'stream'     => $useStream,
            'keep_alive' => $unloadAfter ? 0 : '15m',
            'options'    => [
                'temperature' => $temperature,
                'num_ctx'     => $numCtx
            ]
        ];
        if ($systemPrompt !== '') {
            $reqBody['system'] = $systemPrompt;
        }
        $payload = json_encode($reqBody, JSON_UNESCAPED_UNICODE);

        if ($useStream) {
            $ch = curl_init($API_OLLAMA_GEN);
            curl_setopt_array($ch, [
                CURLOPT_POST             => true,
                CURLOPT_POSTFIELDS       => $payload,
                CURLOPT_HTTPHEADER       => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER   => false,
                CURLOPT_CONNECTTIMEOUT   => 0,
                CURLOPT_TIMEOUT          => 0,
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_PROGRESSFUNCTION => $makeAnti524ProgressCallback("Cargando {$model} en 6GB VRAM / 32GB RAM...", $t0, $lastOutputTime),
                CURLOPT_WRITEFUNCTION    => function ($ch, string $chunk) use (&$lineBuffer, &$lastOutputTime): int {
                    $lineBuffer .= $chunk;
                    while (($pos = strpos($lineBuffer, "\n")) !== false) {
                        $completeLine = trim(substr($lineBuffer, 0, $pos));
                        $lineBuffer   = substr($lineBuffer, $pos + 1);
                        if ($completeLine !== '') {
                            echo $completeLine . "\n";
                            if (ob_get_level() > 0) {
                                @ob_flush();
                            }
                            flush();
                            $lastOutputTime = microtime(true);
                        }
                    }
                    return strlen($chunk);
                }
            ]);

            $ok       = curl_exec($ch);
            $curlErr  = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $rem = trim($lineBuffer);
            if ($rem !== '') {
                echo $rem . "\n";
            }

            $durationMs = (microtime(true) - $t0) * 1000.0;
            finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'ollama', $durationMs);

            if ($ok === false || ($httpCode !== 200 && $httpCode !== 0)) {
                sendSafeJson(['type' => 'error', 'ok' => false, 'error' => "Error en Ollama (HTTP {$httpCode})" . ($curlErr ? ": {$curlErr}" : '')]);
            }

            sendSafeJson(['type' => 'done', 'ok' => true, 'done' => true, 'duration_ms' => round($durationMs)]);
        }

        $ch = curl_init($API_OLLAMA_GEN);
        curl_setopt_array($ch, [
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => $payload,
            CURLOPT_HTTPHEADER       => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_CONNECTTIMEOUT   => 0,
            CURLOPT_TIMEOUT          => 0,
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => $makeAnti524ProgressCallback("Generando respuesta completa con {$model}...", $t0, $lastOutputTime)
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $durationMs = (microtime(true) - $t0) * 1000.0;
        finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'ollama', $durationMs);

        if ($response === false || $httpCode !== 200) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => "Error en Ollama (HTTP {$httpCode})" . ($curlErr ? ": {$curlErr}" : '')]);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => 'Ollama devolvió una respuesta inválida.']);
        }

        $decoded['type']        = 'result';
        $decoded['ok']          = true;
        $decoded['duration_ms'] = round($durationMs);
        sendSafeJson($decoded);
    }

    // 7.9 EJECUCIÓN TTS: TEXTO A VOZ
    if ($action === 'tts') {
        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $text  = trim((string)($input['text'] ?? ''));

        if ($text === '') {
            finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'tts', 0);
            sendSafeJson(['ok' => false, 'error' => 'No hay texto para sintetizar en voz.'], 400);
        }

        openAnti524Stream($ticketId, 'Sintetizando voz en motor TTS...');
        lockServerTurnForExecution($TELEMETRY_FILE, $ticketId, $operator_id, 'tts');

        $t0             = microtime(true);
        $lastOutputTime = $t0;

        $ch = curl_init($API_TTS);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_POST             => true,
            CURLOPT_HTTPHEADER       => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS       => json_encode(['texto' => $text], JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT   => 0,
            CURLOPT_TIMEOUT          => 0,
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => $makeAnti524ProgressCallback('Generando audio WAV en motor TTS...', $t0, $lastOutputTime)
        ]);

        $audioData = curl_exec($ch);
        $curlErr   = curl_error($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $durationMs = (microtime(true) - $t0) * 1000.0;
        finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'tts', $durationMs);

        if ($httpCode === 200 && $audioData !== false && strlen((string)$audioData) > 64) {
            sendSafeJson([
                'type'         => 'result',
                'ok'           => true,
                'audio_base64' => base64_encode((string)$audioData),
                'duration_ms'  => round($durationMs)
            ]);
        }

        sendSafeJson(['type' => 'error', 'ok' => false, 'error' => "Error HTTP {$httpCode} en motor TTS" . ($curlErr ? ": {$curlErr}" : '')]);
    }

    // 7.10 EJECUCIÓN SD: STABLE DIFFUSION
    if ($action === 'sd') {
        $input          = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $rawPrompt      = trim((string)($input['prompt'] ?? ''));
        $negativePrompt = trim((string)($input['negative_prompt'] ?? 'blurry, low quality, bad anatomy, deformed, ugly, watermark, text, signature, cropped'));
        $steps          = max(10, min(50, (int)($input['steps'] ?? 20)));
        $cfgScale       = max(1.0, min(20.0, (float)($input['cfg_scale'] ?? 7.0)));
        $width          = max(256, min(1024, (int)($input['width'] ?? 512)));
        $height         = max(256, min(1024, (int)($input['height'] ?? 512)));
        $autoUnload     = (bool)($input['free_vram'] ?? true);
        $optimizePrompt = (bool)($input['optimize_prompt'] ?? true);
        $lastModel      = trim((string)($input['model'] ?? $DEFAULT_OLLAMA_MODEL));

        if ($rawPrompt === '') {
            finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'sd', 0);
            sendSafeJson(['ok' => false, 'error' => 'El prompt para Stable Diffusion está vacío.'], 400);
        }

        openAnti524Stream($ticketId, 'Preparando pipeline de Stable Diffusion...');
        lockServerTurnForExecution($TELEMETRY_FILE, $ticketId, $operator_id, 'sd');

        $t0             = microtime(true);
        $lastOutputTime = $t0;
        $finalSdPrompt  = $rawPrompt;

        if ($optimizePrompt) {
            emitStreamLine(['type' => 'ping', 'phase' => 'Traduciendo y optimizando prompt visual al inglés con Ollama...']);
            $engineerInstruction = "Convert the following text into a vivid, high-detail English image generation prompt for Stable Diffusion. Include visual descriptors, lighting, atmosphere, camera angle, and quality tags (masterpiece, best quality, highly detailed, 8k). Output ONLY the English prompt without quotes or explanations:\n\n" . mb_substr($rawPrompt, 0, 900);

            $optPayload = json_encode([
                'model'      => $lastModel ?: $DEFAULT_OLLAMA_MODEL,
                'prompt'     => $engineerInstruction,
                'stream'     => false,
                'keep_alive' => $autoUnload ? 0 : '5m',
                'options'    => ['temperature' => 0.5, 'num_ctx' => 1024]
            ], JSON_UNESCAPED_UNICODE);

            $chOpt = curl_init($API_OLLAMA_GEN);
            curl_setopt_array($chOpt, [
                CURLOPT_POST             => true,
                CURLOPT_POSTFIELDS       => $optPayload,
                CURLOPT_HTTPHEADER       => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER   => true,
                CURLOPT_CONNECTTIMEOUT   => 0,
                CURLOPT_TIMEOUT          => 0,
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_PROGRESSFUNCTION => $makeAnti524ProgressCallback('Traduciendo prompt a inglés con Ollama...', $t0, $lastOutputTime)
            ]);
            $optRaw  = curl_exec($chOpt);
            $optCode = (int)curl_getinfo($chOpt, CURLINFO_HTTP_CODE);
            curl_close($chOpt);

            if ($optRaw !== false && $optCode === 200) {
                $optDec = json_decode((string)$optRaw, true);
                if (!empty($optDec['response'])) {
                    $cleanedPrompt = trim((string)$optDec['response'], " \t\n\r\0\x0B\"'");
                    if ($cleanedPrompt !== '') {
                        $finalSdPrompt = $cleanedPrompt;
                        emitStreamLine(['type' => 'sd_prompt', 'sd_prompt' => $finalSdPrompt]);
                    }
                }
            }
        }

        if ($autoUnload) {
            emitStreamLine(['type' => 'ping', 'phase' => 'Liberando 6GB VRAM de Ollama (keep_alive: 0) para Stable Diffusion...']);
            unloadAllOllamaModelsFromVram($API_OLLAMA_PS, $API_OLLAMA_GEN, $lastModel);
        }

        emitStreamLine(['type' => 'ping', 'phase' => "Renderizando en GPU ({$width}x{$height} • {$steps} pasos)..."]);

        $sdPayload = json_encode([
            'prompt'          => $finalSdPrompt,
            'negative_prompt' => $negativePrompt,
            'steps'           => $steps,
            'cfg_scale'       => $cfgScale,
            'width'           => $width,
            'height'          => $height
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($API_SD);
        curl_setopt_array($ch, [
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => $sdPayload,
            CURLOPT_HTTPHEADER       => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_CONNECTTIMEOUT   => 0,
            CURLOPT_TIMEOUT          => 0,
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => $makeAnti524ProgressCallback("Renderizando imagen en GPU ({$width}x{$height}, {$steps} pasos)...", $t0, $lastOutputTime)
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $durationMs = (microtime(true) - $t0) * 1000.0;
        finishTicketAndRecordMetric($TELEMETRY_FILE, $ticketId, 'sd', $durationMs);

        if ($response === false || $httpCode !== 200) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => "Error en Stable Diffusion (HTTP {$httpCode})" . ($curlErr ? ": {$curlErr}" : '')]);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || empty($decoded['images'][0])) {
            sendSafeJson(['type' => 'error', 'ok' => false, 'error' => 'Stable Diffusion no devolvió una imagen válida.']);
        }

        sendSafeJson([
            'type'        => 'result',
            'ok'          => true,
            'images'      => [$decoded['images'][0]],
            'sd_prompt'   => $finalSdPrompt,
            'duration_ms' => round($durationMs)
        ]);
    }

    sendSafeJson(['ok' => false, 'error' => 'Acción desconocida.'], 400);
}

// Pre-cargar modelos desde PHP para que la interfaz los tenga disponibles desde el milisegundo cero
$INITIAL_MODELS_DATA = $autenticado ? discoverAllOllamaModels($API_OLLAMA_TAGS, $DEFAULT_OLLAMA_MODEL) : ['online' => false, 'models' => []];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Aryi Neural Core v6 — Prompt Studio & Model Arena</title>
    <style>
        :root {
            --bg-color: #050811;
            --card-bg: rgba(15, 23, 42, 0.86);
            --card-border: rgba(255, 255, 255, 0.1);
            --surface-soft: rgba(0, 0, 0, 0.38);
            --primary: #00f3ff;
            --primary-dim: rgba(0, 243, 255, 0.14);
            --secondary: #8b5cf6;
            --accent-amber: #f59e0b;
            --accent-green: #10b981;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --error: #ef4444;
            --radius-lg: clamp(12px, 2vw, 22px);
            --radius-md: clamp(8px, 1.5vw, 14px);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background:
                radial-gradient(circle at 88% 12%, rgba(139, 92, 246, 0.20), transparent 42%),
                radial-gradient(circle at 12% 88%, rgba(0, 243, 255, 0.15), transparent 45%),
                var(--bg-color);
            color: var(--text);
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: clamp(6px, 1.6vw, 24px);
            overflow-x: hidden;
        }
        .app-shell { width: 100%; max-width: 1720px; margin: 0 auto; }
        .login-wrapper { width: 100%; max-width: 430px; margin: 0 auto; }
        .workspace-grid { display: grid; grid-template-columns: 1fr; gap: clamp(10px, 1.8vw, 20px); align-items: start; }
        @media (min-width: 1080px) { .workspace-grid { grid-template-columns: minmax(0, 1.85fr) minmax(340px, 1fr); } }
        @media (min-width: 1480px) { .workspace-grid { grid-template-columns: minmax(0, 2.15fr) minmax(400px, 1fr); } }

        .panel {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: clamp(11px, 2vw, 24px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.65);
            width: 100%;
        }
        .top-bar {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 8px; padding-bottom: 12px; margin-bottom: 14px; border-bottom: 1px solid var(--card-border);
        }
        .brand-group { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .pulse-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--accent-green); box-shadow: 0 0 10px var(--accent-green); flex-shrink: 0; }
        h1 { margin: 0; font-size: clamp(1rem, 2.8vw, 1.55rem); font-weight: 800; color: var(--primary); text-shadow: 0 0 14px rgba(0, 243, 255, 0.35); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-left: auto; }
        .status-pill { font-size: 0.74rem; color: var(--text-muted); background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; }
        .logout-btn { background: rgba(239, 68, 68, 0.16); border: 1px solid rgba(239, 68, 68, 0.38); color: #fca5a5; text-decoration: none; padding: 5px 11px; border-radius: 8px; font-size: 0.76rem; font-weight: 700; }

        /* Barra de Hardware y Pestañas de Modo */
        .hardware-bar { background: var(--surface-soft); border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 11px; margin-bottom: 14px; display: flex; flex-direction: column; gap: 9px; }
        .model-row { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; }
        .model-row label { font-size: 0.76rem; font-weight: 700; color: var(--primary); white-space: nowrap; }
        .model-select-group { display: flex; flex: 1 1 180px; gap: 6px; min-width: 0; }
        select, input[type="password"], input[type="text"], input[type="number"], textarea {
            width: 100%; background: rgba(0, 0, 0, 0.48); border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px; padding: 8px 10px; color: var(--text); font-size: 0.84rem; font-family: inherit; outline: none; min-width: 0;
        }
        select:focus, input:focus, textarea:focus { border-color: var(--primary); }
        .vram-controls-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, 0.06); font-size: 0.73rem; }
        .vram-badge { color: var(--accent-amber); font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .vram-badge.free { color: var(--accent-green); }
        .toggles-wrap { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .chk-label { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; color: var(--text-muted); font-size: 0.73rem; user-select: none; }

        /* Navegación de Módulos (Asistente / Constructor de Prompts / Arena de Modelos) */
        .mode-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .tab-btn {
            flex: 1 1 140px; background: rgba(255, 255, 255, 0.04); border: 1px solid var(--card-border);
            color: var(--text-muted); padding: 9px 12px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; cursor: pointer;
        }
        .tab-btn.active {
            background: var(--primary-dim); border-color: var(--primary); color: var(--primary);
            box-shadow: 0 0 15px rgba(0, 243, 255, 0.18);
        }

        details.config-drawer { background: rgba(0, 0, 0, 0.25); border: 1px solid var(--card-border); border-radius: 10px; padding: 8px 11px; font-size: 0.78rem; }
        details.config-drawer summary { cursor: pointer; font-weight: 700; color: var(--text-muted); outline: none; user-select: none; }
        details.config-drawer[open] summary { color: var(--primary); margin-bottom: 10px; }
        .drawer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); gap: 8px; align-items: end; }
        .field-box { display: flex; flex-direction: column; gap: 4px; text-align: left; }
        .field-box span { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; }

        .hidden { display: none !important; }
        .state-section { display: flex; flex-direction: column; gap: 12px; }
        textarea { resize: vertical; min-height: 130px; line-height: 1.5; }

        button {
            background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #050811; border: none;
            padding: 9px 15px; font-size: 0.82rem; font-weight: 700; border-radius: var(--radius-md);
            cursor: pointer; transition: transform 0.12s ease, opacity 0.15s ease; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        }
        button:hover:not(:disabled) { transform: translateY(-1px); }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        button.outline { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.18); color: var(--text); }
        button.amber-outline { background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.45); color: #fde68a; }
        button.danger-outline { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
        .btn-group { display: flex; flex-wrap: wrap; gap: 8px; width: 100%; }
        .btn-group > button { flex: 1 1 130px; }

        /* Deck de Voz y Respaldo Nativo */
        .voice-deck {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 18px 12px; background: rgba(0, 0, 0, 0.24); border: 1px dashed rgba(255, 255, 255, 0.13); border-radius: var(--radius-lg); gap: 10px;
        }
        .mic-btn {
            background: linear-gradient(135deg, var(--secondary), var(--primary)); border-radius: 50%;
            width: clamp(64px, 13vw, 84px); height: clamp(64px, 13vw, 84px); font-size: clamp(24px, 4vw, 32px); box-shadow: 0 12px 28px rgba(0, 243, 255, 0.25);
        }
        .mic-btn.recording { animation: pulse-rec 1.4s infinite; background: linear-gradient(135deg, #ef4444, #f97316); }
        @keyframes pulse-rec { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        #audio-canvas { width: 100%; max-width: 340px; height: 36px; border-radius: 8px; background: rgba(0, 0, 0, 0.4); }
        .hybrid-input-bar { display: flex; flex-wrap: wrap; gap: 8px; width: 100%; }
        .hybrid-input-bar input { flex: 1 1 170px; }

        /* Estudio Constructor de Prompts Profesionales */
        .prompt-studio-card {
            background: rgba(0, 0, 0, 0.32); border: 1px solid rgba(0, 243, 255, 0.22);
            border-radius: var(--radius-md); padding: 14px; display: flex; flex-direction: column; gap: 10px;
        }
        .studio-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }

        /* Arena de Comparación Multi-Modelo */
        .arena-box {
            background: rgba(0, 0, 0, 0.34); border: 1px solid rgba(139, 92, 246, 0.35);
            border-radius: var(--radius-md); padding: 14px; display: flex; flex-direction: column; gap: 12px;
        }
        .arena-models-checklist {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 8px;
        }
        .model-check-card {
            background: rgba(255, 255, 255, 0.03); border: 1px solid var(--card-border);
            border-radius: 9px; padding: 8px 10px; display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.78rem;
        }
        .model-check-card:hover { border-color: var(--primary); }
        .model-check-card input { width: auto; margin: 0; }
        .arena-results-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 12px; margin-top: 6px;
        }
        .arena-col {
            background: rgba(15, 23, 42, 0.9); border: 1px solid var(--card-border);
            border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 8px;
        }
        .arena-col.running { border-color: var(--primary); box-shadow: 0 0 18px rgba(0, 243, 255, 0.15); }
        .arena-col.done { border-color: var(--accent-green); }
        .arena-col-header { display: flex; justify-content: space-between; align-items: center; gap: 6px; border-bottom: 1px solid var(--card-border); padding-bottom: 6px; }
        .arena-col-title { font-weight: 800; font-size: 0.82rem; color: var(--primary); word-break: break-all; }
        .arena-metrics { display: flex; gap: 8px; flex-wrap: wrap; font-size: 0.7rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
        .arena-output {
            background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px; padding: 9px; font-size: 0.8rem; min-height: 170px; max-height: 340px;
            overflow-y: auto; white-space: pre-wrap; line-height: 1.45;
        }

        /* Telemetría HUD */
        .telemetry-hud { background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(0, 243, 255, 0.32); border-radius: var(--radius-md); padding: 14px; display: flex; flex-direction: column; gap: 10px; }
        .hud-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; }
        .turn-badge { background: var(--primary-dim); border: 1px solid var(--primary); color: var(--primary); font-weight: 800; font-size: 0.75rem; padding: 4px 10px; border-radius: 999px; }
        .turn-badge.queued { background: rgba(245, 158, 11, 0.16); border-color: var(--accent-amber); color: var(--accent-amber); }
        .queue-roadmap { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        @media (max-width: 380px) { .queue-roadmap { grid-template-columns: 1fr; } }
        .roadmap-step { background: rgba(255, 255, 255, 0.03); border: 1px solid var(--card-border); border-radius: 8px; padding: 6px; font-size: 0.7rem; color: var(--text-muted); text-align: center; }
        .roadmap-step.active { background: rgba(0, 243, 255, 0.12); border-color: var(--primary); color: var(--text); font-weight: 700; }
        .roadmap-step.waiting { background: rgba(245, 158, 11, 0.12); border-color: var(--accent-amber); color: #fde68a; font-weight: 700; }
        .roadmap-step.done { border-color: var(--accent-green); color: #6ee7b7; }
        .hud-metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 8px; }
        .metric-card { background: rgba(255, 255, 255, 0.03); border: 1px solid var(--card-border); border-radius: 8px; padding: 8px; }
        .metric-label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 2px; }
        .metric-value { font-size: 1rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        .progress-track { width: 100%; height: 8px; background: rgba(255, 255, 255, 0.08); border-radius: 999px; overflow: hidden; }
        .progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, var(--primary), var(--secondary)); transition: width 0.25s ease; }

        /* Multimedia y Lateral */
        .media-results-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
        @media (min-width: 680px) { .media-results-grid.dual-active { grid-template-columns: 1fr 1fr; } }
        .media-card { background: var(--surface-soft); border: 1px solid var(--card-border); border-radius: var(--radius-md); padding: 12px; display: flex; flex-direction: column; gap: 9px; }
        .media-card h3 { margin: 0; font-size: 0.84rem; color: var(--primary); display: flex; justify-content: space-between; align-items: center; }
        #generated-image { width: 100%; height: auto; border-radius: 8px; object-fit: contain; max-height: 440px; background: #000; cursor: zoom-in; }
        .side-stack { display: flex; flex-direction: column; gap: 14px; }
        .section-title { margin: 0 0 9px 0; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; justify-content: space-between; gap: 6px; }
        .health-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 7px; margin-bottom: 10px; }
        .health-chip { background: rgba(0, 0, 0, 0.35); border: 1px solid var(--card-border); border-radius: 8px; padding: 6px 8px; font-size: 0.72rem; display: flex; justify-content: space-between; align-items: center; }
        .engine-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(115px, 1fr)); gap: 8px; }
        .engine-item { background: var(--surface-soft); border: 1px solid var(--card-border); border-radius: 10px; padding: 8px 10px; }
        .engine-item .name { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; }
        .engine-item .stats { display: flex; justify-content: space-between; align-items: baseline; margin-top: 3px; font-variant-numeric: tabular-nums; }
        .engine-item .avg { font-size: 0.95rem; font-weight: 800; color: var(--primary); }
        .engine-item .last { font-size: 0.66rem; color: var(--text-muted); }
        .history-list { display: flex; flex-direction: column; gap: 8px; max-height: 320px; overflow-y: auto; }
        .history-item { background: var(--surface-soft); border: 1px solid var(--card-border); border-left: 3px solid var(--secondary); border-radius: 8px; padding: 8px 10px; font-size: 0.78rem; cursor: pointer; }
        .history-item:hover { background: rgba(255, 255, 255, 0.06); }
        .history-item .meta { display: flex; justify-content: space-between; color: var(--text-muted); font-size: 0.66rem; margin-bottom: 3px; }
        .history-item .snippet { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .toast-error { background: rgba(239, 68, 68, 0.16); border: 1px solid var(--error); color: #fecaca; padding: 9px 11px; border-radius: var(--radius-md); font-size: 0.8rem; display: flex; justify-content: space-between; align-items: center; gap: 8px; word-break: break-word; }
        .lightbox-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.88); z-index: 9999; display: flex; justify-content: center; align-items: center; padding: 16px; cursor: zoom-out; }
        .lightbox-overlay img { max-width: 96vw; max-height: 92vh; border-radius: 12px; box-shadow: 0 0 40px rgba(0, 243, 255, 0.3); }
    </style>
</head>
<body>

<?php if (!$autenticado): ?>
    <div class="login-wrapper">
        <div class="panel" style="text-align: center;">
            <div class="brand-group" style="justify-content: center; margin-bottom: 14px;">
                <span class="pulse-dot"></span>
                <h1>Aryi Neural Core v6</h1>
            </div>
            <p style="color: var(--text-muted); font-size: 0.84rem; margin: 0 0 18px 0;">
                Estación IA Privada (i7 • 32GB RAM • 6GB VRAM) • Prompt Studio & Arena
            </p>
            <form method="POST" autocomplete="off">
                <?php if ($error_msg !== null): ?>
                    <div class="toast-error" style="margin-bottom: 14px;">
                        <span>⚠️ <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>
                <input type="password" name="password" placeholder="Contraseña de acceso" required autofocus style="margin-bottom: 12px;">
                <button type="submit" style="width: 100%;">🔐 Entrar al Sistema</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="app-shell">
        <div class="workspace-grid">

            <!-- PANEL PRINCIPAL IZQUIERDO -->
            <main class="panel">
                <header class="top-bar">
                    <div class="brand-group">
                        <span class="pulse-dot"></span>
                        <h1>Aryi Neural Core v6</h1>
                    </div>
                    <div class="header-actions">
                        <span class="status-pill" id="status-text">En espera</span>
                        <a href="?salir=true" class="logout-btn" onclick="notifyLogout()">Salir</a>
                    </div>
                </header>

                <!-- BARRA DE HARDWARE Y DETECCIÓN AUTOMÁTICA DE MODELOS -->
                <div class="hardware-bar">
                    <div class="model-row">
                        <label for="ollama-model-select">🧠 Modelo Activo (<span id="detected-count"><?= count($INITIAL_MODELS_DATA['models']) ?></span> detectados):</label>
                        <div class="model-select-group">
                            <select id="ollama-model-select" title="Modelos detectados automáticamente en tu servidor Ollama">
                                <?php foreach ($INITIAL_MODELS_DATA['models'] as $m): ?>
                                    <option value="<?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8') ?><?= $m['fits_vram'] ? ' ⚡[6GB VRAM OK]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="outline" onclick="loadOllamaModels(true)" title="Auto-detectar modelos de Ollama ahora" style="padding: 6px 10px;">🔄</button>
                        </div>
                        <button id="btn-free-vram" class="amber-outline" onclick="manualFreeVram()" style="padding: 6px 10px; font-size: 0.74rem;">
                            🧹 Liberar 6GB VRAM
                        </button>
                    </div>

                    <div class="vram-controls-row">
                        <span id="vram-status-badge" class="vram-badge free">🟢 VRAM 6GB: Verificando...</span>
                        <div class="toggles-wrap">
                            <label class="chk-label"><input type="checkbox" id="stream-toggle" checked> Streaming</label>
                            <label class="chk-label"><input type="checkbox" id="memory-toggle" checked> 🧠 Memoria Chat</label>
                            <label class="chk-label"><input type="checkbox" id="autopilot-tts"> 🔊 Auto-Hablar</label>
                            <label class="chk-label"><input type="checkbox" id="autopilot-sd"> 🎨 Auto-Imagen</label>
                        </div>
                    </div>

                    <details class="config-drawer">
                        <summary>⚙️ Personalidad del Sistema y Parámetros de Memoria (LLM)</summary>
                        <div class="drawer-grid">
                            <div class="field-box">
                                <span>Rol Predefinido:</span>
                                <select id="role-preset" onchange="applyRolePreset()">
                                    <option value="Eres Aryi, una asistente de inteligencia artificial ágil, clara y directa. Responde en español.">Asistente Aryi (Estándar)</option>
                                    <option value="Eres un arquitecto de software senior experto en PHP, Python, C++, Docker, Linux e IA local. Da soluciones completas y listas para producción.">Arquitecto de Software Senior</option>
                                    <option value="Eres un ingeniero experto en Prompt Engineering. Diseñas instrucciones ultra precisas, estructuradas y optimizadas para modelos LLM y generadores de imágenes.">Ingeniero de Prompts (Meta-Prompt)</option>
                                    <option value="">Sin System Prompt (Modo Libre / Raw)</option>
                                </select>
                            </div>
                            <div class="field-box">
                                <span>Contexto (`num_ctx` 6GB VRAM):</span>
                                <select id="llm-ctx">
                                    <option value="2048" selected>2048 tokens (Óptimo 6GB VRAM)</option>
                                    <option value="4096">4096 tokens (Medio)</option>
                                    <option value="8192">8192 tokens (Usa RAM sistema)</option>
                                </select>
                            </div>
                            <div class="field-box">
                                <span>Temperatura (<strong id="temp-val">0.7</strong>):</span>
                                <input type="range" id="llm-temp" min="0.1" max="1.3" step="0.1" value="0.7" oninput="document.getElementById('temp-val').innerText=this.value">
                            </div>
                        </div>
                        <div class="field-box" style="margin-top: 8px;">
                            <span>Instrucción del Sistema (System Prompt):</span>
                            <input type="text" id="system-prompt-input" value="Eres Aryi, una asistente de inteligencia artificial ágil, clara y directa. Responde en español.">
                        </div>
                    </details>
                </div>

                <!-- PESTAÑAS DE MÓDULOS: VOZ/CHAT | PROMPT STUDIO | ARENA DE MODELOS -->
                <div class="mode-tabs">
                    <button type="button" id="tab-btn-assistant" class="tab-btn active" onclick="switchModuleTab('assistant')">
                        🎤 Asistente Voz & Chat
                    </button>
                    <button type="button" id="tab-btn-studio" class="tab-btn" onclick="switchModuleTab('studio')">
                        🛠️ Constructor de Prompts Pro
                    </button>
                    <button type="button" id="tab-btn-arena" class="tab-btn" onclick="switchModuleTab('arena')">
                        ⚔️ Arena Comparar Modelos (<span id="arena-tab-count"><?= count($INITIAL_MODELS_DATA['models']) ?></span>)
                    </button>
                </div>

                <!-- Banner de Error -->
                <div id="error-banner" class="toast-error hidden" style="margin-bottom: 12px;">
                    <span id="error-banner-text"></span>
                    <button class="outline" onclick="hideError()" style="padding: 4px 8px; font-size: 0.72rem;">✕</button>
                </div>

                <!-- =========================================================
                     MÓDULO A: ASISTENTE DE VOZ (CON RESPALDO NATIVO) Y TEXTO
                     ========================================================= -->
                <section id="module-assistant" class="state-section">
                    <div id="state-record" class="state-section">
                        <div class="voice-deck">
                            <button id="btn-mic" class="mic-btn" onclick="toggleRecording()" title="Grabar con micrófono en directo">🎤</button>
                            <div style="text-align: center;">
                                <div id="rec-timer" style="font-weight: 800; font-size: 1.05rem; color: var(--primary); font-variant-numeric: tabular-nums;">00:00.0</div>
                                <p id="mic-helper-text" style="margin: 3px 0 0 0; color: var(--text-muted); font-size: 0.8rem;">
                                    Toca el micrófono para hablar en vivo o usa el botón de Grabadora Nativa abajo
                                </p>
                            </div>
                            <canvas id="audio-canvas" class="hidden" width="340" height="36"></canvas>

                            <!-- Solución universal si el navegador bloquea getUserMedia por HTTP -->
                            <input type="file" id="native-audio-input" accept="audio/*,video/webm,video/mp4" capture="user" class="hidden" onchange="handleNativeAudioUpload(this)">
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;">
                                <button type="button" class="outline" onclick="document.getElementById('native-audio-input').click()" style="padding: 5px 10px; font-size: 0.73rem;">
                                    📱 Grabadora del Dispositivo / Subir Audio (Funciona sin HTTPS)
                                </button>
                            </div>
                        </div>

                        <div class="hybrid-input-bar">
                            <input type="text" id="manual-prompt" placeholder="O escribe tu consulta aquí..." onkeydown="if(event.key==='Enter') submitManualPrompt()">
                            <button onclick="submitManualPrompt()">⚡ Enviar a Modelo</button>
                            <button class="outline" onclick="sendInputToArena()" title="Comparar esta consulta en varios modelos">⚔️ Comparar en Arena</button>
                        </div>
                    </div>
                </section>

                <!-- =========================================================
                     MÓDULO B: CONSTRUCTOR DE PROMPTS PROFESIONALES (PROMPT STUDIO)
                     ========================================================= -->
                <section id="module-studio" class="state-section hidden">
                    <div class="prompt-studio-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <strong style="color: var(--primary); font-size: 0.9rem;">🛠️ Laboratorio de Ingeniería de Prompts Profesionales</strong>
                            <select id="studio-template" onchange="loadPromptTemplate()" style="width: auto; font-size: 0.75rem; padding: 5px 8px;">
                                <option value="">-- Cargar Plantilla Rápida --</option>
                                <option value="code">💻 Arquitectura / Código Listo para Producción</option>
                                <option value="audit">🛡️ Auditoría de Seguridad y Optimización</option>
                                <option value="sd_art">🎨 Prompt Cinematográfico (Stable Diffusion)</option>
                                <option value="teaching">🎓 Explicación Didáctica Paso a Paso</option>
                            </select>
                        </div>

                        <div class="studio-grid">
                            <div class="field-box">
                                <span>1. Rol / Experto Asignado:</span>
                                <input type="text" id="ps-role" placeholder="Ej: Arquitecto de Software Senior en PHP y Python">
                            </div>
                            <div class="field-box">
                                <span>2. Tarea Principal / Objetivo:</span>
                                <input type="text" id="ps-task" placeholder="Ej: Crear un microservicio seguro con cola FIFO">
                            </div>
                            <div class="field-box">
                                <span>3. Formato de Salida Esperado:</span>
                                <select id="ps-format">
                                    <option value="Código completo listo para producción sin omitir líneas">Código Completo (Monolito sin truncar)</option>
                                    <option value="Guía técnica estructurada paso a paso con ejemplos">Guía Técnica Paso a Paso</option>
                                    <option value="Objeto JSON estrictamente válido y estructurado">JSON Estructurado</option>
                                    <option value="Tabla comparativa técnica con pros, contras y métricas">Tabla Comparativa</option>
                                    <option value="Prompt visual descriptivo en inglés (iluminación, lente, atmósfera, 8k)">Prompt Visual para Stable Diffusion</option>
                                </select>
                            </div>
                            <div class="field-box">
                                <span>4. Tono y Nivel de Detalle:</span>
                                <select id="ps-tone">
                                    <option value="Técnico, directo, riguroso y enfocado en rendimiento">Técnico, Directo y Riguroso</option>
                                    <option value="Didáctico, claro y con comentarios explicativos">Didáctico y Explicativo</option>
                                    <option value="Creativo, descriptivo y cinematográfico">Creativo / Cinematográfico</option>
                                    <option value="Conciso, directo al grano y sin introducciones">Ultra Conciso (Sin rodeos)</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-box">
                            <span>5. Contexto Técnico, Variables o Restricciones (Ej: Hardware i7, 32GB RAM, 6GB VRAM, sin dependencias externas):</span>
                            <textarea id="ps-context" style="min-height: 70px;" placeholder="Especifica el contexto de tu proyecto, restricciones de memoria, lenguaje o fragmentos de código..."></textarea>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="outline" onclick="assembleProfessionalPrompt()">🧩 1. Ensamblar Prompt Estructurado</button>
                            <button type="button" class="amber-outline" onclick="optimizePromptWithAI()">✨ 2. Mejorar Prompt con IA (Meta-Prompt)</button>
                        </div>

                        <div class="field-box">
                            <span>Prompt Profesional Resultante (Editable):</span>
                            <textarea id="ps-result" style="min-height: 120px; border-color: var(--primary);" placeholder="Aquí aparecerá tu prompt profesional ensamblado o mejorado por IA..."></textarea>
                        </div>

                        <div class="btn-group">
                            <button type="button" onclick="runStudioPromptInSingleModel()">🚀 Ejecutar en Modelo Activo</button>
                            <button type="button" class="outline" onclick="runStudioPromptInArena()">⚔️ Comparar este Prompt en la Arena de Modelos</button>
                            <button type="button" class="outline" onclick="navigator.clipboard.writeText(document.getElementById('ps-result').value); setStatus('Prompt copiado');">📋 Copiar Prompt</button>
                        </div>
                    </div>
                </section>

                <!-- =========================================================
                     MÓDULO C: ARENA DE COMPARACIÓN ENTRE MODELOS OLLAMA
                     ========================================================= -->
                <section id="module-arena" class="state-section hidden">
                    <div class="arena-box">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <strong style="color: var(--primary); font-size: 0.92rem;">⚔️ Arena de Comparación Multi-Modelo</strong>
                                <p style="margin: 2px 0 0 0; font-size: 0.74rem; color: var(--text-muted);">
                                    Selecciona los modelos detectados en tu servidor para evaluar cuál responde mejor y más rápido.
                                </p>
                            </div>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="outline" onclick="selectArenaPreset('fast')" style="padding: 4px 9px; font-size: 0.72rem;">⚡ Modelos &le; 6GB VRAM</button>
                                <button type="button" class="outline" onclick="selectArenaPreset('all')" style="padding: 4px 9px; font-size: 0.72rem;">☑️ Todos</button>
                                <button type="button" class="outline" onclick="loadOllamaModels(true)" style="padding: 4px 9px; font-size: 0.72rem;">🔄 Re-detectar</button>
                            </div>
                        </div>

                        <!-- Lista dinámica de todos los modelos detectados -->
                        <div id="arena-models-list" class="arena-models-checklist"></div>

                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px;">
                            <label class="chk-label" style="color: #fde68a; font-weight: 600;">
                                <input type="checkbox" id="arena-unload-between" checked>
                                🧹 Liberar los 6GB de VRAM (`keep_alive: 0`) al terminar cada modelo antes de pasar al siguiente
                            </label>
                        </div>

                        <div class="field-box">
                            <span>Prompt a evaluar en todos los modelos seleccionados:</span>
                            <textarea id="arena-prompt-input" style="min-height: 85px;" placeholder="Escribe el prompt o envíalo desde el Constructor de Prompts Profesionales..."></textarea>
                        </div>

                        <div class="btn-group">
                            <button type="button" id="btn-start-arena" onclick="startModelArenaComparison()">⚔️ Iniciar Comparación de Modelos</button>
                            <button type="button" class="danger-outline" onclick="cancelActiveTurn()">⏹️ Detener Comparación</button>
                        </div>

                        <!-- Tarjetas Lado a Lado con Resultados y Métricas -->
                        <div id="arena-results-grid" class="arena-results-grid"></div>
                    </div>
                </section>

                <!-- HUD DE TURNO Y TELEMETRÍA EN TIEMPO REAL -->
                <section id="state-processing" class="state-section hidden" style="margin-top: 12px;">
                    <div class="telemetry-hud">
                        <div class="hud-top">
                            <span id="processing-label" style="font-weight: 700; color: var(--primary); font-size: 0.86rem;">Solicitando turno...</span>
                            <span id="hud-turn-badge" class="turn-badge">Verificando cola...</span>
                        </div>
                        <div class="queue-roadmap">
                            <div id="step-queue" class="roadmap-step">1. Fila de Espera</div>
                            <div id="step-gpu" class="roadmap-step">2. Turno #1 (GPU Activa)</div>
                            <div id="step-deliver" class="roadmap-step">3. Respuesta Lista</div>
                        </div>
                        <div class="progress-track">
                            <div id="hud-progress-bar" class="progress-fill"></div>
                        </div>
                        <div class="hud-metrics-grid">
                            <div class="metric-card">
                                <span class="metric-label">Tu Turno</span>
                                <span class="metric-value" id="hud-queue-pos">#1 de 1</span>
                            </div>
                            <div class="metric-card">
                                <span class="metric-label">Transcurrido</span>
                                <span class="metric-value" id="hud-elapsed">0.0s</span>
                            </div>
                            <div class="metric-card">
                                <span class="metric-label">Espera Est. (ETA)</span>
                                <span class="metric-value" id="hud-eta">Calculando...</span>
                            </div>
                            <div class="metric-card">
                                <span class="metric-label">Canal Anti-524</span>
                                <span class="metric-value" id="hud-node-task" style="font-size: 0.74rem; color: var(--accent-green);">Activo • Sin timeout</span>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end;">
                            <button class="danger-outline" onclick="cancelActiveTurn()" style="padding: 5px 11px; font-size: 0.74rem;">
                                ✖ Cancelar petición y ceder turno
                            </button>
                        </div>
                    </div>
                </section>

                <!-- WORKSPACE DE RESPUESTA, VOZ (TTS) Y ESTUDIO STABLE DIFFUSION -->
                <section id="state-workspace" class="state-section hidden" style="margin-top: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                        <label for="ollama-response" style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">
                            ✏️ Respuesta Activa de Aryi (Editable antes de enviar a Voz TTS o Stable Diffusion):
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span id="last-cycle-badges" style="font-size: 0.72rem; color: var(--primary); font-weight: 700;"></span>
                            <button class="outline" onclick="copyResponseText()" style="padding: 3px 8px; font-size: 0.7rem;">📋 Copiar</button>
                        </div>
                    </div>

                    <textarea id="ollama-response" placeholder="Recibiendo tokens de Ollama..."></textarea>

                    <details class="config-drawer" open>
                        <summary>🎨 Estudio Stable Diffusion (6GB VRAM) & Opciones de Voz</summary>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 8px;">
                            <label class="chk-label" style="color: var(--primary); font-weight: 600;">
                                <input type="checkbox" id="sd-optimize-prompt" checked> ✨ Traducir y optimizar prompt a Inglés con IA antes de renderizar
                            </label>
                            <label class="chk-label">
                                <input type="checkbox" id="auto-vram-sd" checked> 🧹 Auto-liberar 6GB VRAM antes de SD
                            </label>
                            <label class="chk-label">
                                <input type="checkbox" id="tts-clean-md" checked> 🗣️ Limpiar signos Markdown al hablar
                            </label>
                        </div>
                        <div class="drawer-grid">
                            <div class="field-box">
                                <span>Resolución (6GB VRAM):</span>
                                <select id="sd-res">
                                    <option value="512x512" selected>512x512 (Estándar Rápido)</option>
                                    <option value="512x768">512x768 (Retrato Vertical)</option>
                                    <option value="768x512">768x512 (Paisaje Horizontal)</option>
                                    <option value="640x640">640x640 (Alta Definición)</option>
                                </select>
                            </div>
                            <div class="field-box">
                                <span>Pasos (Steps):</span>
                                <input type="number" id="sd-steps" value="20" min="10" max="50">
                            </div>
                            <div class="field-box">
                                <span>CFG Scale:</span>
                                <input type="number" id="sd-cfg" value="7" min="1" max="20" step="0.5">
                            </div>
                        </div>
                        <div class="field-box" style="margin-top: 8px;">
                            <span>Prompt Negativo:</span>
                            <input type="text" id="sd-neg-prompt" value="blurry, low quality, bad anatomy, deformed, ugly, watermark, text, signature, cropped">
                        </div>
                    </details>

                    <div class="btn-group">
                        <button id="btn-tts" onclick="processTTS()">🗣️ Hablar (TTS)</button>
                        <button id="btn-sd" onclick="processImage()">🎨 Generar Imagen (SD)</button>
                        <button class="outline" onclick="resetCycle()">🔄 Nueva Consulta</button>
                    </div>

                    <div id="media-results" class="media-results-grid hidden">
                        <div id="audio-card" class="media-card hidden">
                            <h3>
                                <span>🔊 Voz Sintetizada</span>
                                <a id="download-audio" href="#" download="aryi_voz.wav" style="color: var(--primary); font-size: 0.74rem; text-decoration: none;">⬇ Descargar WAV</a>
                            </h3>
                            <audio id="audio-player" controls style="width: 100%;"></audio>
                        </div>

                        <div id="image-card" class="media-card hidden">
                            <h3>
                                <span>🖼️ Render Stable Diffusion</span>
                                <a id="download-image" href="#" download="aryi_render.png" style="color: var(--primary); font-size: 0.74rem; text-decoration: none;">⬇ Descargar PNG</a>
                            </h3>
                            <img id="generated-image" src="" alt="Render Stable Diffusion" onclick="openLightbox(this.src)" />
                            <div id="sd-used-prompt" style="font-size: 0.7rem; color: var(--text-muted); background: rgba(0,0,0,0.3); padding: 6px 8px; border-radius: 6px;"></div>
                        </div>
                    </div>
                </section>
            </main>

            <!-- PANEL LATERAL DERECHO: SALUD, TELEMETRÍA E HISTORIAL -->
            <aside class="side-stack">
                <div class="panel">
                    <div class="section-title">
                        <span>🩺 Salud Contenedores (<?= htmlspecialchars($DOCKER_IP, ENT_QUOTES, 'UTF-8') ?>)</span>
                        <button class="outline" onclick="checkDockerHealth()" style="padding: 3px 8px; font-size: 0.68rem;">🔄 Ping</button>
                    </div>
                    <div class="health-grid">
                        <div class="health-chip"><span>🎤 Whisper :8000</span><strong id="hlth-stt">...</strong></div>
                        <div class="health-chip"><span>🧠 Ollama :11434</span><strong id="hlth-ollama">...</strong></div>
                        <div class="health-chip"><span>🗣️ TTS :5050</span><strong id="hlth-tts">...</strong></div>
                        <div class="health-chip"><span>🎨 SD :7860</span><strong id="hlth-sd">...</strong></div>
                    </div>

                    <div class="section-title" style="margin-top: 12px;">
                        <span>📡 Cola Global y Latencia Media</span>
                        <span id="global-queue-pill" class="status-pill">En cola: 0</span>
                    </div>
                    <p id="global-active-banner" style="margin: 0 0 9px 0; font-size: 0.74rem; color: var(--text-muted);">
                        GPU libre • Esperando peticiones
                    </p>
                    <div class="engine-list">
                        <div class="engine-item">
                            <div class="name">🎤 Whisper STT</div>
                            <div class="stats"><span class="avg" id="stat-stt-avg">--s</span><span class="last" id="stat-stt-last">Últ: --</span></div>
                        </div>
                        <div class="engine-item">
                            <div class="name">🧠 Ollama LLM</div>
                            <div class="stats"><span class="avg" id="stat-ollama-avg">--s</span><span class="last" id="stat-ollama-last">Últ: --</span></div>
                        </div>
                        <div class="engine-item">
                            <div class="name">🗣️ Voz TTS</div>
                            <div class="stats"><span class="avg" id="stat-tts-avg">--s</span><span class="last" id="stat-tts-last">Últ: --</span></div>
                        </div>
                        <div class="engine-item">
                            <div class="name">🎨 Stable Diffusion</div>
                            <div class="stats"><span class="avg" id="stat-sd-avg">--s</span><span class="last" id="stat-sd-last">Últ: --</span></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="section-title">
                        <span>🗂️ Historial de la Sesión</span>
                        <div style="display: flex; gap: 5px;">
                            <button class="outline" onclick="exportHistoryJson()" style="padding: 3px 7px; font-size: 0.68rem;">💾</button>
                            <button class="outline" onclick="clearHistory()" style="padding: 3px 7px; font-size: 0.68rem;">Limpiar</button>
                        </div>
                    </div>
                    <div id="history-list" class="history-list"></div>
                </div>
            </aside>
        </div>
    </div>

    <div id="lightbox-modal" class="lightbox-overlay hidden" onclick="closeLightbox()">
        <img id="lightbox-img" src="" alt="Vista ampliada" />
    </div>

    <script src="https://syntaxsanctuary.com/bit.js" data-user="SyntaxSanctuary_RGG"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
        const STORAGE_KEY = 'aryi_neural_history_v6';
        let detectedModelsCatalog = <?= json_encode($INITIAL_MODELS_DATA['models'], JSON_UNESCAPED_UNICODE) ?>;

        let mediaRecorder = null;
        let audioChunks = [];
        let isRecording = false;
        let recStartTime = 0;
        let recTimerInterval = null;
        let audioCtx = null;
        let analyser = null;
        let animFrameId = null;

        let activeTicket = null;
        let activeAbortController = null;
        let telemetryPollTimer = null;
        let localStopwatchTimer = null;
        let taskStartTimestamp = 0;
        let currentEtaMs = 10000;
        let cycleLatencies = {};
        let sessionHistory = [];

        window.addEventListener('DOMContentLoaded', () => {
            renderArenaModelChecklist(detectedModelsCatalog);
            loadSavedHistory();
            loadOllamaModels(false);
            checkDockerHealth();
            refreshTelemetrySnapshot('', true);
            setInterval(() => {
                if (!activeTicket) refreshTelemetrySnapshot('', false);
            }, 3000);
        });

        window.addEventListener('pagehide', () => {
            if (activeTicket && navigator.sendBeacon) {
                navigator.sendBeacon(`?action=leave_queue&ticket=${encodeURIComponent(activeTicket)}`);
            }
        });

        function notifyLogout() {
            if (activeTicket && navigator.sendBeacon) {
                navigator.sendBeacon(`?action=leave_queue&ticket=${encodeURIComponent(activeTicket)}`);
            }
        }

        function switchModuleTab(tabName) {
            ['assistant', 'studio', 'arena'].forEach(t => {
                document.getElementById(`module-${t}`).classList.toggle('hidden', t !== tabName);
                document.getElementById(`tab-btn-${t}`).classList.toggle('active', t === tabName);
            });
        }

        function applyRolePreset() {
            document.getElementById('system-prompt-input').value = document.getElementById('role-preset').value;
        }

        function openLightbox(src) {
            if (!src) return;
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox-modal').classList.remove('hidden');
        }

        function closeLightbox() {
            document.getElementById('lightbox-modal').classList.add('hidden');
        }

        // =========================================================
        // BLINDAJE DE PARSEO Y STREAMING ANTI-524
        // =========================================================
        function formatHttpError(status, rawText) {
            if (status === 524) return "Cloudflare reportó tiempo de espera (HTTP 524). Reintenta la operación.";
            if (status === 502 || status === 504) return `El contenedor Docker no respondió a tiempo (HTTP ${status}). Verifica el semáforo de salud.`;
            const stripped = (rawText || '').replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim().slice(0, 160);
            return `Error del servidor (HTTP ${status}): ${stripped || 'Respuesta inválida'}`;
        }

        async function safeFetchJson(url, options = {}) {
            const res = await fetch(url, options);
            const rawText = await res.text();
            let data = null;
            try {
                data = JSON.parse(rawText);
            } catch (_) {
                throw new Error(formatHttpError(res.status, rawText));
            }
            if (!res.ok || (data && data.ok === false)) {
                throw new Error((data && data.error) ? data.error : formatHttpError(res.status, rawText));
            }
            return { data, headers: res.headers };
        }

        async function consumeAnti524Stream(url, options = {}, callbacks = {}) {
            const res = await fetch(url, options);
            if (!res.ok || !res.body) {
                const errRaw = await res.text();
                let parsedErr = null;
                try {
                    const obj = JSON.parse(errRaw);
                    if (obj && obj.error) parsedErr = obj.error;
                } catch (_) {}
                throw new Error(parsedErr || formatHttpError(res.status, errRaw));
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';
            let finalResult = null;

            const processLine = (line) => {
                const trimmed = line.trim();
                if (!trimmed) return;
                let obj = null;
                try {
                    obj = JSON.parse(trimmed);
                } catch (_) {
                    if (trimmed.startsWith('<!DOCTYPE') || trimmed.startsWith('<html') || trimmed.startsWith('<!--[if')) {
                        throw new Error(formatHttpError(524, trimmed));
                    }
                    return;
                }
                if (obj.ok === false || obj.type === 'error' || obj.error) {
                    throw new Error(obj.error || 'Error reportado por el contenedor de IA.');
                }
                if (obj.type === 'init' || obj.type === 'ping') {
                    if (obj.phase) {
                        document.getElementById('processing-label').innerText = `⚡ ${obj.phase}`;
                        document.getElementById('hud-node-task').innerText = 'Latido Anti-524 OK';
                    }
                    if (callbacks.onPing) callbacks.onPing(obj);
                    return;
                }
                if (obj.type === 'sd_prompt' && callbacks.onSdPrompt) {
                    callbacks.onSdPrompt(obj.sd_prompt);
                    return;
                }
                if (typeof obj.response === 'string' && callbacks.onToken) {
                    callbacks.onToken(obj);
                }
                if (obj.type === 'result' || obj.type === 'done' || obj.done === true) {
                    finalResult = obj;
                }
            };

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';
                for (const line of lines) processLine(line);
            }
            if (buffer.trim() !== '') processLine(buffer);
            return finalResult || { ok: true };
        }

        function showError(msg) {
            const banner = document.getElementById('error-banner');
            document.getElementById('error-banner-text').innerText = "⚠️ " + msg;
            banner.classList.remove('hidden');
        }
        function hideError() { document.getElementById('error-banner').classList.add('hidden'); }
        function setStatus(text) { document.getElementById('status-text').innerText = text; }
        function formatSeconds(ms) { return (!ms || ms < 0) ? "0.0s" : (ms / 1000).toFixed(1) + "s"; }

        // =========================================================
        // 1. AUTO-DETECCIÓN DE TODOS LOS MODELOS OLLAMA Y SALUD DOCKER
        // =========================================================
        async function checkDockerHealth() {
            try {
                const { data } = await safeFetchJson('?action=health', { cache: 'no-store' });
                if (data.services) {
                    for (const key of ['stt', 'ollama', 'tts', 'sd']) {
                        const el = document.getElementById(`hlth-${key}`);
                        const srv = data.services[key];
                        if (srv && srv.online) {
                            el.style.color = 'var(--accent-green)';
                            el.innerText = `🟢 ${srv.ms}ms`;
                        } else {
                            el.style.color = 'var(--error)';
                            el.innerText = `🔴 Offline`;
                        }
                    }
                }
                if (data.vram_models) updateVramBadge(data.vram_models);
            } catch (_) {}
        }

        function updateVramBadge(vramModels) {
            const badge = document.getElementById('vram-status-badge');
            if (!Array.isArray(vramModels) || vramModels.length === 0) {
                badge.className = 'vram-badge free';
                badge.innerText = '🟢 VRAM 6GB: Libre (0 modelos cargados)';
            } else {
                const names = vramModels.map(m => `${m.name.split(':')[0]}${m.vram_gb ? ' (' + m.vram_gb + 'GB)' : ''}`).join(', ');
                badge.className = 'vram-badge';
                badge.innerText = `🔥 En VRAM: ${names}`;
            }
        }

        async function loadOllamaModels(notifyUser = false) {
            const select = document.getElementById('ollama-model-select');
            const previousVal = select.value;
            try {
                const { data } = await safeFetchJson('?action=models', { cache: 'no-store' });
                if (data.models && data.models.length > 0) {
                    detectedModelsCatalog = data.models;
                    document.getElementById('detected-count').innerText = data.models.length;
                    document.getElementById('arena-tab-count').innerText = data.models.length;

                    select.innerHTML = data.models.map(m =>
                        `<option value="${escapeHtml(m.name)}">${escapeHtml(m.label)}${m.fits_vram ? ' ⚡[6GB VRAM OK]' : ''}</option>`
                    ).join('');

                    if (previousVal && data.models.some(m => m.name === previousVal)) {
                        select.value = previousVal;
                    } else if (data.default_model) {
                        select.value = data.default_model;
                    }
                    renderArenaModelChecklist(data.models);
                    if (notifyUser) setStatus(`${data.models.length} modelos detectados`);
                }
                if (data.vram_models) updateVramBadge(data.vram_models);
            } catch (err) {
                if (notifyUser) showError("No se pudo conectar con Ollama para listar modelos: " + err.message);
            }
        }

        function renderArenaModelChecklist(models) {
            const container = document.getElementById('arena-models-list');
            if (!container || !Array.isArray(models)) return;
            //Conservar selección previa si existe
            const prevChecked = new Set(
                Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value)
            );
            container.innerHTML = models.map((m, idx) => {
                const isChecked = prevChecked.size > 0 ? prevChecked.has(m.name) : (idx < 2);
                const badge = m.fits_vram
                    ? `<span style="color:var(--accent-green);font-size:0.68rem;font-weight:700;">⚡ &le;6GB</span>`
                    : `<span style="color:var(--accent-amber);font-size:0.68rem;">RAM+VRAM</span>`;
                return `
                    <label class="model-check-card">
                        <input type="checkbox" class="arena-model-cb" value="${escapeHtml(m.name)}" data-fits="${m.fits_vram ? '1' : '0'}" ${isChecked ? 'checked' : ''}>
                        <div style="min-width:0;flex:1;">
                            <div style="font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(m.name)}</div>
                            <div style="font-size:0.68rem;color:var(--text-muted);display:flex;justify-content:space-between;">
                                <span>${escapeHtml(m.param_size || '')} ${escapeHtml(m.quant || '')} (${m.size_gb || '?'} GB)</span>
                                ${badge}
                            </div>
                        </div>
                    </label>
                `;
            }).join('');
        }

        function selectArenaPreset(mode) {
            const boxes = document.querySelectorAll('.arena-model-cb');
            boxes.forEach(cb => {
                if (mode === 'all') cb.checked = true;
                else if (mode === 'fast') cb.checked = (cb.getAttribute('data-fits') === '1');
            });
        }

        async function manualFreeVram() {
            const btn = document.getElementById('btn-free-vram');
            const currentModel = document.getElementById('ollama-model-select').value;
            btn.disabled = true;
            const origText = btn.innerText;
            btn.innerText = "⏳ Liberando...";
            try {
                const { data } = await safeFetchJson('?action=free_vram', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ model: currentModel })
                });
                updateVramBadge(data.vram_models || []);
                setStatus("6GB VRAM liberados");
            } catch (err) {
                showError("Error liberando VRAM: " + err.message);
            } finally {
                btn.disabled = false;
                btn.innerText = origText;
            }
        }

        // =========================================================
        // 2. CONSTRUCTOR DE PROMPTS PROFESIONALES (PROMPT STUDIO)
        // =========================================================
        function loadPromptTemplate() {
            const tpl = document.getElementById('studio-template').value;
            if (tpl === 'code') {
                document.getElementById('ps-role').value = "Arquitecto de Software Full-Stack Senior (PHP 8, Python 3, JS, Docker)";
                document.getElementById('ps-task').value = "Escribir una solución monolítica completa, robusta y lista para producción.";
                document.getElementById('ps-context').value = "Servidor con Intel Core i7, 32GB RAM, 6GB VRAM. Sin librerías pesadas externas. Manejo estricto de errores.";
            } else if (tpl === 'audit') {
                document.getElementById('ps-role').value = "Auditor Senior de Ciberseguridad y Rendimiento de Sistemas";
                document.getElementById('ps-task').value = "Detectar vulnerabilidades, cuellos de botella de memoria y proponer el código corregido.";
                document.getElementById('ps-context').value = "Revisar inyecciones, bloqueos de sesión, fugas de VRAM y condiciones de carrera.";
            } else if (tpl === 'sd_art') {
                document.getElementById('ps-role').value = "Director de Fotografía y Concept Artist experto en Stable Diffusion";
                document.getElementById('ps-task').value = "Crear un prompt visual ultra detallado en inglés para generación de imagen.";
                document.getElementById('ps-context').value = "Incluir iluminación volumétrica, lente 35mm, composición, texturas hiperrealistas, 8k, masterpiece.";
            } else if (tpl === 'teaching') {
                document.getElementById('ps-role').value = "Instructor Técnico Senior de Programación e Inteligencia Artificial";
                document.getElementById('ps-task').value = "Explicar el concepto paso a paso desde los fundamentos hasta un ejemplo práctico en código.";
                document.getElementById('ps-context').value = "Claridad máxima, ejemplos ejecutables y buenas prácticas reales.";
            }
            assembleProfessionalPrompt();
        }

        function assembleProfessionalPrompt() {
            const role = document.getElementById('ps-role').value.trim();
            const task = document.getElementById('ps-task').value.trim();
            const format = document.getElementById('ps-format').value;
            const tone = document.getElementById('ps-tone').value;
            const ctx = document.getElementById('ps-context').value.trim();

            const blocks = [];
            if (role) blocks.push(`### ROL ACTIVO:\nActúa como ${role}.`);
            if (task) blocks.push(`### OBJETIVO Y TAREA PRINCIPAL:\n${task}`);
            if (ctx) blocks.push(`### CONTEXTO Y RESTRICCIONES TÉCNICAS:\n${ctx}`);
            blocks.push(`### FORMATO DE SALIDA Y ESTILO:\n- Formato esperado: ${format}.\n- Tono y enfoque: ${tone}.`);

            const finalPrompt = blocks.join('\n\n');
            document.getElementById('ps-result').value = finalPrompt;
            setStatus("Prompt profesional ensamblado");
            return finalPrompt;
        }

        async function optimizePromptWithAI() {
            let baseDraft = document.getElementById('ps-result').value.trim();
            if (!baseDraft) {
                baseDraft = assembleProfessionalPrompt();
            }
            if (!baseDraft) {
                showError("Completa al menos el objetivo en el Constructor de Prompts antes de optimizar con IA.");
                return;
            }

            const selectedModel = document.getElementById('ollama-model-select').value;
            const resultArea = document.getElementById('ps-result');

            try {
                const ticketId = await acquireTurnAndWait('ollama', `Optimizando Prompt Profesional con ${selectedModel}...`, 10000);
                resultArea.value = "";

                const metaInstruction =
                    "Eres un Ingeniero Maestro de Prompts (Meta-Prompt Engineer). " +
                    "Toma el siguiente borrador de prompt y transfórmalo en un Prompt Profesional de alto rendimiento, " +
                    "claro, estructurado, con instrucciones precisas, criterios de calidad y restricciones bien definidas. " +
                    "Devuelve ÚNICAMENTE el prompt mejorado listo para copiar y usar, sin saludos ni explicaciones extra:\n\n" + baseDraft;

                let optimizedText = "";
                await consumeAnti524Stream('?action=ollama', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN,
                        'X-Ticket-Id': ticketId
                    },
                    body: JSON.stringify({
                        prompt: metaInstruction,
                        model: selectedModel,
                        stream: true,
                        temperature: 0.4,
                        num_ctx: 2048
                    }),
                    signal: activeAbortController.signal
                }, {
                    onToken: (obj) => {
                        optimizedText += obj.response;
                        resultArea.value = optimizedText;
                        resultArea.scrollTop = resultArea.scrollHeight;
                    }
                });

                stopTelemetryTracking();
                setStatus("Prompt optimizado con IA");
            } catch (err) {
                if (err.name !== 'AbortError') {
                    stopTelemetryTracking();
                    showError(err.message);
                }
            }
        }

        async function runStudioPromptInSingleModel() {
            let p = document.getElementById('ps-result').value.trim();
            if (!p) p = assembleProfessionalPrompt();
            if (!p) {
                showError("El prompt está vacío.");
                return;
            }
            await executeOllama(p);
        }

        function runStudioPromptInArena() {
            let p = document.getElementById('ps-result').value.trim();
            if (!p) p = assembleProfessionalPrompt();
            document.getElementById('arena-prompt-input').value = p;
            switchModuleTab('arena');
        }

        function sendInputToArena() {
            const txt = document.getElementById('manual-prompt').value.trim();
            if (txt) document.getElementById('arena-prompt-input').value = txt;
            switchModuleTab('arena');
        }

        // =========================================================
        // 3. ARENA DE COMPARACIÓN ENTRE MÚLTIPLES MODELOS OLLAMA
        // =========================================================
        async function startModelArenaComparison() {
            const promptText = document.getElementById('arena-prompt-input').value.trim();
            if (!promptText) {
                showError("Escribe o envía un prompt para comparar en la Arena.");
                return;
            }

            const selectedModels = Array.from(document.querySelectorAll('.arena-model-cb:checked')).map(cb => cb.value);
            if (selectedModels.length === 0) {
                showError("Selecciona al menos un modelo en la lista de la Arena.");
                return;
            }

            const unloadBetween = document.getElementById('arena-unload-between').checked;
            const systemPrompt = document.getElementById('system-prompt-input').value.trim();
            const temperature = parseFloat(document.getElementById('llm-temp').value) || 0.7;
            const numCtx = parseInt(document.getElementById('llm-ctx').value, 10) || 2048;

            const grid = document.getElementById('arena-results-grid');
            grid.innerHTML = selectedModels.map((mName, idx) => `
                <div class="arena-col" id="arena-col-${idx}">
                    <div class="arena-col-header">
                        <span class="arena-col-title">🧠 ${escapeHtml(mName)}</span>
                        <span class="status-pill" id="arena-status-${idx}">En fila</span>
                    </div>
                    <div class="arena-metrics">
                        <span id="arena-time-${idx}">⏱️ 0.0s</span>
                        <span id="arena-speed-${idx}">⚡ -- tok/s</span>
                        <span id="arena-chars-${idx}">📝 0 chars</span>
                    </div>
                    <div class="arena-output" id="arena-out-${idx}">Esperando turno de GPU...</div>
                    <div class="btn-group">
                        <button type="button" class="outline" onclick="useArenaResult(${idx})" style="padding:6px 10px;font-size:0.74rem;">
                            🏆 Usar esta respuesta (TTS / SD)
                        </button>
                    </div>
                </div>
            `).join('');

            const btnArena = document.getElementById('btn-start-arena');
            btnArena.disabled = true;

            try {
                for (let i = 0; i < selectedModels.length; i++) {
                    const modelName = selectedModels[i];
                    const colEl = document.getElementById(`arena-col-${i}`);
                    const statusEl = document.getElementById(`arena-status-${i}`);
                    const timeEl = document.getElementById(`arena-time-${i}`);
                    const speedEl = document.getElementById(`arena-speed-${i}`);
                    const charsEl = document.getElementById(`arena-chars-${i}`);
                    const outEl = document.getElementById(`arena-out-${i}`);

                    colEl.classList.add('running');
                    statusEl.innerText = `Ejecutando (${i + 1}/${selectedModels.length})`;
                    outEl.textContent = "";

                    const ticketId = await acquireTurnAndWait('ollama', `Arena (${i + 1}/${selectedModels.length}): ${modelName}`, 14000);
                    const t0 = performance.now();
                    let fullText = "";
                    let tokCount = 0;

                    await consumeAnti524Stream('?action=ollama', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': CSRF_TOKEN,
                            'X-Ticket-Id': ticketId
                        },
                        body: JSON.stringify({
                            prompt: promptText,
                            system: systemPrompt,
                            model: modelName,
                            stream: true,
                            unload_after: unloadBetween,
                            temperature: temperature,
                            num_ctx: numCtx
                        }),
                        signal: activeAbortController.signal
                    }, {
                        onToken: (obj) => {
                            fullText += obj.response;
                            tokCount++;
                            outEl.textContent = fullText;
                            outEl.scrollTop = outEl.scrollHeight;
                            const elapsedSec = (performance.now() - t0) / 1000;
                            timeEl.innerText = `⏱️ ${elapsedSec.toFixed(1)}s`;
                            charsEl.innerText = `📝 ${fullText.length} chars`;
                            if (obj.eval_count && obj.eval_duration) {
                                speedEl.innerText = `⚡ ${(obj.eval_count / (obj.eval_duration / 1e9)).toFixed(1)} tok/s`;
                            } else if (elapsedSec > 0) {
                                speedEl.innerText = `⚡ ${(tokCount / elapsedSec).toFixed(1)} tok/s`;
                            }
                        }
                    });

                    const totalMs = performance.now() - t0;
                    timeEl.innerText = `⏱️ ${formatSeconds(totalMs)}`;
                    colEl.classList.remove('running');
                    colEl.classList.add('done');
                    statusEl.innerText = "✅ Completado";
                    stopTelemetryTracking();
                }
                setStatus("Comparación de Arena finalizada");
            } catch (err) {
                if (err.name !== 'AbortError') {
                    stopTelemetryTracking();
                    showError("Arena interrumpida: " + err.message);
                }
            } finally {
                btnArena.disabled = false;
            }
        }

        function useArenaResult(idx) {
            const outEl = document.getElementById(`arena-out-${idx}`);
            if (!outEl || !outEl.textContent) return;
            document.getElementById('ollama-response').value = outEl.textContent;
            document.getElementById('state-workspace').classList.remove('hidden');
            document.getElementById('state-workspace').scrollIntoView({ behavior: 'smooth' });
            setStatus(`Respuesta #${idx + 1} enviada al área de trabajo`);
        }

        // =========================================================
        // 4. COLA FIFO Y TELEMETRÍA EN VIVO
        // =========================================================
        async function acquireTurnAndWait(taskType, taskTitle, defaultEtaMs) {
            hideError();
            activeAbortController = new AbortController();
            taskStartTimestamp = performance.now();
            currentEtaMs = defaultEtaMs;

            document.getElementById('state-processing').classList.remove('hidden');
            document.getElementById('processing-label').innerText = taskTitle;
            updateRoadmapUI('queue', 0);

            const { data: ticketData } = await safeFetchJson('?action=enqueue', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ task: taskType }),
                signal: activeAbortController.signal
            });

            activeTicket = ticketData.ticket;
            applyTelemetryToHUD(ticketData);
            startLocalTimers(activeTicket);

            while (activeTicket) {
                const { data: snap } = await safeFetchJson(`?action=telemetry&ticket=${encodeURIComponent(activeTicket)}`, {
                    cache: 'no-store',
                    signal: activeAbortController.signal
                });
                applyTelemetryToHUD(snap);

                if (snap.can_execute || snap.turn === 1) {
                    updateRoadmapUI('gpu', 1);
                    document.getElementById('processing-label').innerText = `⚡ Turno #1 activo • ${taskTitle}`;
                    return activeTicket;
                }

                await new Promise((resolve, reject) => {
                    const t = setTimeout(resolve, 850);
                    activeAbortController.signal.addEventListener('abort', () => {
                        clearTimeout(t);
                        reject(new Error('Turno cancelado.'));
                    }, { once: true });
                });
            }
            throw new Error('Turno cancelado.');
        }

        function startLocalTimers() {
            clearInterval(localStopwatchTimer);
            localStopwatchTimer = setInterval(() => {
                const elapsed = performance.now() - taskStartTimestamp;
                document.getElementById('hud-elapsed').innerText = formatSeconds(elapsed);
                const remaining = Math.max(500, currentEtaMs - elapsed);
                document.getElementById('hud-eta').innerText = "~" + formatSeconds(remaining);
                const pct = Math.min(96, Math.max(6, (elapsed / Math.max(1500, currentEtaMs)) * 100));
                document.getElementById('hud-progress-bar').style.width = pct.toFixed(1) + "%";
            }, 100);

            clearInterval(telemetryPollTimer);
            telemetryPollTimer = setInterval(() => {
                if (activeTicket) refreshTelemetrySnapshot(activeTicket, false);
            }, 1200);
        }

        function stopTelemetryTracking() {
            activeTicket = null;
            clearInterval(localStopwatchTimer);
            clearInterval(telemetryPollTimer);
            document.getElementById('hud-progress-bar').style.width = "100%";
            updateRoadmapUI('deliver', 1);
            setTimeout(() => {
                if (!activeTicket) document.getElementById('state-processing').classList.add('hidden');
            }, 300);
            refreshTelemetrySnapshot('', true);
        }

        async function cancelActiveTurn() {
            const ticketToCancel = activeTicket;
            activeTicket = null;
            if (activeAbortController) activeAbortController.abort();
            clearInterval(localStopwatchTimer);
            clearInterval(telemetryPollTimer);
            document.getElementById('state-processing').classList.add('hidden');

            if (ticketToCancel) {
                try {
                    await safeFetchJson(`?action=leave_queue&ticket=${encodeURIComponent(ticketToCancel)}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN }
                    });
                } catch (_) {}
            }
            setStatus("Turno liberado");
            refreshTelemetrySnapshot('', true);
        }

        function updateRoadmapUI(phase, turn) {
            const sQueue = document.getElementById('step-queue');
            const sGpu = document.getElementById('step-gpu');
            const sDeliver = document.getElementById('step-deliver');
            sQueue.className = 'roadmap-step';
            sGpu.className = 'roadmap-step';
            sDeliver.className = 'roadmap-step';

            if (phase === 'queue' && turn > 1) {
                sQueue.className = 'roadmap-step waiting';
                sQueue.innerText = `1. En Cola (Tu turno: #${turn})`;
                sGpu.innerText = `2. Esperando GPU`;
                sDeliver.innerText = `3. Entrega de Respuesta`;
            } else if (phase === 'gpu' || turn === 1) {
                sQueue.className = 'roadmap-step done';
                sQueue.innerText = `1. Turno #1 Asignado`;
                sGpu.className = 'roadmap-step active';
                sGpu.innerText = `2. Procesando en GPU`;
                sDeliver.innerText = `3. Entrega de Respuesta`;
            } else if (phase === 'deliver') {
                sQueue.className = 'roadmap-step done';
                sGpu.className = 'roadmap-step done';
                sDeliver.className = 'roadmap-step active';
                sDeliver.innerText = `3. ¡Respuesta Lista!`;
            }
        }

        function applyTelemetryToHUD(data) {
            if (data.metrics) {
                for (const key of ['stt', 'ollama', 'tts', 'sd']) {
                    if (data.metrics[key]) {
                        document.getElementById(`stat-${key}-avg`).innerText = formatSeconds(data.metrics[key].avg_ms);
                        document.getElementById(`stat-${key}-last`).innerText = `Últ: ${formatSeconds(data.metrics[key].last_ms)}`;
                    }
                }
            }
            if (data.vram_models) updateVramBadge(data.vram_models);

            const qTotal = data.queue_total || 0;
            document.getElementById('global-queue-pill').innerText = `En cola: ${qTotal}`;
            document.getElementById('global-active-banner').innerText = qTotal > 0
                ? `🔥 ${data.active_task_label} • ${qTotal} en fila`
                : `🟢 GPU libre • Esperando peticiones`;

            if (data.turn && data.turn > 0) {
                const badge = document.getElementById('hud-turn-badge');
                document.getElementById('hud-queue-pos').innerText = `#${data.turn} de ${qTotal}`;
                const elapsedNow = performance.now() - taskStartTimestamp;
                currentEtaMs = elapsedNow + (data.eta_ms || 3000);

                if (data.turn === 1) {
                    badge.className = 'turn-badge';
                    badge.innerText = '⚡ Turno #1 • En GPU';
                    updateRoadmapUI('gpu', 1);
                    setStatus("Turno #1 (En GPU)");
                } else {
                    badge.className = 'turn-badge queued';
                    badge.innerText = `⏳ Tu turno es el #${data.turn} • En espera`;
                    document.getElementById('processing-label').innerText = `Tu turno es el #${data.turn} — ${data.active_task_label}...`;
                    updateRoadmapUI('queue', data.turn);
                    setStatus(`En cola: Turno #${data.turn}`);
                }
            }
        }

        async function refreshTelemetrySnapshot(ticketId, includeVram = false) {
            try {
                const vramParam = includeVram ? '&include_vram=1' : '';
                const { data } = await safeFetchJson(`?action=telemetry&ticket=${encodeURIComponent(ticketId)}${vramParam}`, { cache: 'no-store' });
                applyTelemetryToHUD(data);
            } catch (_) {}
        }

        function updateCycleBadges() {
            const parts = [];
            if (cycleLatencies.stt) parts.push(`STT: ${formatSeconds(cycleLatencies.stt)}`);
            if (cycleLatencies.ollama) parts.push(`LLM: ${formatSeconds(cycleLatencies.ollama)}`);
            if (cycleLatencies.tokSec) parts.push(`${cycleLatencies.tokSec} tok/s`);
            if (cycleLatencies.tts) parts.push(`TTS: ${formatSeconds(cycleLatencies.tts)}`);
            if (cycleLatencies.sd) parts.push(`SD: ${formatSeconds(cycleLatencies.sd)}`);
            document.getElementById('last-cycle-badges').innerText = parts.join(' • ');
        }

        // =========================================================
        // 5. CAPTURA DE VOZ CON DIAGNÓSTICO Y RESPALDO NATIVO (SIN HTTPS)
        // =========================================================
        function getSupportedMimeType() {
            const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'];
            for (const t of types) {
                if (window.MediaRecorder && MediaRecorder.isTypeSupported(t)) return t;
            }
            return '';
        }

        async function toggleRecording() {
            const btnMic = document.getElementById('btn-mic');
            const canvas = document.getElementById('audio-canvas');

            if (!isRecording) {
                hideError();
                if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    showError(
                        "Tu navegador desactivó el micrófono directo porque no estás en HTTPS. " +
                        "Solución inmediata: Pulsa el botón '📱 Grabadora del Dispositivo / Subir Audio' debajo del micrófono, " +
                        "o entra mediante https:// (Cloudflare Tunnel)."
                    );
                    return;
                }

                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
                    });
                    const mimeType = getSupportedMimeType();
                    mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);

                    mediaRecorder.ondataavailable = e => {
                        if (e.data && e.data.size > 0) audioChunks.push(e.data);
                    };
                    mediaRecorder.onstop = processSTT_LLM;

                    audioChunks = [];
                    mediaRecorder.start();
                    isRecording = true;

                    btnMic.classList.add('recording');
                    btnMic.innerText = "⏹️";
                    document.getElementById('mic-helper-text').innerText = "Grabando voz... Toca para encolar y procesar";
                    setStatus("Grabando voz...");

                    recStartTime = performance.now();
                    clearInterval(recTimerInterval);
                    recTimerInterval = setInterval(() => {
                        const sec = ((performance.now() - recStartTime) / 1000).toFixed(1);
                        document.getElementById('rec-timer').innerText = `00:${sec.padStart(4, '0')}`;
                    }, 100);

                    canvas.classList.remove('hidden');
                    startVisualizer(stream, canvas);
                } catch (err) {
                    let motivo = err.message || "Error desconocido";
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                        motivo = "Permiso denegado en el navegador. Toca el candado junto a la URL, activa 'Micrófono: Permitir', o usa el botón '📱 Grabadora del Dispositivo'.";
                    } else if (err.name === 'NotFoundError') {
                        motivo = "No se encontró ningún micrófono conectado.";
                    }
                    showError(`No se pudo abrir el micrófono (${err.name}): ${motivo}`);
                }
            } else {
                stopRecordingInternal();
            }
        }

        async function handleNativeAudioUpload(inputEl) {
            if (!inputEl.files || !inputEl.files[0]) return;
            const file = inputEl.files[0];
            audioChunks = [file];
            mediaRecorder = { mimeType: file.type || 'audio/webm' };
            inputEl.value = '';
            await processSTT_LLM();
        }

        function stopRecordingInternal() {
            const btnMic = document.getElementById('btn-mic');
            clearInterval(recTimerInterval);
            stopVisualizer();
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
            }
            isRecording = false;
            btnMic.classList.remove('recording');
            btnMic.innerText = "🎤";
            document.getElementById('mic-helper-text').innerText = "Toca el micrófono para hablar en vivo o usa el botón de Grabadora Nativa abajo";
        }

        function startVisualizer(stream, canvas) {
            try {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const source = audioCtx.createMediaStreamSource(stream);
                analyser = audioCtx.createAnalyser();
                analyser.fftSize = 64;
                source.connect(analyser);

                const bufferLength = analyser.frequencyBinCount;
                const dataArray = new Uint8Array(bufferLength);
                const ctx = canvas.getContext('2d');

                function draw() {
                    animFrameId = requestAnimationFrame(draw);
                    analyser.getByteFrequencyData(dataArray);
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    const barWidth = (canvas.width / bufferLength) - 2;
                    let x = 0;
                    for (let i = 0; i < bufferLength; i++) {
                        const barHeight = Math.max(3, (dataArray[i] / 255) * canvas.height);
                        ctx.fillStyle = '#00f3ff';
                        ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
                        x += barWidth + 2;
                    }
                }
                draw();
            } catch (_) {}
        }

        function stopVisualizer() {
            if (animFrameId) cancelAnimationFrame(animFrameId);
            if (audioCtx && audioCtx.state !== 'closed') audioCtx.close().catch(() => {});
            document.getElementById('audio-canvas').classList.add('hidden');
        }

        // =========================================================
        // 6. FLUJO PRINCIPAL: WHISPER -> OLLAMA -> TTS / SD
        // =========================================================
        async function submitManualPrompt() {
            const input = document.getElementById('manual-prompt');
            const text = input.value.trim();
            if (!text) {
                showError("Escribe tu consulta antes de enviar.");
                return;
            }
            input.value = '';
            cycleLatencies = {};
            await executeOllama(text);
        }

        async function processSTT_LLM() {
            cycleLatencies = {};
            try {
                const ticketId = await acquireTurnAndWait('stt', 'Transcribiendo voz en Whisper STT...', 3500);
                const audioBlob = new Blob(audioChunks, { type: mediaRecorder?.mimeType || 'audio/webm' });
                const formData = new FormData();
                formData.append('file', audioBlob, 'voice.webm');

                const t0 = performance.now();
                const sttResult = await consumeAnti524Stream('?action=stt', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Ticket-Id': ticketId },
                    body: formData,
                    signal: activeAbortController.signal
                });

                cycleLatencies.stt = sttResult.duration_ms || (performance.now() - t0);
                stopTelemetryTracking();

                const textPrompt = (sttResult.text || '').trim();
                if (!textPrompt) {
                    showError("Whisper no detectó voz clara en la grabación.");
                    return;
                }
                await executeOllama(textPrompt);
            } catch (error) {
                if (error.name === 'AbortError') return;
                stopTelemetryTracking();
                showError(error.message);
            }
        }

        function buildContextualPrompt(userPrompt) {
            if (!document.getElementById('memory-toggle').checked || sessionHistory.length === 0) {
                return userPrompt;
            }
            const recent = sessionHistory.slice(0, 3).reverse();
            let contextStr = "Historial reciente de la conversación:\n";
            for (const item of recent) {
                contextStr += `Usuario: ${item.prompt}\nAryi: ${item.response}\n---\n`;
            }
            contextStr += `Nueva consulta del Usuario: ${userPrompt}`;
            return contextStr;
        }

        async function executeOllama(promptText) {
            const selectedModel = document.getElementById('ollama-model-select').value;
            const useStreaming = document.getElementById('stream-toggle').checked;
            const systemPrompt = document.getElementById('system-prompt-input').value.trim();
            const temperature = parseFloat(document.getElementById('llm-temp').value) || 0.7;
            const numCtx = parseInt(document.getElementById('llm-ctx').value, 10) || 2048;
            const responseBox = document.getElementById('ollama-response');

            const fullPromptWithMemory = buildContextualPrompt(promptText);

            try {
                const ticketId = await acquireTurnAndWait('ollama', `Ejecutando ${selectedModel}...`, 12000);
                const t0 = performance.now();

                responseBox.value = '';
                document.getElementById('state-workspace').classList.remove('hidden');

                let fullResponse = '';
                let tokenCount = 0;

                const finalPacket = await consumeAnti524Stream('?action=ollama', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN,
                        'X-Ticket-Id': ticketId
                    },
                    body: JSON.stringify({
                        prompt: fullPromptWithMemory,
                        system: systemPrompt,
                        model: selectedModel,
                        stream: useStreaming,
                        temperature: temperature,
                        num_ctx: numCtx
                    }),
                    signal: activeAbortController.signal
                }, {
                    onToken: (obj) => {
                        fullResponse += obj.response;
                        tokenCount++;
                        responseBox.value = fullResponse;
                        responseBox.scrollTop = responseBox.scrollHeight;
                        if (obj.eval_count && obj.eval_duration) {
                            cycleLatencies.tokSec = (obj.eval_count / (obj.eval_duration / 1e9)).toFixed(1);
                        }
                    }
                });

                if (!useStreaming && finalPacket && typeof finalPacket.response === 'string') {
                    fullResponse = finalPacket.response.trim();
                    responseBox.value = fullResponse;
                }

                cycleLatencies.ollama = (finalPacket && finalPacket.duration_ms) ? finalPacket.duration_ms : (performance.now() - t0);
                if (!cycleLatencies.tokSec && tokenCount > 0) {
                    cycleLatencies.tokSec = (tokenCount / (cycleLatencies.ollama / 1000)).toFixed(1);
                }

                stopTelemetryTracking();
                updateCycleBadges();
                setStatus("Respuesta completada");
                addHistoryItem(promptText, fullResponse || responseBox.value, selectedModel);

                if (document.getElementById('autopilot-tts').checked) await processTTS();
                if (document.getElementById('autopilot-sd').checked) await processImage();
            } catch (error) {
                if (error.name === 'AbortError') return;
                stopTelemetryTracking();
                showError(error.message);
            }
        }

        function sanitizeTextForSpeech(rawText) {
            if (!document.getElementById('tts-clean-md').checked) return rawText;
            return rawText
                .replace(/```[\s\S]*?```/g, ' [bloque de código omitido] ')
                .replace(/`([^`]+)`/g, '$1')
                .replace(/[*_~#>-]+/g, ' ')
                .replace(/\[([^\]]+)\]\([^\)]+\)/g, '$1')
                .replace(/\s+/g, ' ')
                .trim();
        }

        async function processTTS() {
            const rawText = document.getElementById('ollama-response').value.trim();
            if (!rawText) {
                showError("No hay texto para sintetizar en voz.");
                return;
            }
            const textToSpeak = sanitizeTextForSpeech(rawText);
            const btnTts = document.getElementById('btn-tts');
            btnTts.disabled = true;

            try {
                const ticketId = await acquireTurnAndWait('tts', 'Sintetizando voz en servidor TTS...', 4200);
                const t0 = performance.now();

                const ttsResult = await consumeAnti524Stream('?action=tts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN, 'X-Ticket-Id': ticketId },
                    body: JSON.stringify({ text: textToSpeak }),
                    signal: activeAbortController.signal
                });

                if (!ttsResult.audio_base64) throw new Error("El servidor TTS no devolvió audio válido.");
                cycleLatencies.tts = ttsResult.duration_ms || (performance.now() - t0);

                const binaryStr = atob(ttsResult.audio_base64);
                const bytes = new Uint8Array(binaryStr.length);
                for (let i = 0; i < binaryStr.length; i++) bytes[i] = binaryStr.charCodeAt(i);
                const audioBlob = new Blob([bytes], { type: 'audio/wav' });
                const audioUrl = URL.createObjectURL(audioBlob);

                stopTelemetryTracking();
                updateCycleBadges();

                document.getElementById('media-results').classList.remove('hidden');
                document.getElementById('audio-card').classList.remove('hidden');
                updateDualGridLayout();

                document.getElementById('download-audio').href = audioUrl;
                const audioPlayer = document.getElementById('audio-player');
                audioPlayer.src = audioUrl;
                audioPlayer.play().catch(() => {});
                setStatus("Voz reproducida");
            } catch (error) {
                if (error.name !== 'AbortError') {
                    stopTelemetryTracking();
                    showError(error.message);
                }
            } finally {
                btnTts.disabled = false;
            }
        }

        async function processImage() {
            const promptText = document.getElementById('ollama-response').value.trim();
            const stepsVal = parseInt(document.getElementById('sd-steps').value, 10) || 20;
            const cfgVal = parseFloat(document.getElementById('sd-cfg').value) || 7.0;
            const negPrompt = document.getElementById('sd-neg-prompt').value.trim();
            const resParts = (document.getElementById('sd-res').value || '512x512').split('x');
            const widthVal = parseInt(resParts[0], 10) || 512;
            const heightVal = parseInt(resParts[1], 10) || 512;
            const autoFreeVram = document.getElementById('auto-vram-sd').checked;
            const optimizePrompt = document.getElementById('sd-optimize-prompt').checked;
            const currentModel = document.getElementById('ollama-model-select').value;

            if (!promptText) {
                showError("El cuadro de texto para generar la imagen está vacío.");
                return;
            }

            const btnSd = document.getElementById('btn-sd');
            btnSd.disabled = true;

            try {
                const ticketId = await acquireTurnAndWait('sd', `Preparando render en Stable Diffusion (${widthVal}x${heightVal})...`, 25000);
                const t0 = performance.now();
                const promptBadge = document.getElementById('sd-used-prompt');
                promptBadge.innerText = "Preparando prompt visual...";

                const sdData = await consumeAnti524Stream('?action=sd', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN, 'X-Ticket-Id': ticketId },
                    body: JSON.stringify({
                        prompt: promptText,
                        negative_prompt: negPrompt,
                        steps: stepsVal,
                        cfg_scale: cfgVal,
                        width: widthVal,
                        height: heightVal,
                        free_vram: autoFreeVram,
                        optimize_prompt: optimizePrompt,
                        model: currentModel
                    }),
                    signal: activeAbortController.signal
                }, {
                    onSdPrompt: (translatedPrompt) => {
                        promptBadge.innerText = `✨ Prompt IA (EN): ${translatedPrompt}`;
                    }
                });

                if (!sdData.images || !sdData.images[0]) throw new Error("Stable Diffusion no devolvió una imagen válida.");

                cycleLatencies.sd = sdData.duration_ms || (performance.now() - t0);
                stopTelemetryTracking();
                updateCycleBadges();

                const dataUri = `data:image/png;base64,${sdData.images[0]}`;
                document.getElementById('media-results').classList.remove('hidden');
                document.getElementById('image-card').classList.remove('hidden');
                updateDualGridLayout();

                document.getElementById('generated-image').src = dataUri;
                document.getElementById('download-image').href = dataUri;
                if (sdData.sd_prompt) promptBadge.innerText = `✨ Prompt aplicado: ${sdData.sd_prompt}`;
                setStatus("Imagen renderizada");
            } catch (error) {
                if (error.name !== 'AbortError') {
                    stopTelemetryTracking();
                    showError(error.message);
                }
            } finally {
                btnSd.disabled = false;
            }
        }

        function updateDualGridLayout() {
            const mediaGrid = document.getElementById('media-results');
            const audioVisible = !document.getElementById('audio-card').classList.contains('hidden');
            const imageVisible = !document.getElementById('image-card').classList.contains('hidden');
            mediaGrid.classList.toggle('dual-active', audioVisible && imageVisible);
        }

        function copyResponseText() {
            const val = document.getElementById('ollama-response').value;
            if (!val) return;
            navigator.clipboard.writeText(val).then(() => setStatus("Copiado al portapapeles")).catch(() => {});
        }

        // =========================================================
        // 7. HISTORIAL DE SESIÓN Y EXPORTACIÓN
        // =========================================================
        function loadSavedHistory() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) sessionHistory = parsed.slice(0, 30);
                }
            } catch (_) {}
            renderHistory();
        }

        function saveHistoryToStorage() {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(sessionHistory.slice(0, 30))); } catch (_) {}
        }

        function addHistoryItem(prompt, response, modelName) {
            const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            sessionHistory.unshift({ time: timeStr, prompt, response, model: modelName, latencies: { ...cycleLatencies } });
            saveHistoryToStorage();
            renderHistory();
        }

        function renderHistory() {
            const container = document.getElementById('history-list');
            if (sessionHistory.length === 0) {
                container.innerHTML = `<p style="color: var(--text-muted); font-size: 0.78rem; margin: 0;">Tus consultas procesadas se guardan automáticamente aquí.</p>`;
                return;
            }
            container.innerHTML = sessionHistory.map((item, idx) => `
                <div class="history-item" onclick="restoreHistoryItem(${idx})">
                    <div class="meta">
                        <span>🕒 ${escapeHtml(item.time)} • ${escapeHtml((item.model || '').split(':')[0])}</span>
                        <span>${formatSeconds((item.latencies && item.latencies.ollama) || 0)}</span>
                    </div>
                    <div class="snippet"><strong>Tú:</strong> ${escapeHtml(item.prompt)}</div>
                    <div class="snippet" style="color: var(--text-muted); margin-top: 2px;">${escapeHtml(item.response)}</div>
                </div>
            `).join('');
        }

        function restoreHistoryItem(idx) {
            const item = sessionHistory[idx];
            if (!item) return;
            document.getElementById('state-workspace').classList.remove('hidden');
            document.getElementById('ollama-response').value = item.response;
            cycleLatencies = { ...(item.latencies || {}) };
            updateCycleBadges();
            setStatus("Restaurado del historial");
        }

        function clearHistory() {
            sessionHistory = [];
            try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
            renderHistory();
        }

        function exportHistoryJson() {
            if (sessionHistory.length === 0) {
                showError("No hay elementos en el historial para exportar.");
                return;
            }
            const blob = new Blob([JSON.stringify(sessionHistory, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `aryi_historial_${new Date().toISOString().slice(0,10)}.json`;
            a.click();
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        function resetCycle() {
            if (activeTicket) cancelActiveTurn();
            document.getElementById('ollama-response').value = "";
            document.getElementById('rec-timer').innerText = "00:00.0";
            const audioPlayer = document.getElementById('audio-player');
            audioPlayer.pause();
            audioPlayer.src = "";
            document.getElementById('audio-card').classList.add('hidden');
            document.getElementById('image-card').classList.add('hidden');
            document.getElementById('media-results').classList.add('hidden');
            document.getElementById('state-workspace').classList.add('hidden');
            switchModuleTab('assistant');
            setStatus("En espera");
        }
    </script>
<?php endif; ?>

</body>
</html>