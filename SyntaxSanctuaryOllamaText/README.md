# 🧠 Ollama Text-Only Streaming Monolith (PHP NDJSON Bridge)

Cliente y puente de inferencia en tiempo real contenido en un único archivo PHP (`strict_types=1`), diseñado para comunicarse en modo streaming (`stream = true`) con la API local o remota de **Ollama** (`/api/generate`). Implementa transmisión continua de bloques **NDJSON (`application/x-ndjson`)** mediante `CURLOPT_WRITEFUNCTION` y un panel de telemetría de rendimiento con resolución de nanosegundos en el servidor y milisegundos en el navegador.

---

## ✨ Características Principales

* **Puente de Streaming en Vivo (`application/x-ndjson`):**
  * Desactivación total del *output buffering* de PHP y compresión (`zlib.output_compression = 0`, `X-Accel-Buffering: no`) para reenviar cada fragmento de texto al navegador en el instante en que Ollama lo genera.
  * Lectura progresiva en el cliente mediante `ReadableStream` (`res.body.getReader()`) y `TextDecoder` sin esperar a que concluya la generación completa.
* **Selector con Lista Blanca de 10 Modelos:**
  * Validación estricta en backend (`in_array($model, $textModels, true)`) con *fallback* automático hacia `qwen2.5-coder:0.5b` por seguridad.
  * Modelos preconfigurados: `qwen3-coder:30b`, `nemotron-3.5-bolt:latest`, `muse-glimmer:latest`, `qwen3.8:27b`, `bonsai-2:27b`, `bonsai-2:4b`, `qwen2.5-coder:0.5b`, `gemma2:9b`, `aya:8b` y `qwen2.5-coder:7b`.
* **Telemetría de Inferencia Dual (Backend + Frontend):**
  * **Métricas en Vivo (UI):** Velocidad de generación en tokens por segundo (`t/s`), tiempo hasta el primer token (`TTFT`), duración total en milisegundos y conteo total de tokens (`prompt_eval_count + eval_count`).
  * **Métricas de Puente PHP (`hrtime`):** Cálculo de latencia de red e inferencia desde el servidor PHP utilizando relojes monotónicos de alta resolución (`hrtime(true)`), inyectando un paquete final `bridge_summary` con `php_bridge_ms` y `php_ttft_ms`.
* **Control de Creatividad:** Ajuste dinámico de `temperature` (`0.0` a `1.0`, con valor predeterminado de `0.2` ideal para generación de código) directamente desde la interfaz.

---

## 🛠️ Requisitos del Sistema

* **PHP:** 7.4 / 8.x o superior (con las extensiones `curl` y `json` habilitadas, y soporte para `hrtime()`).
* **Servidor Ollama:** Instancia de Ollama activa y accesible en el puerto `11434` (local o a través de red/Tailscale) con los modelos deseados previamente descargados (`ollama pull <modelo>`).

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/ollama-streaming-monolith.git](https://github.com/tu-usuario/ollama-streaming-monolith.git)
   cd ollama-streaming-monolith