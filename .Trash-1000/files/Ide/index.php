<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Syntax Sanctuary - AI IDE</title>
<style>
  :root {
    --bg-dark: #0d1117;
    --bg-panel: #161b22;
    --bg-hover: #21262d;
    --border: #30363d;
    --accent: #2f81f7;
    --accent-green: #238636;
    --accent-rec: #da3633;
    --text: #e6edf3;
    --muted: #8b949e;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: system-ui, -apple-system, sans-serif;
    background: var(--bg-dark);
    color: var(--text);
    height: 100dvh;
    width: 100vw;
    overflow: hidden;
    display: grid;
    grid-template-rows: 40px 36px 1fr;
  }
  @media (max-width: 900px) {
    body { grid-template-rows: 40px 36px 1fr 48px; }
  }

  /* 1. Barra Superior: Selector de Modo y Modelo Ollama */
  .topbar {
    background: var(--bg-panel);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 10px;
    gap: 8px;
    overflow-x: auto;
  }
  .brand { font-weight: 700; font-size: 0.85rem; white-space: nowrap; color: #58a6ff; }
  .top-selects { display: flex; gap: 6px; align-items: center; }
  select, .btn-sm {
    background: var(--bg-dark);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 5px;
    padding: 4px 8px;
    font-size: 0.75rem;
    cursor: pointer;
  }
  .btn-sm.save { background: var(--accent-green); border-color: transparent; font-weight: 600; }

  /* 2. Barra PATH interactiva estilo VS Code (Breadcrumbs) */
  .path-bar {
    background: #12161f;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 10px;
    font-family: monospace;
    font-size: 0.8rem;
    overflow-x: auto;
    white-space: nowrap;
  }
  .crumbs { display: flex; align-items: center; gap: 4px; }
  .crumb {
    padding: 2px 6px;
    border-radius: 4px;
    cursor: pointer;
    color: var(--muted);
  }
  .crumb:hover, .crumb.active { background: var(--bg-hover); color: var(--text); }
  .path-actions { display: flex; gap: 6px; }

  /* 3. Área Principal (3 Paneles en PC / Conmutable en Móvil) */
  main {
    display: grid;
    grid-template-columns: 230px 1fr 350px;
    height: 100%;
    overflow: hidden;
  }
  .panel { height: 100%; overflow: hidden; display: flex; flex-direction: column; }

  /* File Manager */
  .file-manager {
    background: var(--bg-panel);
    border-right: 1px solid var(--border);
  }
  .fm-header {
    padding: 8px 10px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--muted);
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
  }
  .tree-list { flex: 1; overflow-y: auto; padding: 6px; font-size: 0.83rem; }
  .tree-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 8px;
    border-radius: 5px;
    cursor: pointer;
    user-select: none;
  }
  .tree-item:hover { background: var(--bg-hover); }
  .tree-item.selected { background: #1f3251; color: #79c0ff; }
  .del-btn { opacity: 0.5; font-size: 0.7rem; padding: 2px 5px; }
  .del-btn:hover { opacity: 1; color: #ff7b72; }

  /* Editor Central + Vista Previa */
  .center-stage { background: var(--bg-dark); position: relative; }
  #codeEditor {
    width: 100%;
    height: 100%;
    background: #0d1117;
    color: #e6edf3;
    border: none;
    padding: 14px;
    font-family: 'Fira Code', 'Consolas', monospace;
    font-size: 0.9rem;
    line-height: 1.5;
    resize: none;
    outline: none;
    tab-size: 2;
  }
  #previewFrame {
    width: 100%;
    height: 100%;
    border: none;
    background: white;
    display: none;
  }

  /* Panel del Agente IA */
  .agent-panel {
    background: var(--bg-panel);
    border-left: 1px solid var(--border);
    padding: 10px;
    gap: 8px;
  }
  .log-box {
    flex: 1;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 0.84rem;
  }
  .msg { padding: 8px 10px; border-radius: 6px; line-height: 1.4; word-wrap: break-word; }
  .msg.user { background: #1f3a60; align-self: flex-end; max-width: 88%; }
  .msg.agent { background: #1c2129; border: 1px solid var(--border); align-self: flex-start; max-width: 94%; }
  .msg.action-badge {
    background: #122d1f;
    border: 1px solid #2ea043;
    color: #7ee787;
    font-family: monospace;
    font-size: 0.76rem;
  }
  .msg.error { background: #3d1717; border: 1px solid #f85149; color: #ffa198; }

  .agent-input {
    width: 100%;
    height: 62px;
    background: var(--bg-dark);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px;
    resize: none;
    font-size: 0.85rem;
  }
  .controls { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
  .btn-main {
    padding: 9px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    color: white;
    background: var(--accent);
  }
  .btn-mic { background: #30363d; }
  .btn-mic.recording { background: var(--accent-rec); animation: pulse 1.1s infinite; }
  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }

  /* Navegación Inferior para Móvil */
  .mobile-nav {
    display: none;
    background: var(--bg-panel);
    border-top: 1px solid var(--border);
    grid-template-columns: repeat(4, 1fr);
  }
  .mobile-nav button {
    background: none;
    border: none;
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
  }
  .mobile-nav button.active { color: #58a6ff; border-top: 2px solid #58a6ff; }

  @media (max-width: 900px) {
    main { grid-template-columns: 1fr; }
    .mobile-nav { display: grid; }
    .panel { display: none; }
    .panel.mobile-active { display: flex; }
  }
</style>
</head>
<body>

<!-- 1. BARRA SUPERIOR -->
<header class="topbar">
  <div class="brand">⚡ SyntaxSanctuary IDE</div>
  <div class="top-selects">
    <select id="modeSelect" title="Modo de estudio">
      <option value="php_project">🐘 Proyecto PHP / Full-Stack</option>
      <option value="web_builder">🌐 Constructor Web</option>
      <option value="lang_creator">🧬 Crear Lenguaje</option>
    </select>
    <select id="modelSelect" title="Modelo en Ollama :11434">
      <option value="qwen2.5-coder:latest">Cargando Ollama...</option>
    </select>
    <button class="btn-sm save" onclick="guardarArchivoActual()">💾 Guardar</button>
  </div>
</header>

<!-- 2. BARRA PATH ESTILO VS CODE -->
<nav class="path-bar">
  <div class="crumbs" id="breadcrumbs">
    <span class="crumb active" onclick="seleccionarCarpeta('')">workspace</span>
  </div>
  <div class="path-actions">
    <button class="btn-sm" onclick="nuevoArchivoPrompt()">+ Archivo</button>
    <button class="btn-sm" onclick="nuevaCarpetaPrompt()">+ Carpeta</button>
  </div>
</nav>

<!-- 3. WORKSPACE PRINCIPAL -->
<main>
  <!-- Panel Izquierdo: File Manager -->
  <section class="panel file-manager" id="panelFiles">
    <div class="fm-header">
      <span>EXPLORADOR DE ARCHIVOS</span>
      <span style="cursor:pointer;" onclick="cargarArbol()">🔄</span>
    </div>
    <div class="tree-list" id="treeList"></div>
  </section>

  <!-- Panel Central: Editor de Código y Vista Previa -->
  <section class="panel center-stage" id="panelEditor">
    <textarea id="codeEditor" spellcheck="false" placeholder="// Selecciona o crea un archivo, o pídele al Agente por voz que inicie un proyecto paso a paso..."></textarea>
    <iframe id="previewFrame"></iframe>
  </section>

  <!-- Panel Derecho: Agente Multimodal -->
  <aside class="panel agent-panel mobile-active" id="panelAgent">
    <div class="log-box" id="logBox">
      <div class="msg agent">
        <b>Agente conectado a Whisper (:8000), Ollama (:11434) y TTS (:5050).</b><br>
        Dime por voz o texto qué proyecto PHP, página web o lenguaje de programación quieres iniciar y avanzaremos paso a paso.
      </div>
    </div>

    <textarea id="promptInput" class="agent-input" placeholder="Ej: Oye, quiero iniciar un nuevo proyecto PHP modular, ¿por dónde empezamos?"></textarea>

    <div class="controls">
      <button class="btn-main" onclick="enviarAlAgente()">🚀 Enviar al Agente</button>
      <button class="btn-main btn-mic" id="btnMic" onclick="toggleGrabacion()">🎤 Hablar (:8000)</button>
    </div>

    <audio id="audioPlayer" style="width:100%; height:28px;" controls></audio>
  </aside>
</main>

<!-- 4. BARRA DE NAVEGACIÓN MÓVIL -->
<footer class="mobile-nav">
  <button id="tabFiles" onclick="cambiarVistaMovil('files')">📁 Archivos</button>
  <button id="tabEditor" onclick="cambiarVistaMovil('editor')">💻 Código</button>
  <button id="tabPreview" onclick="cambiarVistaMovil('preview')">🌐 Preview</button>
  <button id="tabAgent" class="active" onclick="cambiarVistaMovil('agent')">🤖 Agente IA</button>
</footer>

<script>
let currentPath = '';
let currentFolder = '';
let chatHistory = [];
const codeEditor = document.getElementById('codeEditor');
const previewFrame = document.getElementById('previewFrame');
const logBox = document.getElementById('logBox');

// ==========================================
// NAVEGACIÓN RESPONSIVA (MÓVIL + ESCRITORIO)
// ==========================================
function cambiarVistaMovil(vista) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('mobile-active'));
  document.querySelectorAll('.mobile-nav button').forEach(b => b.classList.remove('active'));

  if (vista === 'files') {
    document.getElementById('panelFiles').classList.add('mobile-active');
    document.getElementById('tabFiles').classList.add('active');
  } else if (vista === 'editor') {
    document.getElementById('panelEditor').classList.add('mobile-active');
    document.getElementById('tabEditor').classList.add('active');
    previewFrame.style.display = 'none';
    codeEditor.style.display = 'block';
  } else if (vista === 'preview') {
    document.getElementById('panelEditor').classList.add('mobile-active');
    document.getElementById('tabPreview').classList.add('active');
    codeEditor.style.display = 'none';
    previewFrame.style.display = 'block';
    actualizarPreview();
  } else {
    document.getElementById('panelAgent').classList.add('mobile-active');
    document.getElementById('tabAgent').classList.add('active');
  }
}

function actualizarPreview() {
  if (!currentPath) {
    previewFrame.srcdoc = '<h3 style="font-family:sans-serif;padding:20px;">Abre un archivo HTML o PHP para previsualizar</h3>';
    return;
  }
  if (currentPath.endsWith('.html') || currentPath.endsWith('.htm')) {
    previewFrame.srcdoc = codeEditor.value;
  } else {
    previewFrame.src = 'workspace/' + currentPath + '?t=' + Date.now();
  }
}

// ==========================================
// BARRA PATH (BREADCRUMBS DE VS CODE)
// ==========================================
function renderizarPath(ruta) {
  const bc = document.getElementById('breadcrumbs');
  bc.innerHTML = `<span class="crumb" onclick="seleccionarCarpeta('')">workspace</span>`;
  if (!ruta) return;

  const partes = ruta.split('/');
  let acumulado = '';
  partes.forEach((parte, idx) => {
    acumulado += (idx === 0 ? '' : '/') + parte;
    const esUltimo = idx === partes.length - 1;
    const rutaClick = acumulado;
    bc.innerHTML += `<span>/</span><span class="crumb ${esUltimo ? 'active' : ''}" onclick="clickCrumb('${rutaClick}', ${esUltimo})">${parte}</span>`;
  });
}

function clickCrumb(ruta, esUltimo) {
  if (!esUltimo) {
    seleccionarCarpeta(ruta);
  }
}

function seleccionarCarpeta(carpeta) {
  currentFolder = carpeta;
  renderizarPath(carpeta);
}

// ==========================================
// FILE MANAGER (LISTAR, LEER, GUARDAR, CREAR)
// ==========================================
async function cargarArbol() {
  const res = await fetch('fs_and_agent.php?action=fs_list');
  const data = await res.json();
  if (data.ok) dibujarArbol(data.tree);
}

function dibujarArbol(nodos, contenedor = document.getElementById('treeList'), nivel = 0) {
  if (nivel === 0) contenedor.innerHTML = '';
  nodos.forEach(item => {
    const fila = document.createElement('div');
    fila.className = 'tree-item' + (item.path === currentPath ? ' selected' : '');
    fila.style.paddingLeft = (8 + nivel * 14) + 'px';

    const icono = item.type === 'dir' ? '📁' : '📄';
    fila.innerHTML = `
      <span onclick="clickItemArbol('${item.path}', '${item.type}')" style="flex:1;overflow:hidden;text-overflow:ellipsis;">${icono} ${item.name}</span>
      <span class="del-btn" onclick="eliminarItem(event, '${item.path}')">✕</span>
    `;
    contenedor.appendChild(fila);

    if (item.type === 'dir' && item.children && item.children.length > 0) {
      dibujarArbol(item.children, contenedor, nivel + 1);
    }
  });
}

async function clickItemArbol(path, type) {
  if (type === 'dir') {
    currentFolder = path;
    renderizarPath(path);
  } else {
    await abrirArchivo(path);
    if (window.innerWidth <= 900) cambiarVistaMovil('editor');
  }
}

async function abrirArchivo(path) {
  const res = await fetch('fs_and_agent.php?action=fs_read', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path })
  });
  const data = await res.json();
  if (data.ok) {
    currentPath = data.path;
    const partes = currentPath.split('/');
    partes.pop();
    currentFolder = partes.join('/');
    codeEditor.value = data.content;
    renderizarPath(currentPath);
    cargarArbol();
  }
}

async function guardarArchivoActual() {
  if (!currentPath) {
    const nombre = prompt('Nombre del archivo a guardar (ej. index.php):');
    if (!nombre) return;
    currentPath = currentFolder ? `${currentFolder}/${nombre}` : nombre;
  }
  const res = await fetch('fs_and_agent.php?action=fs_write', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path: currentPath, content: codeEditor.value })
  });
  const data = await res.json();
  if (data.ok) {
    agregarLog(`💾 Guardado: ${currentPath}`, 'action-badge');
    renderizarPath(currentPath);
    cargarArbol();
  }
}

async function nuevoArchivoPrompt() {
  const base = currentFolder ? currentFolder + '/' : '';
  const ruta = prompt('Ruta del nuevo archivo:', base + 'nuevo.php');
  if (!ruta) return;
  await fetch('fs_and_agent.php?action=fs_write', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path: ruta, content: '<' + '?php\n\n' })
  });
  await abrirArchivo(ruta);
}


async function nuevaCarpetaPrompt() {
  const base = currentFolder ? currentFolder + '/' : '';
  const ruta = prompt('Nombre de la nueva carpeta:', base + 'nueva_carpeta');
  if (!ruta) return;
  await fetch('fs_and_agent.php?action=fs_mkdir', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path: ruta })
  });
  currentFolder = ruta;
  renderizarPath(ruta);
  cargarArbol();
}

async function eliminarItem(e, path) {
  e.stopPropagation();
  if (!confirm(`¿Eliminar ${path}?`)) return;
  await fetch('fs_and_agent.php?action=fs_delete', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path })
  });
  if (currentPath === path) {
    currentPath = '';
    codeEditor.value = '';
  }
  cargarArbol();
}

// ==========================================
// CONEXIÓN CON OLLAMA (:11434), WHISPER (:8000) Y TTS (:5050)
// ==========================================
function agregarLog(texto, tipo = 'agent') {
  const div = document.createElement('div');
  div.className = `msg ${tipo}`;
  div.textContent = texto;
  logBox.appendChild(div);
  logBox.scrollTop = logBox.scrollHeight;
}

async function cargarModelosOllama() {
  try {
    const res = await fetch('fs_and_agent.php?action=models');
    const data = await res.json();
    const sel = document.getElementById('modelSelect');
    if (data.models && data.models.length > 0) {
      sel.innerHTML = data.models.map(m => `<option value="${m}">${m}</option>`).join('');
    } else {
      sel.innerHTML = `<option value="qwen2.5-coder:latest">qwen2.5-coder:latest</option>`;
    }
  } catch (e) {}
}

async function enviarAlAgente(textoOpcional = null) {
  const inputEl = document.getElementById('promptInput');
  const prompt = (textoOpcional || inputEl.value).trim();
  if (!prompt) return;

  inputEl.value = '';
  agregarLog(prompt, 'user');

  const payload = {
    prompt,
    mode: document.getElementById('modeSelect').value,
    model: document.getElementById('modelSelect').value,
    current_path: currentPath,
    current_code: codeEditor.value,
    history: chatHistory
  };

  try {
    const res = await fetch('fs_and_agent.php?action=agent', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (!res.ok || data.error) {
      throw new Error(data.detalle || data.error || `HTTP ${res.status}`);
    }

    // Mostrar acciones que el agente ejecutó sobre el File Manager
    if (data.acciones_ejecutadas && data.acciones_ejecutadas.length > 0) {
      data.acciones_ejecutadas.forEach(acc => agregarLog(acc, 'action-badge'));
    }

    if (data.tree) dibujarArbol(data.tree);
    if (data.open_file) await abrirArchivo(data.open_file);

    agregarLog(data.detalle_chat || data.voz_respuesta, 'agent');

    chatHistory.push({ role: 'user', content: prompt });
    chatHistory.push({ role: 'assistant', content: data.detalle_chat || data.voz_respuesta });

    // Hablar la respuesta usando tu audio_bridge.php (:5050/tts)
    if (data.voz_respuesta) {
      await hablarTTS(data.voz_respuesta);
    }
  } catch (err) {
    agregarLog(`Error Agente: ${err.message}`, 'error');
  }
}

async function hablarTTS(texto) {
  try {
    const res = await fetch('audio_bridge.php?action=tts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ texto })
    });
    if (!res.ok) return;
    const blob = await res.blob();
    const player = document.getElementById('audioPlayer');
    player.src = URL.createObjectURL(blob);
    await player.play();
  } catch (e) {}
}

// ==========================================
// GRABACIÓN DE VOZ -> WHISPER (:8000) -> AGENTE (:11434)
// ==========================================
let mediaRecorder = null;
let audioChunks = [];
let grabando = false;

async function toggleGrabacion() {
  const btnMic = document.getElementById('btnMic');
  if (!grabando) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaRecorder = new MediaRecorder(stream);
      audioChunks = [];
      mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };
      mediaRecorder.onstop = async () => {
        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
        stream.getTracks().forEach(t => t.stop());
        await procesarVozConWhisper(audioBlob);
      };
      mediaRecorder.start();
      grabando = true;
      btnMic.classList.add('recording');
      btnMic.textContent = '⏹️ Enviar Voz';
    } catch (err) {
      agregarLog(`Error micrófono: ${err.message}`, 'error');
    }
  } else {
    mediaRecorder.stop();
    grabando = false;
    btnMic.classList.remove('recording');
    btnMic.textContent = '🎤 Hablar (:8000)';
  }
}

async function procesarVozConWhisper(audioBlob) {
  const formData = new FormData();
  formData.append('audio', audioBlob, 'voz.webm');
  try {
    const res = await fetch('audio_bridge.php?action=stt', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.ok && data.text) {
      await enviarAlAgente(data.text);
    } else {
      agregarLog('Whisper no devolvió texto.', 'error');
    }
  } catch (err) {
    agregarLog(`Error Whisper: ${err.message}`, 'error');
  }
}

// Inicializar al abrir
cargarArbol();
cargarModelosOllama();
</script>
</body>
</html>
