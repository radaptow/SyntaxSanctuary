# ⚡ SD // MONOLITH (PHP → Stable Diffusion API)

Cliente web monolítico en un único archivo PHP (`strict_types=1`) diseñado para interactuar directamente con el endpoint `txt2img` de **Stable Diffusion WebUI (AUTOMATIC1111)**. Incluye autenticación por contraseña con sesiones seguras, telemetría de tiempos de generación y una interfaz ciberpunk responsiva sin dependencias externas.

---

## ✨ Características Principales

* **Arquitectura Monolítica con Tipado Estricto:** Todo el flujo de autenticación, cliente HTTP (`cURL`), manejo de excepciones y renderizado HTML5/CSS3 reside en un solo script con `declare(strict_types=1)`.
* **Seguridad Integrada:**
  * Autenticación basada en hash bcrypt (`password_verify`) con soporte para configuración mediante la variable de entorno `APP_PASSWORD_HASH`.
  * Prevención de fijación de sesión mediante `session_regenerate_id(true)` bajo la sesión personalizada `SDMONOLITHSESS`.
  * Sanitización total de salidas en el DOM con `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` y ocultamiento de errores en producción (`display_errors = 0`).
* **Comunicación Directa vía cURL (`txt2img`):**
  * Petición `POST` JSON nativa hacia `/sdapi/v1/txt2img` sin límite de espera (`CURLOPT_TIMEOUT => 0`) para soportar generaciones pesadas en GPU local o remota.
  * Renderizado instantáneo en el navegador mediante `data:image/png;base64` sin necesidad de escribir archivos temporales en disco[cite: 3].
* **Telemetría y Validación:**
  * Medición exacta del tiempo de inferencia en segundos (`microtime(true)`) y código de estado HTTP[cite: 3].
  * Restricción automática de parámetros: longitud máxima de `prompt` (4000 caracteres) y rango seguro de `steps` acotado entre `1` y `100`[cite: 3].

---

## 🛠️ Requisitos del Sistema

* **PHP:** 7.4 / 8.x o superior (con la extensión `php-curl` y `php-json` habilitadas)[cite: 3].
* **Backend de IA:** Instancia activa de Stable Diffusion WebUI (AUTOMATIC1111 o Forge) ejecutándose con la bandera `--api` habilitada[cite: 3].

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/sd-monolith.git](https://github.com/tu-usuario/sd-monolith.git)
   cd sd-monolith