<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SD WEBUI / AUTOMATIC1111 - MONOLITO PHP (CON SEGURIDAD)
|--------------------------------------------------------------------------
| Compatible con:
|   POST http://xxx.xxx.xxx.xxx:7860/sdapi/v1/txt2img
|
| Todo en un solo archivo con protección de contraseña.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');

/* =========================
   CONFIGURACIÓN & SEGURIDAD
   ========================= */

$SD_URL = 'http://xxx.xxx.xx.xxx:7860/sdapi/v1/txt2img';

// La contraseña por defecto es: secreto123
// Puedes cambiarla configurando la variable de entorno APP_PASSWORD_HASH
$PASSWORD_HASH = getenv('APP_PASSWORD_HASH') ?: '$2y$10$EAxGijvmozvttuyiuyiua9YOY.h0s0oaGbWa.gikvLOe9T4tDKn7S5i'; 

session_name('SDMONOLITHSESS');
session_start();

$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $inputPassword = $_POST['password'] ?? '';
        if (password_verify($inputPassword, $PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '', '?'));
            exit;
        } else {
            $loginError = 'Contraseña incorrecta.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'logout') {
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '', '?'));
        exit;
    }
}

$authenticated = ($_SESSION['authenticated'] ?? false) === true;

$DEFAULT_PROMPT = 'a cybernetic glowing monolith in a futuristic city';
$DEFAULT_STEPS  = 10;

$resultImage = null;
$error       = null;
$elapsed     = null;
$statusCode  = null;


/* =========================
   FUNCIONES
   ========================= */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


/**
 * Envía el prompt a Stable Diffusion.
 */
function generateImage(
    string $url,
    string $prompt,
    int $steps
): array {

    $payload = [
        'prompt' => $prompt,
        'steps'  => $steps
    ];

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new RuntimeException('No se pudo construir el JSON.');
    }

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('No se pudo inicializar cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_CONNECTTIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
    ]);

    $start = microtime(true);
    $response = curl_exec($ch);
    $elapsed = microtime(true) - $start;

    if ($response === false) {
        $message = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException(
            'Error de conexión con Stable Diffusion: ' . $message
        );
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(
            'Stable Diffusion respondió HTTP ' .
            $httpCode .
            '. Respuesta: ' .
            substr($response, 0, 1000)
        );
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            'La respuesta no contiene JSON válido.'
        );
    }

    if (
        !isset($data['images']) ||
        !is_array($data['images']) ||
        empty($data['images'][0])
    ) {
        throw new RuntimeException(
            'La API respondió correctamente pero no devolvió ninguna imagen.'
        );
    }

    return [
        'image'   => $data['images'][0],
        'elapsed' => $elapsed,
        'status'  => $httpCode
    ];
}


/* =========================
   PROCESAR FORMULARIO
   ========================= */

$prompt = $DEFAULT_PROMPT;
$steps  = $DEFAULT_STEPS;

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'generate')) {

    $prompt = trim(
        (string)($_POST['prompt'] ?? '')
    );

    $steps = filter_input(
        INPUT_POST,
        'steps',
        FILTER_VALIDATE_INT
    );

    if ($steps === false || $steps === null) {
        $steps = $DEFAULT_STEPS;
    }

    $steps = max(1, min(100, $steps));

    if ($prompt === '') {
        $error = 'El prompt no puede estar vacío.';
    } elseif (strlen($prompt) > 4000) {
        $error = 'El prompt es demasiado largo.';
    } else {
        try {
            $result = generateImage(
                $SD_URL,
                $prompt,
                $steps
            );

            $resultImage = $result['image'];
            $elapsed     = $result['elapsed'];
            $statusCode  = $result['status'];

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SD Monolith</title>
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; min-width: 320px; }
body {
    min-height: 100vh;
    background: radial-gradient(circle at top, #17202b 0, #080b0f 45%, #030405 100%);
    color: #e8edf2;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.app { width: 100%; max-width: 1100px; margin: 0 auto; padding: clamp(12px, 3vw, 30px); }
header { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.logo { font-size: clamp(20px, 5vw, 34px); font-weight: 800; letter-spacing: 0.08em; color: #00f3ff; text-shadow: 0 0 10px rgba(0, 243, 255, .4); }
.subtitle { margin-top: 5px; color: #7f8b96; font-size: 13px; }
.panel { width: 100%; padding: clamp(14px, 3vw, 25px); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; background: rgba(10, 14, 18, .88); box-shadow: 0 20px 60px rgba(0,0,0,.35); }
label { display: block; margin-bottom: 8px; color: #aeb8c2; font-size: 13px; font-weight: 600; }
textarea, input { width: 100%; border: 1px solid #27323c; border-radius: 9px; background: #070a0d; color: #e9f0f5; outline: none; }
textarea { min-height: 120px; resize: vertical; padding: 13px; line-height: 1.5; font-family: inherit; font-size: 15px; }
textarea:focus, input:focus { border-color: #00f3ff; box-shadow: 0 0 0 2px rgba(0,243,255,.08); }
.controls { display: grid; grid-template-columns: minmax(100px, 160px) 1fr; gap: 12px; margin-top: 15px; }
input { height: 45px; padding: 10px; font-size: 15px; }
button { width: 100%; min-height: 45px; border: 0; border-radius: 9px; background: #00f3ff; color: #001014; font-size: 14px; font-weight: 800; cursor: pointer; transition: transform .15s, box-shadow .15s, opacity .15s; }
button:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(0,243,255,.18); }
button:active { transform: translateY(0); }
.logout-btn { width: auto; padding: 6px 14px; min-height: auto; background: #ff4646; color: #fff; font-size: 12px; }
.logout-btn:hover { box-shadow: 0 8px 25px rgba(255,70,70,.18); }
.endpoint { margin-top: 14px; padding: 10px; border-radius: 8px; background: #050709; color: #74818d; font-family: monospace; font-size: 11px; overflow-x: auto; white-space: nowrap; }
.error { margin-top: 18px; padding: 13px; border: 1px solid rgba(255,70,70,.35); border-radius: 9px; background: rgba(100,0,0,.18); color: #ff8c8c; font-size: 13px; word-break: break-word; }
.result { margin-top: 22px; overflow: hidden; border-radius: 12px; border: 1px solid rgba(255,255,255,.08); background: #050709; }
.result-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,.07); font-size: 12px; color: #8d9aa5; }
.status { color: #66ffb2; }
.image-wrapper { width: 100%; display: flex; justify-content: center; background: #000; }
.image-wrapper img { display: block; width: 100%; height: auto; max-width: 1024px; }
footer { margin-top: 18px; text-align: center; color: #56616b; font-size: 11px; }
@media (max-width: 500px) {
    .app { padding: 10px; }
    .controls { grid-template-columns: 1fr; }
    .panel { border-radius: 10px; }
    textarea { min-height: 110px; }
}
</style>
</head>
<body>
<main class="app">
<header>
    <div>
        <div class="logo">SD // MONOLITH</div>
        <div class="subtitle">PHP → Stable Diffusion API → Imagen</div>
    </div>
    <?php if ($authenticated): ?>
    <form method="POST">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="logout-btn">SALIR</button>
    </form>
    <?php endif; ?>
</header>

<section class="panel">
<?php if (!$authenticated): ?>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <label for="password">CONTRASEÑA DE ACCESO</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Introduce tu clave..." style="margin-bottom: 15px;">
        <button type="submit">ACCEDER AL SISTEMA</button>
    </form>
    <?php if ($loginError !== null): ?>
        <div class="error"><?= h($loginError) ?></div>
    <?php endif; ?>
<?php else: ?>
    <form method="POST">
        <input type="hidden" name="action" value="generate">
        <label for="prompt">PROMPT</label>
        <textarea id="prompt" name="prompt" maxlength="4000" required><?= h($prompt) ?></textarea>
        <div class="controls">
            <div>
                <label for="steps">STEPS</label>
                <input id="steps" name="steps" type="number" min="1" max="100" value="<?= h((string)$steps) ?>">
            </div>
            <div style="display:flex;align-items:end;">
                <button type="submit">GENERAR IMAGEN</button>
            </div>
        </div>
    </form>

    <div class="endpoint"><?= h($SD_URL) ?></div>

    <?php if ($error !== null): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($resultImage !== null): ?>
    <section class="result">
        <div class="result-header">
            <span>RESULTADO</span>
            <span class="status">HTTP <?= h((string)$statusCode) ?> · <?= number_format((float)$elapsed, 2) ?> s</span>
        </div>
        <div class="image-wrapper">
            <img src="data:image/png;base64,<?= h($resultImage) ?>" alt="Imagen generada por Stable Diffusion">
        </div>
    </section>
    <?php endif; ?>
<?php endif; ?>
</section>

<footer>
    SD MONOLITH · PHP + cURL · <?= date('Y') ?>
</footer>
</main>
</body>
</html>