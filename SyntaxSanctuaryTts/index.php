<?php
declare(strict_types=1);
session_start();

/* ============================================================
 * CONFIGURACIÓN
 * ============================================================ */
const ADMIN_PASSWORD = '1234'; // Tu contraseña de acceso
// Pon aquí la IP de tu máquina Windows (la local o la de Tailscale)
const WINDOWS_DOCKER_IP = 'xxx.xxx.x.xxx'; 
const WINDOWS_DOCKER_PORT = '5050';

/* ============================================================
 * LOGIN SYSTEM
 * ============================================================ */
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === ADMIN_PASSWORD) {
        $_SESSION['tts_authenticated'] = true;
    } else {
        $login_error = "Contraseña incorrecta.";
    }
}

$is_logged_in = $_SESSION['tts_authenticated'] ?? false;

/* ============================================================
 * PUENTE API (AJAX -> PHP -> DOCKER WINDOWS -> PHP -> AJAX)
 * ============================================================ */
if ($is_logged_in && isset($_GET['action']) && $_GET['action'] === 'synthesize') {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = trim($input['texto'] ?? '');
    
    // Ya no mandamos $voice porque el modelo actual de Python no lo soporta

    if ($text !== '') {
        $ch = curl_init('http://' . WINDOWS_DOCKER_IP . ':' . WINDOWS_DOCKER_PORT . '/tts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Eliminar límite de tiempo en la transferencia y en la conexión
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        
        // CORRECCIÓN 1: Enviar 'texto' en lugar de 'text'
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['texto' => $text]));
        
        $audio_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $audio_data !== false) {
            // CORRECCIÓN 2: Cambiar a audio/wav
            header('Content-Type: audio/wav');
            echo $audio_data;
        } else {
            http_response_code(500);
            echo "Error de conexión con el servidor en Windows. Código HTTP: " . $http_code;
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sintetizador de Voz | Syntax Sanctuary</title>
    <style>
        :root {
            --bg: #09090b;
            --surface: #18181b;
            --primary: #00f0ff;
            --primary-hover: #08c5d1;
            --text: #f4f4f5;
            --border: #27272a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .app-container {
            width: 100%;
            max-width: 600px;
            background: var(--surface);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 40px rgba(0,240,255,0.05);
        }
        h1 { font-size: 1.8rem; margin-bottom: 20px; color: var(--primary); text-align: center; }
        .form-group { margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px; }
        label { font-size: 0.9rem; font-weight: 600; color: #a1a1aa; }
        input[type="password"], textarea, select {
            width: 100%; padding: 12px; border-radius: 8px;
            background: var(--bg); border: 1px solid var(--border);
            color: var(--text); font-family: inherit; font-size: 1rem;
        }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--primary); }
        textarea { resize: vertical; min-height: 150px; }
        .btn {
            width: 100%; padding: 14px; background: var(--primary); color: #000;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: bold;
            cursor: pointer; transition: background 0.2s;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn:disabled { background: #3f3f46; cursor: not-allowed; }
        .error { color: #fca5a5; background: #450a0a; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; border: 1px solid #7f1d1d;}
        .logout { display: block; text-align: center; margin-top: 20px; color: #a1a1aa; text-decoration: none; font-size: 0.85rem;}
        .logout:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="app-container">
    <h1>🎙️ Generador TTS IA</h1>

    <?php if (!$is_logged_in): ?>
        <!-- PANTALLA DE LOGIN -->
        <?php if (isset($login_error)): ?>
            <div class="error"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Contraseña de Acceso</label>
                <input type="password" name="login_password" required placeholder="Ingresa tu clave maestra">
            </div>
            <button type="submit" class="btn">Desbloquear Sistema</button>
        </form>

    <?php else: ?>
        <!-- PANTALLA DEL SINTETIZADOR -->
        <!--<div class="form-group">
            <label>Selecciona la voz</label>
            <select id="voz_select">
                <option value="es-MX-JorgeNeural">Jorge (México - Masculino)</option>
                <option value="es-MX-DaliaNeural">Dalia (México - Femenino)</option>
                <option value="es-ES-AlvaroNeural">Álvaro (España - Masculino)</option>
                <option value="es-ES-ElviraNeural">Elvira (España - Femenino)</option>
                <option value="en-US-ChristopherNeural">Christopher (Inglés US)</option>
            </select>
        </div>-->
        <div class="form-group">
            <label>Texto a convertir</label>
            <textarea id="texto_input" placeholder="Escribe el texto que deseas que la IA lea en voz alta..."></textarea>
        </div>
        <button id="btn_generar" class="btn" onclick="generarAudio()">▶️ Sintetizar y Escuchar</button>
        
        <audio id="reproductor" style="display:none;" controls></audio>
        
        <a href="?logout=1" class="logout">Cerrar Sesión</a>

        <script>
            async function generarAudio() {
                const btn = document.getElementById('btn_generar');
                const texto = document.getElementById('texto_input').value;
                //const voz = document.getElementById('voz_select').value;
                const reproductor = document.getElementById('reproductor');

                if (!texto.trim()) { alert('Por favor, escribe algo.'); return; }

                btn.disabled = true;
                btn.innerText = '⏳ Generando audio (Consultando Docker en Windows)...';

                try {
                    const response = await fetch('?action=synthesize', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ texto: texto })
                    });

                    if (!response.ok) throw new Error('Error en la síntesis');

                    const blob = await response.blob();
                    const audioUrl = URL.createObjectURL(blob);
                    
                    reproductor.src = audioUrl;
                    reproductor.style.display = 'block';
                    reproductor.style.width = '100%';
                    reproductor.style.marginTop = '20px';
                    reproductor.play();
                } catch (error) {
                    alert('Error: Asegúrate de que el Docker en Windows esté corriendo y la IP sea correcta en lector.php');
                    console.error(error);
                } finally {
                    btn.disabled = false;
                    btn.innerText = '▶️ Sintetizar y Escuchar';
                }
            }
        </script>
    <?php endif; ?>
</div>

</body>
</html>