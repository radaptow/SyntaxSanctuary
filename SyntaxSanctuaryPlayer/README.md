# 🎬 Syntax Sanctuary — Media Center (Monolito PHP)

Plataforma de catálogo y reproducción de vídeo autoalojada, contenida en un único archivo PHP (`strict_types=1`). Combina un reproductor híbrido capaz de alternar dinámicamente entre **vídeos locales (`HTML5 <video>`)** y **transmisiones embebidas de YouTube (`<iframe>`, incluyendo Shorts y Lives)**, junto con un panel de administración protegido por sesión y tokens CSRF sin depender de bases de datos SQL.

---

## ✨ Características Principales

* **Reproductor Híbrido Inteligente:**
  * **Modo YouTube:** Extracción automática del ID de 11 caracteres mediante expresiones regulares compatibles con enlaces estándar (`watch?v=`), cortos (`youtu.be/`), `embed/`, **YouTube Shorts** (`shorts/`) y **YouTube Live** (`live/`).
  * **Modo Local:** Soporte para subida y streaming directo de archivos `.mp4`, `.webm`, `.ogv` y `.mov` de hasta **1 GB** con barra de progreso en tiempo real (`XMLHttpRequest` upload progress).
* **Persistencia JSON Atómica (`videos_data.json`):**
  * Escritura segura a través de archivos temporales aleatorios (`.tmp.<hex>`) con bloqueo exclusivo (`LOCK_EX`) y reemplazo atómico (`rename()`) para evitar cualquier corrupción del catálogo ante cortes o lecturas concurrentes.
* **Seguridad Multicapa:**
  * **Protección CSRF:** Generación criptográfica de tokens (`random_bytes(32)`) y validación en tiempo constante mediante `hash_equals()` en todas las operaciones mutables (`upload_video`, `delete_video`, `logout`).
  * **Inspección MIME Real:** Validación estricta del binario subido utilizando la extensión nativa `finfo(FILEINFO_MIME_TYPE)` y renombrado seguro en disco (`bin2hex(random_bytes(16))`).
  * **Cabeceras HTTP de Seguridad:** Inclusión nativa de `X-Content-Type-Options: nosniff` y `X-Frame-Options: SAMEORIGIN`, además de sanitización XSS con `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
* **Autogestión de Almacenamiento:** Creación automática del directorio `uploads_videos/` (`0755`) y del archivo `videos_data.json` en la primera ejecución, así como borrado físico automático (`unlink`) de archivos locales al eliminarlos desde la interfaz.

---

## 🛠️ Requisitos del Sistema

* **PHP:** 8.0 o superior (requerido por el tipo de retorno `: never`, funciones como `str_starts_with()` y `declare(strict_types=1)`).
* **Extensiones PHP:** `fileinfo` (para inspección MIME) y `json`.
* **Permisos de Directorio:** Permisos de escritura para el usuario del servidor web en el directorio raíz del proyecto.

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/syntax-media-center.git](https://github.com/tu-usuario/syntax-media-center.git)
   cd syntax-media-center