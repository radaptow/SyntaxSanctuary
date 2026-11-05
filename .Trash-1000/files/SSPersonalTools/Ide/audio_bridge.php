<?php
// audio_bridge.php - Puente LAMP <-> Nodo IA Windows (100.115.1.71)
header('Access-Control-Allow-Origin: *');

$WINDOWS_IP   = '100.115.1.71';
$TTS_ENDPOINT = "http://{$WINDOWS_IP}:5050/tts";
$STT_ENDPOINT = "http://{$WINDOWS_IP}:8000/v1/audio/transcriptions";

$action = $_GET['action'] ?? '';

// ==========================================
// 1. MÓDULO TTS (Texto a Voz -> Puerto 5050/tts)
// ==========================================
if ($action === 'tts') {
    $input = json_decode(file_get_contents('php://input'), true);
    $text  = trim($input['text'] ?? $input['texto'] ?? '');

    if ($text === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'El texto está vacío']);
        exit;
    }

    // Enviamos 'texto' (requerido por tu contenedor en :5050/tts) junto con 'text' e 'input'
    $payload = json_encode([
        'texto' => $text,
        'text'  => $text,
        'input' => $text,
        'voice' => $input['voice'] ?? 'es-MX-DaliaNeural'
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($TTS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30
    ]);

    $audioData   = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'audio/wav';
    $curlError   = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode([
            'error'     => "Fallo en {$TTS_ENDPOINT} (HTTP {$httpCode})",
            'http_code' => $httpCode,
            'detalle'   => $curlError ?: "HTTP {$httpCode}: " . $audioData
        ]);
        exit;
    }

    // Entregamos el binario de audio al reproductor del navegador
    header("Content-Type: {$contentType}");
    header('Content-Length: ' . strlen($audioData));
    echo $audioData;
    exit;
}

// ==========================================
// 2. MÓDULO WHISPER STT (Voz a Texto -> Puerto 8000)
// ==========================================
if ($action === 'stt') {
    header('Content-Type: application/json');

    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No se recibió archivo de audio válido']);
        exit;
    }

    $tmpPath  = $_FILES['audio']['tmp_name'];
    $mimeType = $_FILES['audio']['type'] ?: 'audio/webm';
    $cFile    = new CURLFile($tmpPath, $mimeType, 'grabacion.webm');

    $postFields = [
        'file'       => $cFile,
        'audio_file' => $cFile,
        'language'   => 'es',
        'model'      => 'whisper-1'
    ];

    $ch = curl_init($STT_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_TIMEOUT        => 60
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400) {
        http_response_code(502);
        echo json_encode([
            'error'     => "Error conectando a Whisper en {$STT_ENDPOINT}",
            'http_code' => $httpCode,
            'detalle'   => $curlError ?: $response
        ]);
        exit;
    }

    $decoded = json_decode($response, true);
    $textoTranscrito = is_array($decoded) 
        ? ($decoded['text'] ?? $decoded['texto'] ?? $decoded['transcription'] ?? json_encode($decoded))
        : trim($response);

    echo json_encode([
        'ok'   => true,
        'text' => $textoTranscrito
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status' => 'Audio Bridge Activo', 'tts' => $TTS_ENDPOINT, 'stt' => $STT_ENDPOINT]);
