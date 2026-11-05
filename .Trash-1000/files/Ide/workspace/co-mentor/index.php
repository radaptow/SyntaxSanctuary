<?php
/**
 * CO-MENTOR DIGITAL PARA "LOS TWOS"
 * Monolito PHP + Vanilla JS + CSS/SVG Procedural Avatar + Ollama Local
 */

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ollamaUrl = 'http://100.115.1.71:11434';

    // 1. Endpoint para listar los modelos locales instalados en Ollama
    if ($_GET['api'] === 'models') {
        $ch = curl_init("$ollamaUrl/api/tags");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$res) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo conectar con Ollama en 127.0.0.1:11434']);
            exit;
        }
        echo $res;
        exit;
    }

    // 2. Endpoint para consultar al modelo y devolver respuesta lista para hablar
    if ($_GET['api'] === 'ask' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $prompt = trim($input['prompt'] ?? '');
        $model  = trim($input['model'] ?? 'gemma2');

        if ($prompt === '') {
            echo json_encode(['ok' => false, 'error' => 'Prompt vacío']);
            exit;
        }

        $systemPrompt = "Eres el asistente digital y co-mentor en vivo del instructor. "
            . "Tu audiencia son 'los twos' (aspirantes a convertirse en estudiantes autodidactas de tecnología y programación). "
            . "REGLAS ESTRICTAS DE RESPUESTA:\n"
            . "1. Tu respuesta será leída en voz alta inmediatamente por un sintetizador de voz.\n"
            . "2. NO uses formato Markdown, NO uses asteriscos (*), ni numerales (#), ni bloques de código, ni listas con guiones.\n"
            . "3. Habla en español con un tono natural, directo, camarada, motivador y muy claro.\n"
            . "4. Ve directo al grano (máximo 3 a 5 oraciones fluidas) explicando el concepto con una analogía práctica para los twos.";

        $payload = json_encode([
            'model'  => $model,
            'system' => $systemPrompt,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.6
            ]
        ]);

        $ch = curl_init("$ollamaUrl/api/generate");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$res) {
            echo json_encode(['ok' => false, 'error' => 'Error al consultar Ollama: ' . $err]);
            exit;
        }

        $data = json_decode($res, true);
        $rawText = $data['response'] ?? 'Escuché la pregunta, pero el modelo no devolvió respuesta.';

        // Limpieza extra para que el sintetizador de voz hable impecable
        $cleanSpeech = preg_replace('/[*#`_~>-]+/', ' ', $rawText);
        $cleanSpeech = preg_replace('/\s+/', ' ', trim($cleanSpeech));

        echo json_encode([
            'ok'     => true,
            'speech' => $cleanSpeech
        ]);
        exit;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Co-Mentor IA | Asistente en Vivo para Los Twos</title>
<style>
    :root {
        --bg-dark: #0b0e14;
        --panel-bg: rgba(18, 24, 34, 0.85);
        --accent: #00d2ff;
        --accent-glow: rgba(0, 210, 255, 0.45);
        --listening: #ff3b5c;
        --thinking: #ffb800;
        --speaking: #00e676;
        --text: #e2e8f0;

        /* Variables dinámicas controladas por JS para animar la cara */
        --mouth-open: 0;       /* 0 a 1 */
        --mouth-width: 1;      /* 0.85 a 1.15 */
        --jaw-drop: 0px;       /* 0px a 9px */
        --brow-lift: 0px;      /* -6px a 4px */
        --eye-x: 0px;          /* -5px a 5px */
        --eye-y: 0px;          /* -3px a 3px */
        --head-tilt: 0deg;     /* rotación sutil */
        --lens-energy: 0.25;   /* brillo en las gafas azules */
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        user-select: none;
    }

    body {
        background: radial-gradient(circle at 50% 35%, #182232 0%, var(--bg-dark) 75%);
        color: var(--text);
        font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    /* Contenedor del Escenario */
    .stage {
        position: relative;
        width: 460px;
        height: 540px;
        display: flex;
        align-items: center;
        justify-content: center;
        filter: drop-shadow(0 20px 40px rgba(0,0,0,0.7));
    }

    /* Halo de estado detrás del avatar */
    .status-halo {
        position: absolute;
        width: 340px;
        height: 340px;
        border-radius: 50%;
        background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
        opacity: 0.35;
        transition: background 0.3s ease, transform 0.3s ease, opacity 0.3s ease;
        z-index: 0;
    }

    body.state-listening .status-halo {
        background: radial-gradient(circle, rgba(255, 59, 92, 0.55) 0%, transparent 70%);
        transform: scale(1.12);
        opacity: 0.85;
    }
    body.state-thinking .status-halo {
        background: radial-gradient(circle, rgba(255, 184, 0, 0.55) 0%, transparent 70%);
        animation: pulseHalo 1s infinite alternate;
        opacity: 0.85;
    }
    body.state-speaking .status-halo {
        background: radial-gradient(circle, rgba(0, 230, 118, 0.5) 0%, transparent 70%);
        opacity: 0.9;
    }

    @keyframes pulseHalo {
        from { transform: scale(0.95); }
        to   { transform: scale(1.15); }
    }

    /* SVG Avatar y Articulaciones CSS */
    .avatar-svg {
        width: 100%;
        height: 100%;
        z-index: 2;
        overflow: visible;
    }

    /* Respiración natural y rotación de cabeza */
    #head-group {
        transform-origin: 230px 340px;
        transform: rotate(var(--head-tilt));
        animation: breathe 4.5s ease-in-out infinite;
        transition: transform 0.12s ease-out;
    }

    @keyframes breathe {
        0%, 100% { translate: 0 0px; }
        50%      { translate: 0 -4px; }
    }

    /* Movimiento de cejas */
    #eyebrows {
        transform: translateY(var(--brow-lift));
        transition: transform 0.12s ease-out;
    }

    /* Mirada (Pupilas e Iris) */
    .iris-group {
        transform: translate(var(--eye-x), var(--eye-y));
        transition: transform 0.08s ease-out;
    }

    /* Parpadeo */
    .eyelid {
        transform-origin: center;
        transform: scaleY(0);
        transition: transform 0.08s ease-in-out;
    }
    .blink .eyelid {
        transform: scaleY(1);
    }

    /* Mandíbula inferior y barba baja sincronizadas con la voz */
    #jaw-group {
        transform: translateY(var(--jaw-drop));
        transition: transform 0.04s linear;
    }

    /* Reflejo activo en los lentes azules sobre la gorra */
    #glasses-glow {
        opacity: var(--lens-energy);
        transition: opacity 0.15s ease;
    }

    /* Ondas de audio inferiores */
    .voice-waves {
        position: absolute;
        bottom: 18px;
        display: flex;
        gap: 6px;
        height: 28px;
        align-items: center;
        z-index: 5;
        opacity: 0;
        transition: opacity 0.25s;
    }
    body.state-speaking .voice-waves,
    body.state-listening .voice-waves {
        opacity: 1;
    }
    .wave-bar {
        width: 5px;
        height: 6px;
        border-radius: 4px;
        background: var(--accent);
        transition: height 0.05s ease;
    }
    body.state-listening .wave-bar {
        background: var(--listening);
    }
    body.state-speaking .wave-bar {
        background: var(--speaking);
    }

    /* HUD de Control (Ocultable con tecla H para limpiar la pantalla en clase) */
    .hud {
        position: fixed;
        bottom: 20px;
        background: var(--panel-bg);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.1);
        padding: 14px 20px;
        border-radius: 16px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        justify-content: center;
        max-width: 92vw;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        z-index: 10;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    .hud.hidden {
        opacity: 0;
        pointer-events: none;
        transform: translateY(20px);
    }

    .badge-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(255,255,255,0.06);
    }
    .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #64748b;
    }
    body.state-listening .dot { background: var(--listening); box-shadow: 0 0 10px var(--listening); }
    body.state-thinking .dot  { background: var(--thinking);  box-shadow: 0 0 10px var(--thinking); }
    body.state-speaking .dot  { background: var(--speaking);  box-shadow: 0 0 10px var(--speaking); }

    select, input[type="text"], button {
        background: rgba(15, 23, 42, 0.9);
        color: var(--text);
        border: 1px solid rgba(255,255,255,0.15);
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        outline: none;
    }
    button {
        cursor: pointer;
        font-weight: 600;
        transition: all 0.15s;
    }
    button.primary {
        background: linear-gradient(135deg, #0072ff, #00c6ff);
        border: none;
        color: #fff;
    }
    button.primary:hover {
        filter: brightness(1.15);
    }
    button.active-mode {
        background: var(--listening);
        border-color: var(--listening);
        color: #fff;
    }

    .shortcut-hint {
        position: fixed;
        top: 14px;
        right: 18px;
        font-size: 0.78rem;
        color: rgba(255,255,255,0.45);
        background: rgba(0,0,0,0.35);
        padding: 6px 10px;
        border-radius: 6px;
    }
</style>
</head>
<body>

<div class="shortcut-hint">
    Mantén <b>[ESPACIO]</b> para hablarle | <b>[H]</b> ocultar controles | <b>[ESC]</b> callar voz
</div>

<div class="stage" id="avatar-stage">
    <div class="status-halo"></div>

    <!-- RECREACIÓN VECTORIAL ARTICULADA BASADA EN TU FOTO -->
    <svg class="avatar-svg" viewBox="0 0 460 540" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <!-- Tonos de piel con sombreado realista -->
            <radialGradient id="skinGrad" cx="50%" cy="42%" r="58%">
                <stop offset="0%" stop-color="#d89e72"/>
                <stop offset="65%" stop-color="#be8156"/>
                <stop offset="100%" stop-color="#96603a"/>
            </radialGradient>

            <!-- Degradado de lentes deportivos polarizados azules -->
            <linearGradient id="blueLens" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#00f2fe"/>
                <stop offset="45%" stop-color="#0072ff"/>
                <stop offset="85%" stop-color="#002b66"/>
                <stop offset="100%" stop-color="#00d2ff"/>
            </linearGradient>

            <!-- Brillo especular sobre los lentes -->
            <linearGradient id="lensSheen" x1="0%" y1="0%" x2="80%" y2="100%">
                <stop offset="0%" stop-color="rgba(255,255,255,0.65)"/>
                <stop offset="35%" stop-color="rgba(0,242,254,0.25)"/>
                <stop offset="100%" stop-color="rgba(255,255,255,0)"/>
            </linearGradient>

            <!-- Textura de barba oscura y densa -->
            <linearGradient id="beardGrad" x1="50%" y1="0%" x2="50%" y2="100%">
                <stop offset="0%" stop-color="#23201e"/>
                <stop offset="50%" stop-color="#171513"/>
                <stop offset="100%" stop-color="#0e0d0c"/>
            </linearGradient>

            <!-- Degradado gorra oscura -->
            <linearGradient id="capGrad" x1="50%" y1="0%" x2="50%" y2="100%">
                <stop offset="0%" stop-color="#2c3034"/>
                <stop offset="100%" stop-color="#16181a"/>
            </linearGradient>

            <!-- Iris claro expresivo -->
            <radialGradient id="irisGrad" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#4a5d4e"/>
                <stop offset="65%" stop-color="#687864"/>
                <stop offset="100%" stop-color="#2c352d"/>
            </radialGradient>
        </defs>

        <!-- 1. CAMISETA NEGRA DE CUELLO REDONDO Y HOMBROS -->
        <g id="torso">
            <path d="M 35 540 C 45 435, 115 405, 165 395 L 295 395 C 345 405, 415 435, 425 540 Z" fill="#121316"/>
            <!-- Sombra y pliegues sutiles de la camiseta negra -->
            <path d="M 155 395 Q 230 445 305 395" fill="none" stroke="#1d2024" stroke-width="14" stroke-linecap="round"/>
            <!-- Cuello -->
            <path d="M 182 355 L 182 408 Q 230 432 278 408 L 278 355 Z" fill="#a87048"/>
        </g>

        <!-- 2. CABEZA ARTICULADA -->
        <g id="head-group">

            <!-- Orejas y auricular oscuro izquierdo -->
            <ellipse cx="132" cy="245" rx="14" ry="28" fill="#ad744c"/>
            <ellipse cx="328" cy="245" rx="14" ry="28" fill="#ad744c"/>
            <ellipse cx="326" cy="252" rx="7" ry="11" fill="#1b1e23" stroke="#00d2ff" stroke-width="1"/>

            <!-- Base del rostro / cráneo -->
            <path d="M 138 165 C 135 265, 148 355, 230 375 C 312 355, 325 265, 322 165 Z" fill="url(#skinGrad)"/>

            <!-- Sombra superior proyectada por la visera de la gorra -->
            <path d="M 138 165 Q 230 190 322 165 L 322 195 Q 230 212 138 195 Z" fill="rgba(0,0,0,0.28)"/>

            <!-- 3. OJOS REALISTAS Y PARPADEO -->
            <g id="eyes">
                <!-- Ojo Izquierdo -->
                <g transform="translate(184, 218)">
                    <path d="M -24 0 Q 0 -13 24 0 Q 0 11 -24 0 Z" fill="#f1f5f9"/>
                    <g class="iris-group">
                        <circle cx="0" cy="-1" r="8.5" fill="url(#irisGrad)"/>
                        <circle cx="0" cy="-1" r="3.8" fill="#0f1419"/>
                        <circle cx="-2.5" cy="-3.5" r="1.8" fill="#ffffff" opacity="0.85"/>
                    </g>
                    <!-- Párpado para parpadeo -->
                    <path class="eyelid" d="M -25 -14 L 25 -14 L 25 12 L -25 12 Z" fill="#b87b50"/>
                    <path d="M -25 0 Q 0 -14 25 0" fill="none" stroke="#231c18" stroke-width="2.5" stroke-linecap="round"/>
                </g>

                <!-- Ojo Derecho -->
                <g transform="translate(276, 218)">
                    <path d="M -24 0 Q 0 -13 24 0 Q 0 11 -24 0 Z" fill="#f1f5f9"/>
                    <g class="iris-group">
                        <circle cx="0" cy="-1" r="8.5" fill="url(#irisGrad)"/>
                        <circle cx="0" cy="-1" r="3.8" fill="#0f1419"/>
                        <circle cx="-2.5" cy="-3.5" r="1.8" fill="#ffffff" opacity="0.85"/>
                    </g>
                    <!-- Párpado para parpadeo -->
                    <path class="eyelid" d="M -25 -14 L 25 -14 L 25 12 L -25 12 Z" fill="#b87b50"/>
                    <path d="M -25 0 Q 0 -14 25 0" fill="none" stroke="#231c18" stroke-width="2.5" stroke-linecap="round"/>
                </g>
            </g>

            <!-- 4. CEJAS DEFINIDAS ARTICULADAS -->
            <g id="eyebrows">
                <path d="M 154 202 Q 182 190 212 200" fill="none" stroke="#1e1a17" stroke-width="7.5" stroke-linecap="round"/>
                <path d="M 248 200 Q 278 190 306 202" fill="none" stroke="#1e1a17" stroke-width="7.5" stroke-linecap="round"/>
            </g>

            <!-- 5. NARIZ CON BRILLO Y RELIEVE -->
            <g id="nose">
                <path d="M 222 212 L 215 262 Q 230 273 245 262 L 238 212" fill="rgba(135, 78, 42, 0.28)"/>
                <path d="M 213 262 Q 230 274 247 262" fill="none" stroke="#8c522b" stroke-width="3" stroke-linecap="round"/>
                <!-- Brillo sutil en la punta de la nariz -->
                <ellipse cx="230" cy="256" rx="5" ry="7" fill="rgba(255,255,255,0.16)"/>
            </g>

            <!-- 6. BARBA LATERAL ALTA (PATILLAS Y MEJILLAS) -->
            <path d="M 138 215 C 126 270, 132 335, 165 375 C 185 395, 275 395, 295 375 C 328 335, 334 270, 322 215
                     C 316 255, 305 288, 285 305 L 175 305 C 155 288, 144 255, 138 215 Z"
                  fill="url(#beardGrad)"/>

            <!-- 7. MANDÍBULA INFERIOR, BARBA TUPIDA Y BOCA SINCRONIZADA -->
            <g id="jaw-group">
                <!-- Masa principal de la barba rizada/ondulada con textura exterior -->
                <path d="M 136 285
                         C 122 325, 132 378, 158 408
                         C 178 430, 205 442, 230 444
                         C 255 442, 282 430, 302 408
                         C 328 378, 338 325, 324 285
                         C 305 302, 275 312, 230 312
                         C 185 312, 155 302, 136 285 Z"
                      fill="url(#beardGrad)"/>

                <!-- Mechones y volumen orgánico en los bordes de la barba -->
                <g stroke="#1b1917" stroke-width="2.2" fill="none" opacity="0.85">
                    <path d="M 134 305 Q 120 330 135 355"/>
                    <path d="M 140 345 Q 126 375 148 398"/>
                    <path d="M 155 388 Q 152 415 175 426"/>
                    <path d="M 185 422 Q 202 446 225 444"/>
                    <path d="M 326 305 Q 340 330 325 355"/>
                    <path d="M 320 345 Q 334 375 312 398"/>
                    <path d="M 305 388 Q 308 415 285 426"/>
                    <path d="M 275 422 Q 258 446 235 444"/>
                    <!-- Hebras plateadas/iluminadas sutiles en el mentón -->
                    <path d="M 205 365 Q 200 398 214 425" stroke="rgba(255,255,255,0.12)"/>
                    <path d="M 255 365 Q 260 398 246 425" stroke="rgba(255,255,255,0.12)"/>
                    <path d="M 230 358 Q 228 395 232 430" stroke="rgba(255,255,255,0.1)"/>
                </g>

                <!-- Zona de piel bajo el labio inferior con la "mosca" (soul patch) característica -->
                <ellipse cx="230" cy="316" rx="28" ry="18" fill="#bd8056"/>
                <!-- Mosca central bajo el labio -->
                <path d="M 220 312 Q 230 310 240 312 L 246 340 Q 230 345 214 340 Z" fill="#171513"/>

                <!-- BOCA DINÁMICA (Controlada por JS) -->
                <g id="mouth-container" transform="translate(230, 302)">
                    <!-- Labio inferior -->
                    <path id="lower-lip" d="M -24 2 Q 0 13 24 2 Q 0 8 -24 2 Z" fill="#b56e65"/>
                    <!-- Cavidad oral que se abre al hablar -->
                    <ellipse id="mouth-cavity" cx="0" cy="0" rx="18" ry="1" fill="#3a1216"/>
                    <!-- Lengua sutil en el fondo -->
                    <ellipse id="mouth-tongue" cx="0" cy="3" rx="10" ry="0.5" fill="#8f3942" opacity="0"/>
                </g>
            </g>

            <!-- 8. BIGOTE DENSO (Se arquea ligeramente sobre la boca) -->
            <path id="mustache"
                  d="M 230 278
                     C 202 276, 175 292, 162 316
                     C 178 312, 198 298, 230 297
                     C 262 298, 282 312, 298 316
                     C 285 292, 258 276, 230 278 Z"
                  fill="#181614"/>

            <!-- 9. GORRA NEGRA Y LENTES AZULES DEPORTIVOS EN LA FRENTE -->
            <g id="cap-and-glasses">
                <!-- Copa de la gorra -->
                <path d="M 132 162 C 132 72, 328 72, 328 162 Z" fill="url(#capGrad)"/>

                <!-- Lentes deportivos apoyados sobre la gorra -->
                <g id="sunglasses">
                    <!-- Montura deportiva negra -->
                    <path d="M 135 126
                             Q 178 90 226 112
                             L 234 112
                             Q 282 90 325 126
                             L 320 154
                             Q 275 162 235 132
                             L 225 132
                             Q 185 162 140 154 Z"
                          fill="#1c1f24" stroke="#2e333b" stroke-width="2.5"/>

                    <!-- Cristal Izquierdo Azul Reflectante -->
                    <path d="M 144 126 Q 182 96 222 115 L 220 132 Q 184 155 146 146 Z" fill="url(#blueLens)"/>
                    <path d="M 146 126 Q 180 100 214 115 L 185 142 L 148 140 Z" fill="url(#lensSheen)"/>

                    <!-- Cristal Derecho Azul Reflectante -->
                    <path d="M 316 126 Q 278 96 238 115 L 240 132 Q 276 155 314 146 Z" fill="url(#blueLens)"/>
                    <path d="M 244 115 Q 278 100 312 126 L 295 142 L 246 132 Z" fill="url(#lensSheen)"/>

                    <!-- Brillo reactivo de IA en los cristales azules cuando procesa o habla -->
                    <g id="glasses-glow">
                        <ellipse cx="184" cy="126" rx="28" ry="12" fill="#00f2fe" filter="blur(6px)"/>
                        <ellipse cx="276" cy="126" rx="28" ry="12" fill="#00f2fe" filter="blur(6px)"/>
                    </g>
                </g>

                <!-- Visera curva de la gorra (al frente, debajo de los lentes) -->
                <path d="M 126 168 Q 230 118 334 168 Q 338 182 325 180 Q 230 140 135 180 Q 122 182 126 168 Z"
                      fill="#1e2125" stroke="#2c3137" stroke-width="1.5"/>
            </g>

        </g>
    </svg>

    <!-- Indicador de ondas de voz -->
    <div class="voice-waves" id="voice-waves">
        <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
        <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
        <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
    </div>
</div>

<!-- HUD DE CONTROL -->
<div class="hud" id="hud">
    <div class="badge-status">
        <span class="dot"></span>
        <span id="status-label">Listo (Presiona Espacio)</span>
    </div>

    <select id="model-select" title="Modelo local de Ollama">
        <option value="gemma2">Cargando modelos...</option>
    </select>

    <button id="btn-ptt" class="primary" title="Mantén presionado o haz clic para hablar">
        🎙️ Hablar al Asistente
    </button>

    <button id="btn-continuous" title="Escucha continua activada por voz">
        👂 Escucha Continua: OFF
    </button>

    <input type="text" id="manual-input" placeholder="O escribe una orden rápida..." style="width: 210px;">
</div>

<script>
(() => {
    const root = document.documentElement;
    const body = document.body;
    const statusLabel = document.getElementById('status-label');
    const modelSelect = document.getElementById('model-select');
    const btnPtt = document.getElementById('btn-ptt');
    const btnContinuous = document.getElementById('btn-continuous');
    const manualInput = document.getElementById('manual-input');
    const hud = document.getElementById('hud');
    const avatarStage = document.getElementById('avatar-stage');
    const mouthCavity = document.getElementById('mouth-cavity');
    const mouthTongue = document.getElementById('mouth-tongue');
    const waveBars = document.querySelectorAll('.wave-bar');

    let currentState = 'idle'; // idle | listening | thinking | speaking
    let continuousMode = false;
    let isRecognizing = false;
    let speechAnimFrame = null;
    let syllableEnergy = 0;

    // =========================================================================
    // 1. CARGAR MODELOS INSTALADOS EN TU OLLAMA LOCAL
    // =========================================================================
    async function loadOllamaModels() {
        try {
            const res = await fetch('?api=models');
            const data = await res.json();
            if (data.models && data.models.length > 0) {
                modelSelect.innerHTML = '';
                data.models.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.name;
                    opt.textContent = m.name;
                    modelSelect.appendChild(opt);
                });
            } else {
                modelSelect.innerHTML = '<option value="gemma2">gemma2 (por defecto)</option>';
            }
        } catch (e) {
            modelSelect.innerHTML = '<option value="gemma2">Error conectando a Ollama</option>';
        }
    }
    loadOllamaModels();

    // =========================================================================
    // 2. SISTEMA DE ESTADOS Y EXPRESIONES FACIALES (CSS + SVG)
    // =========================================================================
    function setState(state, customText = '') {
        currentState = state;
        body.className = `state-${state}`;

        if (state === 'idle') {
            statusLabel.textContent = customText || 'Listo (Espacio para hablar)';
            root.style.setProperty('--brow-lift', '0px');
            root.style.setProperty('--lens-energy', '0.2');
            root.style.setProperty('--head-tilt', '0deg');
            setMouthShape(0);
        } else if (state === 'listening') {
            statusLabel.textContent = customText || 'Escuchándote...';
            root.style.setProperty('--brow-lift', '-4px');
            root.style.setProperty('--lens-energy', '0.65');
            root.style.setProperty('--head-tilt', '-1.5deg');
        } else if (state === 'thinking') {
            statusLabel.textContent = customText || 'Consultando a Ollama...';
            root.style.setProperty('--brow-lift', '-2px');
            root.style.setProperty('--eye-x', '3px');
            root.style.setProperty('--eye-y', '-3px');
            root.style.setProperty('--lens-energy', '0.95');
            root.style.setProperty('--head-tilt', '2deg');
        } else if (state === 'speaking') {
            statusLabel.textContent = customText || 'Explicando a los twos...';
            root.style.setProperty('--eye-x', '0px');
            root.style.setProperty('--eye-y', '0px');
            root.style.setProperty('--lens-energy', '0.8');
        }
    }

    // Ajusta la apertura de la boca, lengua y caída de la barba en tiempo real
    function setMouthShape(openAmount) {
        const clamped = Math.max(0, Math.min(1, openAmount));
        const ry = 1 + clamped * 11;
        const rx = 17 - clamped * 3;
        const jawPx = (clamped * 8.5).toFixed(2);

        mouthCavity.setAttribute('ry', ry.toFixed(2));
        mouthCavity.setAttribute('rx', rx.toFixed(2));
        mouthTongue.setAttribute('ry', (clamped * 4.5).toFixed(2));
        mouthTongue.setAttribute('opacity', clamped > 0.2 ? '0.85' : '0');
        root.style.setProperty('--jaw-drop', `${jawPx}px`);

        // Actualizar barras de onda de voz
        waveBars.forEach((bar) => {
            const h = clamped > 0.05 ? 5 + Math.random() * 22 * clamped : 5;
            bar.style.height = `${h}px`;
        });
    }

    // Parpadeo automático realista cada 2.5 - 5.5 segundos
    function scheduleBlink() {
        const nextBlink = 2200 + Math.random() * 3500;
        setTimeout(() => {
            avatarStage.classList.add('blink');
            setTimeout(() => avatarStage.classList.remove('blink'), 130);
            scheduleBlink();
        }, nextBlink);
    }
    scheduleBlink();

    // Seguimiento sutil de los ojos con el cursor cuando está en reposo
    window.addEventListener('mousemove', (e) => {
        if (currentState === 'thinking') return;
        const x = ((e.clientX / window.innerWidth) - 0.5) * 6;
        const y = ((e.clientY / window.innerHeight) - 0.5) * 4;
        root.style.setProperty('--eye-x', `${x.toFixed(2)}px`);
        root.style.setProperty('--eye-y', `${y.toFixed(2)}px`);
    });

    // =========================================================================
    // 3. SÍNTESIS DE VOZ INMEDIATA Y LIP-SYNC PROCEDURAL
    // =========================================================================
    function speakImmediately(text) {
        if (!('speechSynthesis' in window)) {
            alert('Tu navegador no soporta síntesis de voz nativa.');
            return;
        }

        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'es-MX';
        utter.rate = 1.04; // Ritmo ágil y natural de mentor
        utter.pitch = 0.95;

        // Seleccionar la mejor voz en español disponible en el sistema
        const voices = window.speechSynthesis.getVoices();
        const esVoice = voices.find(v => v.lang.startsWith('es') && (v.name.includes('Natural') || v.name.includes('Sabina') || v.name.includes('Raul') || v.name.includes('Google')))
                     || voices.find(v => v.lang.startsWith('es'));
        if (esVoice) utter.voice = esVoice;

        // Cada vez que pronuncia una palabra o sílaba, inyectamos energía a la boca y cejas
        utter.onboundary = () => {
            syllableEnergy = 0.75 + Math.random() * 0.25;
            const brow = (Math.random() * -4).toFixed(1);
            const tilt = ((Math.random() - 0.5) * 3.5).toFixed(1);
            root.style.setProperty('--brow-lift', `${brow}px`);
            root.style.setProperty('--head-tilt', `${tilt}deg`);
        };

        utter.onstart = () => {
            setState('speaking');
            syllableEnergy = 0.8;
            startLipSyncLoop();
        };

        utter.onend = () => {
            stopLipSyncLoop();
            setState('idle');
            if (continuousMode) startListening();
        };

        utter.onerror = () => {
            stopLipSyncLoop();
            setState('idle');
        };

        window.speechSynthesis.speak(utter);
    }

    function startLipSyncLoop() {
        cancelAnimationFrame(speechAnimFrame);
        let t = 0;
        const animateMouth = () => {
            if (currentState !== 'speaking') {
                setMouthShape(0);
                return;
            }
            t += 0.36;
            // Oscilación orgánica de sílabas mientras habla
            const wave = (Math.sin(t) * 0.5 + 0.5) * (Math.cos(t * 0.65) * 0.4 + 0.6);
            syllableEnergy = Math.max(0.35, syllableEnergy * 0.94);
            setMouthShape(wave * syllableEnergy);
            speechAnimFrame = requestAnimationFrame(animateMouth);
        };
        speechAnimFrame = requestAnimationFrame(animateMouth);
    }

    function stopLipSyncLoop() {
        cancelAnimationFrame(speechAnimFrame);
        setMouthShape(0);
    }

    // Asegurar carga de voces del navegador
    window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();

    // =========================================================================
    // 4. CONEXIÓN CON OLLAMA VÍA PHP
    // =========================================================================
    async function askAssistant(promptText) {
        if (!promptText.trim()) return;
        setState('thinking', `Pensando respuesta...`);

        try {
            const res = await fetch('?api=ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    prompt: promptText,
                    model: modelSelect.value
                })
            });
            const data = await res.json();
            if (data.ok && data.speech) {
                speakImmediately(data.speech);
            } else {
                speakImmediately('Ocurrió un detalle al conectar con el modelo local de Ollama.');
            }
        } catch (err) {
            speakImmediately('No pude comunicarme con el servidor local.');
        }
    }

    // =========================================================================
    // 5. RECONOCIMIENTO DE VOZ EN TIEMPO REAL (MICRÓFONO)
    // =========================================================================
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'es-MX';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            isRecognizing = true;
            setState('listening');
        };

        recognition.onresult = (event) => {
            const transcript = event.results[event.results.length - 1][0].transcript.trim();
            if (transcript) {
                askAssistant(transcript);
            }
        };

        recognition.onerror = () => {
            isRecognizing = false;
            if (currentState === 'listening') setState('idle');
        };

        recognition.onend = () => {
            isRecognizing = false;
            if (currentState === 'listening') setState('idle');
            if (continuousMode && currentState === 'idle') {
                try { recognition.start(); } catch(e) {}
            }
        };
    }

    function startListening() {
        if (!recognition) {
            alert('Usa un navegador compatible con Web Speech API (Chrome, Edge, Brave) para usar el micrófono.');
            return;
        }
        window.speechSynthesis.cancel();
        if (!isRecognizing) {
            try { recognition.start(); } catch (e) {}
        }
    }

    function stopListening() {
        if (recognition && isRecognizing) {
            recognition.stop();
        }
    }

    // Controles de botones y atajos de teclado
    btnPtt.addEventListener('click', () => {
        if (isRecognizing) stopListening();
        else startListening();
    });

    btnContinuous.addEventListener('click', () => {
        continuousMode = !continuousMode;
        btnContinuous.textContent = `👂 Escucha Continua: ${continuousMode ? 'ON' : 'OFF'}`;
        btnContinuous.classList.toggle('active-mode', continuousMode);
        if (continuousMode) startListening();
        else stopListening();
    });

    manualInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && manualInput.value.trim() !== '') {
            askAssistant(manualInput.value);
            manualInput.value = '';
        }
    });

    // Atajos rápidos para tus clases en vivo
    window.addEventListener('keydown', (e) => {
        if (document.activeElement === manualInput) return;

        // Mantener ESPACIO para hablarle
        if (e.code === 'Space' && !isRecognizing && currentState !== 'thinking') {
            e.preventDefault();
            startListening();
        }
        // Tecla H: Ocultar o mostrar el panel inferior para dejar limpio el escenario
        if (e.key.toLowerCase() === 'h') {
            hud.classList.toggle('hidden');
        }
        // ESC: Callar inmediatamente al asistente si quieres retomar la palabra tú
        if (e.key === 'Escape') {
            window.speechSynthesis.cancel();
            stopLipSyncLoop();
            setState('idle');
        }
    });

    window.addEventListener('keyup', (e) => {
        if (document.activeElement === manualInput) return;
        if (e.code === 'Space' && !continuousMode && isRecognizing) {
            e.preventDefault();
            stopListening();
        }
    });
})();
</script>
</body>
</html>