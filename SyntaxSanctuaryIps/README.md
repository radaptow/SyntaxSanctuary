# 📡 Unified Telemetry Dashboard (Robust Edition)

Panel de control monolítico en tiempo real para el monitoreo de recursos del servidor (CPU, RAM, Disco) y el rastreo centralizado de orígenes, nodos y clientes web activos mediante balizas JavaScript (`bit.js`). Construido en un único archivo PHP sin dependencias de bases de datos relacionales y diseñado evitando operadores lógicos problemáticos para garantizar máxima compatibilidad entre entornos.

---

## ✨ Características Principales

* **Telemetría de Hardware en Tiempo Real:**
  * **Carga de CPU:** Lectura directa del promedio de carga de 1 minuto mediante `sys_getloadavg()` en entornos Linux.
  * **Memoria RAM:** Análisis nativo de `/proc/meminfo` (`MemTotal` y `MemAvailable`) con barra de progreso porcentual.
  * **Almacenamiento:** Monitoreo del espacio libre y total de la partición raíz (`/`) mediante `disk_total_space()` y `disk_free_space()`.
* **Registro y Rastreo de Nodos (CORS Habilitado):**
  * Recepción de pulsos (*heartbeats*) desde múltiples dominios gracias a cabeceras `Access-Control-Allow-Origin: *`.
  * Detección real de IP detrás de proxies y Cloudflare (`HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR`, `REMOTE_ADDR`) y resolución inversa de nombres de host (`gethostbyaddr()`).
  * Purga automática de nodos inactivos tras 15 minutos (900 segundos) sin reportar actividad (`last_seen`).
* **Protección Visual de Privacidad (IP Blur + PIN):**
  * Ofuscación por defecto en pantalla de direcciones IP y *hostnames* mediante filtros CSS (`filter: blur(6px)`) para proteger datos sensibles durante transmisiones o capturas de pantalla[cite: 4].
  * Desbloqueo interactivo en tiempo real protegido por PIN de seguridad (`SECURITY_PIN`)[cite: 4].
* **Persistencia JSON Segura y Diagnóstico de Permisos:**
  * Almacenamiento en `origins_registry.json` con bloqueo exclusivo de escritura (`flock` con `LOCK_EX`) para evitar corrupción por concurrencia[cite: 4].
  * Alerta visual automática en la interfaz con el comando exacto de remediación si el servidor carece de permisos de escritura sobre el directorio[cite: 4].
* **Generador de Script Integrado:** Herramienta en el panel para personalizar el atributo `data-user` y copiar con un clic la etiqueta `<script>` lista para incrustar en proyectos satélite[cite: 4].

---

## 🛠️ Requisitos del Sistema

* **Sistema Operativo Recomendado:** Linux (requerido para la lectura de `/proc/meminfo` y `sys_getloadavg()`, aunque es compatible con otros SO mostrando métricas de disco y SO base)[cite: 4].
* **PHP:** 7.4 / 8.x o superior (con extensión `json` habilitada)[cite: 4].
* **Permisos de Directorio:** Permisos de escritura en el directorio del script para la creación y actualización de `origins_registry.json`[cite: 4].

---

## 🚀 Instalación y Configuración

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/telemetry-dashboard.git](https://github.com/tu-usuario/telemetry-dashboard.git)
   cd telemetry-dashboard