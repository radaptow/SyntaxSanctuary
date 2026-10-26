# 👁️ Ollama Vision Telemetry Monolith (Multimodal PHP Bridge)

Cliente y puente de inferencia multimodal contenido en un único archivo PHP (`strict_types=1`), diseñado para analizar imágenes y texto mediante la API de **Ollama** (`/api/generate`, con `stream = false`). Incluye inspección binaria de imágenes en el backend (`getimagesizefromstring`), un generador integrado de láminas de prueba en HTML5 Canvas (`VISION TEST CARD`) y telemetría detallada del *encoder* visual y la velocidad de generación.

---

## ✨ Características Principales

* **Selector con Lista Blanca de 4 Modelos de Visión:**
  * Validación estricta en servidor (`in_array($model, $visionModels, true)`) con *fallback* automático hacia `moondream:latest`.
  * Modelos soportados de fábrica:
    * `moondream:latest`
    * `virtuanista/lfm2.5-8b-a1b-uncensored:q8_0`
    * `llava-phi3:latest`
    * `llama3.2-vision:latest`
* **Inspección y Telemetría de Imagen en Backend:**
  * Limpieza automática de cabeceras Data URL (`data:image/...;base64,`), decodificación estricta (`base64_decode(..., true)`) y validación de tamaño mínimo (`>= 32 bytes`).
  * Extracción de metadatos en memoria sin guardar archivos en disco usando `getimagesizefromstring()`: ancho (`width`), alto (`height`), tipo `mime`, peso en bytes (`size_bytes`) y cálculo exacto de **megapíxeles**[cite: 11].
* **Generador de Lámina de Prueba (`VISION TEST CARD`):**
  * Crea automáticamente al iniciar (o al pulsar el botón **"Lámina Test"**) una imagen sintética de `500x300 px` en un `<canvas>` con texto (`VISION TEST CARD`, `STATUS: OPERATIONAL`) y figuras geométricas de colores para verificar el reconocimiento óptico de caracteres (OCR) y detección de objetos sin necesidad de subir archivos externos[cite: 11].
* **Telemetría de Rendimiento Multimodal:**
  * Medición de velocidad de generación en tokens por segundo (`t/s`), tiempo de procesamiento del **Encoder Visual** (`prompt_eval_duration`), duración total de inferencia (`total_duration`) y latencia del puente PHP en milisegundos con precisión de nanosegundos (`hrtime(true)`)[cite: 11].

---

## 🛠️ Requisitos del Sistema

* **PHP:** 8.0 o superior (utiliza `str_contains()`, `hrtime()` y `declare(strict_types=1)`)[cite: 11].
* **Extensiones PHP:** `curl`, `gd` (para `getimagesizefromstring()`) y `json`[cite: 11].
* **Servidor Ollama:** Instancia activa en el puerto `11434` (local o vía **Tailscale** en `100.115.1.71`) con los modelos de visión descargados[cite: 11].

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/ollama-vision-monolith.git](https://github.com/tu-usuario/ollama-vision-monolith.git)
   cd ollama-vision-monolith