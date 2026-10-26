# 🎙️ Sintetizador de Voz IA | TTS Monolith (Syntax Sanctuary)

Cliente web y proxy inverso monolítico en un único archivo PHP (`strict_types=1`) diseñado para interactuar con un microservicio de síntesis de voz (**Text-to-Speech / Coqui TTS**) alojado en un contenedor Docker sobre Windows (accesible vía red local o **Tailscale**). Recibe la cadena de texto desde el navegador, consulta el endpoint remoto sin límites de *timeout* y retransmite el flujo binario `audio/wav` directamente al reproductor HTML5.

---

## ✨ Características Principales

* **Puente API Binario (`AJAX -> PHP -> Docker Windows -> PHP -> AJAX`):**
  * Actúa como intermediario seguro entre el cliente web y el contenedor de IA (`http://<IP>:5050/tts`), evitando problemas de CORS o exposición directa del puerto del motor en Python.
  * Transfiere el audio sintetizado en formato `audio/wav` puro y lo reproduce al instante en el navegador mediante objetos `Blob` y `URL.createObjectURL()` sin escribir archivos temporales en el servidor web.
* **Tolerancia a Inferencias Largas:**
  * Configuración cURL con `CURLOPT_TIMEOUT => 0` y `CURLOPT_CONNECTTIMEOUT => 0` para permitir la síntesis de textos extensos sin que PHP corte la conexión prematuramente.
* **Autenticación Integrada:**
  * Pantalla de bloqueo protegida mediante clave maestra (`ADMIN_PASSWORD`) y persistencia en `$_SESSION['tts_authenticated']`, incluyendo cierre de sesión limpio (`?logout=1`).
* **Interfaz Minimalista Dark/Neón:**
  * Diseño responsivo centrado con la paleta visual de **Syntax Sanctuary** (`#09090b` / `#00f0ff`), estados de carga interactivos y reproductor `<audio>` nativo.

---

## 🛠️ Requisitos del Sistema

* **Servidor Web (Frontend/Proxy):** PHP 7.4 / 8.x o superior con las extensiones `curl` y `json` habilitadas.
* **Servidor de IA (Backend TTS):** Contenedor Docker ejecutándose en Windows (o Linux) exponiendo el endpoint `POST /tts` en el puerto `5050` (compatible con payloads `{"texto": "..."}` y retorno en formato WAV)[cite: 10].
* **Conectividad de Red:** Alcance entre el servidor PHP y el host de Docker mediante LAN o red mesh **Tailscale** (ej. `100.x.x.x`)[cite: 10].

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/tts-monolith.git](https://github.com/tu-usuario/tts-monolith.git)
   cd tts-monolith