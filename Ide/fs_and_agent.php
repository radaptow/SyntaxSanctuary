<?php
// fs_and_agent.php - File Manager API + Orquestador Paso a Paso con Ollama (:11434)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$WINDOWS_IP  = '100.115.1.71';
$OLLAMA_BASE = "http://{$WINDOWS_IP}:11434";
$WORKSPACE   = __DIR__ . '/workspace';

if (!is_dir($WORKSPACE)) {
    @mkdir($WORKSPACE, 0775, true);
}

// Función de seguridad para evitar salir de workspace/
function rutaSegura(string $base, string $rel): string {
    $rel = trim(str_replace(['\\', '..'], ['/', ''], $rel), '/');
    return $rel === '' ? $base : $base . '/' . $rel;
}

// Escaneo recursivo del árbol de archivos para el File Manager y el contexto de la IA
function escanearArbol(string $dir, string $base): array {
    $items = [];
    if (!is_dir($dir)) return $items;
    $entradas = array_diff(scandir($dir), ['.', '..']);
    foreach ($entradas as $nombre) {
        $full = $dir . '/' . $nombre;
        $rel  = ltrim(str_replace($base, '', $full), '/');
        if (is_dir($full)) {
            $items[] = [
                'name'     => $nombre,
                'path'     => $rel,
                'type'     => 'dir',
                'children' => escanearArbol($full, $base)
            ];
        } else {
            $items[] = [
                'name' => $nombre,
                'path' => $rel,
                'type' => 'file',
                'size' => filesize($full)
            ];
        }
    }
    return $items;
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ====================================================
// 1. GESTOR DE ARCHIVOS (File Manager API)
// ====================================================
if ($action === 'fs_list') {
    echo json_encode([
        'ok'   => true,
        'tree' => escanearArbol($WORKSPACE, $WORKSPACE)
    ]);
    exit;
}

if ($action === 'fs_read') {
    $rel  = $input['path'] ?? ($_GET['path'] ?? '');
    $full = rutaSegura($WORKSPACE, $rel);
    if (!is_file($full)) {
        http_response_code(404);
        echo json_encode(['error' => 'Archivo no encontrado: ' . $rel]);
        exit;
    }
    echo json_encode([
        'ok'      => true,
        'path'    => $rel,
        'content' => file_get_contents($full)
    ]);
    exit;
}

if ($action === 'fs_write') {
    $rel     = $input['path'] ?? '';
    $content = $input['content'] ?? '';
    if ($rel === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Ruta vacía']);
        exit;
    }
    $full = rutaSegura($WORKSPACE, $rel);
    @mkdir(dirname($full), 0775, true);
    file_put_contents($full, $content);
    echo json_encode(['ok' => true, 'path' => $rel]);
    exit;
}

if ($action === 'fs_mkdir') {
    $rel  = $input['path'] ?? '';
    $full = rutaSegura($WORKSPACE, $rel);
    @mkdir($full, 0775, true);
    echo json_encode(['ok' => true, 'path' => $rel]);
    exit;
}

if ($action === 'fs_delete') {
    $rel  = $input['path'] ?? '';
    $full = rutaSegura($WORKSPACE, $rel);
    if (is_file($full)) {
        @unlink($full);
    } elseif (is_dir($full)) {
        @rmdir($full);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ====================================================
// 2. DESCUBRIMIENTO DE MODELOS OLLAMA (:11434)
// ====================================================
if ($action === 'models') {
    $ch = curl_init("{$OLLAMA_BASE}/api/tags");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $models = [];
    if ($code === 200 && $res) {
        $data = json_decode($res, true);
        foreach (($data['models'] ?? []) as $m) {
            $models[] = $m['name'];
        }
    }
    echo json_encode(['ok' => true, 'models' => $models]);
    exit;
}

// ====================================================
// 3. ORQUESTADOR PASO A PASO CON OLLAMA (:11434)
// ====================================================
if ($action === 'agent') {
    $prompt      = trim($input['prompt'] ?? '');
    $mode        = $input['mode'] ?? 'php_project';
    $model       = $input['model'] ?? 'qwen2.5-coder:latest';
    $currentPath = $input['current_path'] ?? '';
    $currentCode = $input['current_code'] ?? '';
    $history     = $input['history'] ?? [];

    // Resumen del árbol actual para que la IA tenga conciencia del File Manager
    $arbolActual = json_encode(escanearArbol($WORKSPACE, $WORKSPACE), JSON_UNESCAPED_UNICODE);

    $modosPrompt = [
        'php_project'  => "MODO ARQUITECTO PHP/FULL-STACK: Guía al usuario paso a paso para crear su proyecto. Propón y crea carpetas o archivos de uno en uno, validando cada decisión con él.",
        'web_builder'  => "MODO CONSTRUCTOR WEB: Crea y edita páginas HTML/CSS/JS paso a paso (1. Estructura, 2. Diseño responsivo, 3. Interactividad) para que el usuario vea el resultado en la pestaña Preview.",
        'lang_creator' => "MODO CREADOR DE LENGUAJES DE PROGRAMACIÓN: Guía al usuario paso a paso en la creación de su propio lenguaje: Paso 1 Gramática, Paso 2 Lexer (Tokenizador), Paso 3 Parser (Árbol AST), Paso 4 Transpilador/Intérprete."
    ];
    $instruccionModo = $modosPrompt[$mode] ?? $modosPrompt['php_project'];

    $systemPrompt = <<<SYS
Eres el Agente Senior-Junior integrado en el Web IDE de Ramiro (Syntax Sanctuary).
{$instruccionModo}

REGLA DE ORO (PASO A PASO):
NUNCA hagas todo el proyecto de golpe. Avanza UN SOLO PASO lógico por turno, ejecuta las acciones de archivos necesarias para ese paso, explica brevemente qué hiciste y haz una pregunta concreta para que Ramiro te retroalimente antes del siguiente paso.

CONTEXTO ACTUAL DEL IDE:
- Archivo abierto en el editor (Path): "{$currentPath}"
- Contenido actual del editor:
{$currentCode}
- Árbol de archivos en workspace/:
{$arbolActual}

RESPONDE ESTRICTAMENTE EN FORMATO JSON VÁLIDO CON ESTA ESTRUCTURA EXACTA:
{
  "voz_respuesta": "Texto breve y natural en español (máximo 2 oraciones) que se leerá en voz alta por TTS explicando qué hiciste y preguntando el siguiente paso.",
  "detalle_chat": "Explicación técnica concisa para el registro del chat.",
  "acciones_ide": [
    { "tipo": "mkdir", "ruta": "nombre_carpeta" },
    { "tipo": "write", "ruta": "nombre_carpeta/index.php", "contenido": "<?php\n// código aquí\n" },
    { "tipo": "open_editor", "ruta": "nombre_carpeta/index.php" }
  ]
}
Si en este turno solo estás haciendo una pregunta de diseño y aún no debes crear archivos, deja "acciones_ide": [].
SYS;

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach (array_slice($history, -6) as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $payload = json_encode([
        'model'    => $model,
        'messages' => $messages,
        'format'   => 'json',
        'stream'   => false
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init("{$OLLAMA_BASE}/api/chat");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 90
    ]);

    $res       = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400) {
        http_response_code(502);
        echo json_encode([
            'error'   => "Error conectando a Ollama en {$OLLAMA_BASE} (Modelo: {$model})",
            'detalle' => $curlError ?: $res
        ]);
        exit;
    }

    $ollamaData = json_decode($res, true);
    $rawContent = $ollamaData['message']['content'] ?? '{}';
    $parsed     = json_decode($rawContent, true);

    if (!is_array($parsed)) {
        $parsed = [
            'voz_respuesta' => strip_tags($rawContent),
            'detalle_chat'  => $rawContent,
            'acciones_ide'  => []
        ];
    }

    // Ejecutamos en el servidor LAMP las acciones que decidió el agente
    $archivoAbrir = null;
    $accionesEjecutadas = [];

    foreach (($parsed['acciones_ide'] ?? []) as $acc) {
        $tipo = $acc['tipo'] ?? '';
        $ruta = trim($acc['ruta'] ?? '', '/');
        if ($ruta === '') continue;

        $full = rutaSegura($WORKSPACE, $ruta);
        if ($tipo === 'mkdir') {
            @mkdir($full, 0775, true);
            $accionesEjecutadas[] = "📁 Carpeta creada: {$ruta}";
        } elseif ($tipo === 'write') {
            @mkdir(dirname($full), 0775, true);
            file_put_contents($full, $acc['contenido'] ?? '');
            $accionesEjecutadas[] = "📝 Archivo escrito: {$ruta}";
            $archivoAbrir = $ruta;
        } elseif ($tipo === 'open_editor') {
            $archivoAbrir = $ruta;
        }
    }

    echo json_encode([
        'ok'                  => true,
        'voz_respuesta'       => $parsed['voz_respuesta'] ?? 'Paso completado.',
        'detalle_chat'        => $parsed['detalle_chat'] ?? ($parsed['voz_respuesta'] ?? ''),
        'acciones_ejecutadas' => $accionesEjecutadas,
        'open_file'           => $archivoAbrir,
        'tree'                => escanearArbol($WORKSPACE, $WORKSPACE)
    ]);
    exit;
}

echo json_encode(['status' => 'FS & Agent Bridge Activo']);
