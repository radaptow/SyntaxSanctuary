# # 💬 Chat Monolítico en Tiempo Real (SSE + Fetch)

Un MVP (Producto Mínimo Viable) de mensajería instantánea contenido en un único archivo (`chat_monolitico.php`) diseñado de forma nativa para servidores Apache y PHP sin necesidad de motores de bases de datos externos ni servidores de WebSockets adicionales.

---

## ✨ Características Principales

* **Arquitectura Monolítica (Single-File):** Backend (API de envío y streaming), persistencia de datos y Frontend (HTML5, CSS3, JS) unificados en un solo script PHP.
* **Tiempo Real vía Server-Sent Events (SSE):** Transmisión unidireccional continua desde el servidor al cliente (`text/event-stream`) con liberación automática de procesos de Apache cada 15 segundos y latido (*heartbeat*) de 500ms para cuidar el uso de CPU.
* **Base de Datos Plana Autogestionada:** Almacenamiento en formato JSON (`chat_db.json`) con bloqueo exclusivo de archivos (`flock` con `LOCK_EX`) para prevenir condiciones de carrera en escrituras concurrentes y rotación automática (límite de 60 mensajes en historial).
* **Notificaciones Push en Dos Niveles:**
  * **Alertas vía Telegram Bot API:** Envío asíncrono con timeout de 2 segundos cuando un usuario externo (distinto a `ss-tfh`) envía un mensaje.
  * **Web Notifications API:** Notificaciones nativas del sistema operativo/navegador cuando la pestaña se encuentra en segundo plano (`document.hidden`).
* **Protección de Sesión:** Validación estricta mediante variables de `$_SESSION` (`acceso_autorizado`, `usuario_id`, `hash_verificado`) con redirección automática al módulo de verificación.
* **UI Minimalista & Responsiva:** Diseño oscuro (*Dark Mode*) adaptable a dispositivos móviles y escritorio, con indicador de estado de conexión en vivo y prevención de mensajes duplicados en el DOM.

---

## 🛠️ Requisitos del Sistema

* **Servidor Web:** Apache (con soporte para ajuste de variables de entorno como `no-gzip`).
* **PHP:** 7.4 o superior (con `allow_url_fopen` habilitado en `php.ini` para la integración con la API de Telegram).
* **Permisos de Directorio:** Permisos de escritura (`775` recomendado) en el directorio raíz del script para la creación y modificación automática de `chat_db.json`.

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/tu-repositorio.git](https://github.com/tu-usuario/tu-repositorio.git)
   cd tu-repositorio