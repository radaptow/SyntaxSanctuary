# 🎙️ Whisper Interface — Speech-to-Text Bridge (Syntax Sanctuary)

Sistema web de reconocimiento automático del habla (**ASR / Voz a Texto**) compuesto por dos scripts PHP (`index.php` y `enviar_whisper.php`). Permite grabar audio directamente desde el micrófono del navegador mediante la API `MediaRecorder` (`audio/webm`), empaquetarlo en memoria y enviarlo a través de un puente seguro en PHP (`CURLFile`) hacia un servidor **Whisper** acelerado por GPU en Windows (conectado vía LAN o **Tailscale**)[cite: 12, 13].

---

## 📂 Arquitectura del Proyecto (2 Archivos)

| Archivo | Rol Principal | Descripción Técnica |
| :--- | :--- | :--- |
| **`index.php`** | Interfaz & Autenticación | Gestiona el inicio de sesión (`$_SESSION['logeado']`), renderiza la interfaz *Glassmorphism* con animaciones de estado del micrófono (`recording`, `processing`) y captura el audio del usuario mediante `navigator.mediaDevices.getUserMedia`. |
| **`enviar_whisper.php`** | Puente API (`CURLFile`) | Valida la sesión activa (retornando `HTTP 403` si no está autenticado), recibe el blob `voice.webm` vía `$_FILES['file']` y lo retransmite por `cURL` (`multipart/form-data`) hacia el endpoint `/v1/audio/transcriptions` con el modelo `medium` en español (`es`)[cite: 12, 13]. |

---

## ✨ Características Principales

* **Captura de Voz Nativa en el Navegador:**
  * Uso de `MediaRecorder` para grabar fragmentos de audio en formato `audio/webm` y liberarlos inmediatamente al detener la grabación (`track.stop()`) para no dejar el micrófono abierto en segundo plano.
  * Acumulación inteligente de transcripciones en el `<textarea>`, separando cada nueva intervención con saltos de línea dobles y auto-scroll automático[cite: 13].
* **Puente Multipart Sin Límites de Tiempo:**
  * Construcción nativa de objetos `CURLFile` a partir del archivo temporal de PHP (`$_FILES['file']['tmp_name']`) sin guardar grabaciones permanentes en el servidor web.
  * Configurado con `CURLOPT_TIMEOUT => 0` y `CURLOPT_CONNECTTIMEOUT => 0` para soportar audios largos mientras la GPU procesa la transcripción.
* **Seguridad de Sesión Sincronizada:**
  * Ambos archivos comparten la sesión de PHP (`$_SESSION['logeado'] === true`)[cite: 12, 13]. Si la sesión expira y `enviar_whisper.php` responde con `HTTP 403`, el cliente JavaScript recarga automáticamente la vista para solicitar credenciales (`if(response.status === 403) window.location.reload()`)[cite: 12, 13].
* **Feedback Visual Reactivo (Glassmorphism UI):**
  * Indicadores visuales mediante animaciones CSS (`@keyframes pulse-ring` en rojo durante la grabación y `@keyframes spin-pulse` en ámbar durante el procesamiento en GPU)[cite: 13].

---

## 🛠️ Requisitos del Sistema

* **Servidor Web (Frontend/Bridge):** Apache / Nginx con PHP 7.4 / 8.x o superior y la extensión `php-curl` habilitada.
* **Contexto Seguro (HTTPS o `localhost`):** Los navegadores modernos requieren `HTTPS` (o `http://localhost`) para habilitar el acceso a `navigator.mediaDevices.getUserMedia({ audio: true })`[cite: 13].
* **Servidor de IA (Backend Whisper):** Instancia compatible con la API de OpenAI Whisper (ej. `faster-whisper-server`, `whisper-asr-webservice` o contenedor Docker en Windows) escuchando en `http://100.115.1.71:8000/v1/audio/transcriptions`.

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/whisper-interface.git](https://github.com/tu-usuario/whisper-interface.git)
   cd whisper-interface