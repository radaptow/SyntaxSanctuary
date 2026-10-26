<?php
session_start();

// Define tu contraseña segura aquí
$PASSWORD_SECRETA = "1234";

// Lógica de inicio de sesión
if (isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD_SECRETA) {$_SESSION['logeado'] = true;
    } else {
        $error_msg = "Contraseña incorrecta.";
    }
}

// Lógica para cerrar sesión
if (isset($_GET['salir'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$autenticado = isset($_SESSION['logeado']) &&$_SESSION['logeado'] === true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whisper Interface</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');

        :root {
            --bg-color: #0B0F19;
            --card-bg: rgba(30, 41, 59, 0.7);
            --primary-cyan: #06b6d4;
            --primary-violet: #8b5cf6;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #1e1b4b, var(--bg-color) 40%),
                        radial-gradient(circle at bottom left, #083344, var(--bg-color) 40%);
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 700px;
            padding: 2rem;
            box-sizing: border-box;
        }

        .glass-panel {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-cyan), var(--primary-violet));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        /* Estilos del Login */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .login-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            color: white;
            font-size: 1.1rem;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.3s;
        }

        .login-input:focus { border-color: var(--primary-cyan); }
        
        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-cyan), var(--primary-violet));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(6, 182, 212, 0.3);
        }

        .error-msg {
            color: #ef4444;
            text-align: center;
            font-weight: 600;
            margin: 0;
        }

        /* Estilos de la Interfaz Whisper */
        .endpoint-config {
            background: rgba(0, 0, 0, 0.2);
            padding: 10px 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .endpoint-config label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .endpoint-config input { flex-grow: 1; background: transparent; border: none; color: var(--text-main); font-family: monospace; font-size: 0.9rem; outline: none; }

        .mic-stage { display: flex; flex-direction: column; align-items: center; gap: 1rem; margin-bottom: 2rem; }

        .mic-btn {
            width: 80px; height: 80px; border-radius: 50%; border: none;
            background: linear-gradient(135deg, var(--primary-violet), var(--primary-cyan));
            color: white; cursor: pointer; display: flex; justify-content: center; align-items: center;
            box-shadow: 0 10px 25px rgba(6, 182, 212, 0.3); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .mic-btn svg { width: 35px; height: 35px; fill: currentColor; transition: transform 0.2s; }
        .mic-btn:hover { transform: scale(1.05); box-shadow: 0 15px 35px rgba(139, 92, 246, 0.4); }
        .mic-btn.recording { animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite; background: #ef4444; box-shadow: none; }
        .mic-btn.processing { background: #f59e0b; animation: spin-pulse 2s linear infinite; }

        #status-text { font-weight: 600; color: var(--text-muted); font-size: 1rem; margin: 0; height: 24px; transition: color 0.3s; }

        textarea {
            width: 100%; height: 200px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px; padding: 1.5rem; color: var(--text-main); font-family: 'Inter', sans-serif;
            font-size: 1.1rem; line-height: 1.6; resize: none; box-sizing: border-box; outline: none;
        }

        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: bold;
            transition: background 0.3s;
        }

        .logout-btn:hover { background: rgba(239, 68, 68, 0.4); }

        @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        @keyframes spin-pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(0.9); opacity: 0.8; } 100% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>

    <div class="container">
        <div class="glass-panel">
            
            <div class="header">
                <h1>Voz a Texto</h1>
            </div>

            <?php if (!$autenticado): ?>
                <!-- PANTALLA DE LOGIN -->
                <form method="POST" class="login-form">
                    <?php if (isset($error_msg)): ?>
                        <p class="error-msg"><?php echo $error_msg; ?></p>
                    <?php endif; ?>
                    <input type="password" name="password" class="login-input" placeholder="Ingresa la contraseña..." required autofocus>
                    <button type="submit" class="login-btn">Acceder al Sistema</button>
                </form>

            <?php else: ?>
                <!-- INTERFAZ PRINCIPAL DE WHISPER -->
                <a href="?salir=true" class="logout-btn">Cerrar Sesión</a>

                <div class="endpoint-config">
                    <label>API Bridge:</label>
                    <input type="text" id="endpoint" value="enviar_whisper.php" readonly>
                </div>

                <div class="mic-stage">
                    <button id="mic-btn" class="mic-btn" title="Haz clic para grabar">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                        </svg>
                    </button>
                    <p id="status-text">Listo para escuchar</p>
                </div>

                <textarea id="transcript" placeholder="La transcripción aparecerá aquí..."></textarea>

                <script>
                    const micBtn = document.getElementById('mic-btn');
                    const statusText = document.getElementById('status-text');
                    const transcript = document.getElementById('transcript');
                    const endpointInput = document.getElementById('endpoint');

                    let mediaRecorder;
                    let audioChunks = [];
                    let isRecording = false;

                    micBtn.addEventListener('click', async () => {
                        if (isRecording) stopRecording();
                        else startRecording();
                    });

                    async function startRecording() {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];

                            mediaRecorder.ondataavailable = event => {
                                if (event.data.size > 0) audioChunks.push(event.data);
                            };

                            mediaRecorder.onstop = processAudio;

                            mediaRecorder.start();
                            isRecording = true;
                            
                            micBtn.classList.add('recording');
                            micBtn.classList.remove('processing');
                            statusText.textContent = "Grabando... (Haz clic para detener)";
                            statusText.style.color = "#ef4444";
                            
                        } catch (err) {
                            statusText.textContent = "Error: Sin acceso al micrófono";
                        }
                    }

                    function stopRecording() {
                        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                            mediaRecorder.stop();
                            isRecording = false;
                            mediaRecorder.stream.getTracks().forEach(track => track.stop());
                            
                            micBtn.classList.remove('recording');
                            micBtn.classList.add('processing');
                            statusText.textContent = "Procesando audio en GPU...";
                            statusText.style.color = "#f59e0b";
                        }
                    }

                    async function processAudio() {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        const formData = new FormData();
                        formData.append('file', audioBlob, 'voice.webm');

                        try {
                            const response = await fetch(endpointInput.value, {
                                method: 'POST',
                                body: formData
                            });

                            if (!response.ok) {
                                // Si el bridge devuelve 403 (sesión caducada)
                                if(response.status === 403) window.location.reload();
                                throw new Error(`HTTP Error: ${response.status}`);
                            }
                            
                            const data = await response.json();
                            
                            const currentText = transcript.value;
                            const newText = data.text.trim();
                            
                            transcript.value = currentText ? `${currentText}\n\n${newText}` : newText;
                            transcript.scrollTop = transcript.scrollHeight;
                            
                            statusText.textContent = "¡Transcripción exitosa!";
                            statusText.style.color = "#10b981";

                        } catch (error) {
                            console.error(error);
                            statusText.textContent = "Error al contactar la API";
                            statusText.style.color = "#ef4444";
                        } finally {
                            setTimeout(() => {
                                micBtn.classList.remove('processing');
                                if(!isRecording) {
                                    statusText.textContent = "Listo para escuchar";
                                    statusText.style.color = "var(--text-muted)";
                                }
                            }, 2000);
                        }
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>