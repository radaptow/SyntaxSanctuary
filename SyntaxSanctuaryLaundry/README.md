# 🧺 SyntaxSanctuaryLaundry `v0.1.0`

**SyntaxSanctuaryLaundry** es una aplicación web monolítica en PHP diseñada para el control rápido, seguro y en tiempo real del inventario de lavandería y blancos (toallas, sábanas, fundas, edredones y sets de cama).

Construida en un único archivo sin dependencias de bases de datos externas (utiliza almacenamiento JSON atómico con bloqueo exclusivo `LOCK_EX`), está optimizada tanto para dispositivos móviles en planta como para pantallas de administración central.

## ✨ Características Principales (`v0.1.0`)

- **📱 Modo Conteo (Móvil):** Interfaz de botones gigantes (`+1` / `-1`) y navegación rápida entre artículos pensada para operarios desde el celular.
- **🖥️ Modo Central (Administración):** Panel completo con sumatoria total de unidades, desglose por artículo y herramientas de gestión.
- **👑 Contador Especial de Sets (King y Queen):** Agrega automáticamente las piezas individuales correspondientes al inventario en una sola operación atómica:
  - **1 King Set** = `1 King Fit` + `1 King Flat` + `4 King PC`
  - **1 Queen Set** = `1 Queen Fit` + `1 Queen Flat` + `4 Queen PC`
- **📷 Acceso Rápido por Código QR:** Generación automática de código QR con PIN integrado para que el personal inicie sesión al instante escaneando desde la pantalla central.
- **➕ Catálogo Personalizable:** Permite dar de alta nuevos artículos directamente desde la interfaz.
- **💾 Respaldo Instantáneo:** Descarga directa de la base de datos en formato `.json` con marca de tiempo y versión.
- **🛡️ Seguridad Integrada:**
  - Autenticación por PIN comparada con `hash_equals` para prevenir ataques de tiempo.
  - Protección contra **CSRF** mediante tokens criptográficos de sesión en formularios y peticiones AJAX.
  - Escritura segura en disco mediante `LOCK_EX` para evitar corrupción de datos ante accesos concurrentes.
  - **Zona de Peligro Protegida:** Reinicio de inventario a cero con doble confirmación en cliente y validación estricta de la palabra `RESET` en el servidor.

## 📋 Requisitos del Servidor

- **PHP:** `8.0` o superior (`declare(strict_types=1)` y tipos de retorno modernos como `never` e `int|false` requieren **PHP 8.1+**).
- **Extensiones PHP:**
  - `mbstring` (para manejo seguro de cadenas UTF-8)
  - `json` y `session` (incluidas por defecto)
- **Servidor Web:** Apache, Nginx, Caddy o el servidor integrado de PHP.
- **Permisos de Escritura:** El directorio donde se aloje el archivo debe tener permisos de escritura para que PHP pueda crear y actualizar `server_inventory.json`.

## 🚀 Instalación

### Opción 1: Servidor Web Tradicional (Apache / Nginx / Hosting)

1. Clona este repositorio o descarga el archivo principal:
   ```bash
   git clone https://github.com/SyntaxSanctuary/SyntaxSanctuaryLaundry.git
   cd SyntaxSanctuaryLaundry
   ```
2. Renombra `SyntaxSanctuaryLaundry.php` a `index.php` (opcional, si deseas acceder desde la raíz del directorio):
   ```bash
   cp SyntaxSanctuaryLaundry.php index.php
   ```
3. Asegúrate de que el servidor web tenga permisos de escritura sobre la carpeta para generar el archivo `server_inventory.json`:
   ```bash
   chmod 755 .
   chown www-data:www-data .
   ```
4. Abre la URL correspondiente en tu navegador (por ejemplo, `https://tudominio.com/lavanderia/`).

### Opción 2: Ejecución Local Rápida (Servidor Integrado de PHP)

Si quieres probar o correr el sistema en una red local rápidamente sin configurar Apache o Nginx:

1. Abre una terminal en la carpeta del proyecto.
2. Ejecuta el servidor embebido de PHP escuchando en todas las interfaces de red:
   ```bash
   php -S 0.0.0.0:8080 SyntaxSanctuaryLaundry.php
   ```
3. Abre en tu navegador `http://localhost:8080` (o usa la dirección IP local de tu equipo, como `http://192.168.1.50:8080`, para conectarte desde los celulares de la lavandería en la misma red Wi-Fi).

## ⚙️ Configuración Inicial

Dentro de las primeras líneas del archivo `SyntaxSanctuaryLaundry.php` encontrarás el bloque de configuración:

```php
const APP_NAME    = 'SyntaxSanctuaryLaundry';
const APP_VERSION = '0.1.0';
const DATA_FILE   = __DIR__ . '/server_inventory.json';

// PIN de acceso predeterminado (Cambiar en producción)
$access_pin = '2709';
```

- **Cambiar el PIN:** Modifica el valor de `$access_pin = '2709';` por el código numérico de tu preferencia. El código QR de la vista Central se actualizará automáticamente con el nuevo PIN.
- **Artículos Predeterminados:** Puedes ajustar el arreglo `$default_items` antes del primer inicio para definir la lista base de prendas de tu lavandería.

## 📖 Guía de Uso

### 1. Inicio de Sesión
- Ingresa el PIN configurado (por defecto `2709`) y presiona **INGRESAR**, o bien escanea el **Código QR** desde otro dispositivo que tenga abierta la vista **CENTRAL**.

### 2. Modo 📱 CONTEO (Para Operadores)
- Muestra un artículo a la vez con su contador actual en tamaño gigante.
- Usa los botones **➕ MÁS 1** y **➖ MENOS 1** para ajustar las cantidades en tiempo real sin recargar la página.
- Usa **⬅️ ANTERIOR** y **SIGUIENTE ➡️** para recorrer el catálogo de prendas.

### 3. Modo 🖥️ CENTRAL (Para Supervisores / Administración)
- **Resumen General:** Visualiza el total acumulado de piezas y el listado completo de cada artículo.
- **Contador Especial de Sets:** Ingresa cuántos sets completos (King o Queen) se armaron, revisa la previsualización de piezas individuales (`Fit`, `Flat` y `PC`) y presiona **AGREGAR SETS**.
- **Agregar Nuevo Artículo:** Escribe el nombre del insumo o prenda en el formulario y presiona **Guardar Artículo** para incorporarlo al catálogo.
- **Respaldo del Inventario:** Haz clic en **💾 DESCARGAR RESPALDO JSON** para guardar una copia local del estado actual del inventario.
- **Reiniciar Todo a Cero:** En la **Zona de Peligro**, presiona **🗑️ REINICIAR TODO A CERO**, acepta la advertencia y escribe la palabra `RESET` para poner todos los contadores en `0` conservando el catálogo de artículos.

## 📁 Estructura de Archivos Recomendada para GitHub

```text
SyntaxSanctuaryLaundry/
├── SyntaxSanctuaryLaundry.php   # Aplicación monolítica principal (o index.php)
├── README.md                    # Documentación de instalación y uso
└── .gitignore                   # Ignorar server_inventory.json para no subir datos reales
```

> **Tip para `.gitignore`:** Si no deseas que los conteos locales se suban a tu repositorio de GitHub, agrega `server_inventory.json` a tu archivo `.gitignore`.

## 📌 Información de Versión

- **Proyecto:** `SyntaxSanctuaryLaundry` (anteriormente *Control de Inventario - Lavandería*)
- **Versión:** `0.1.0`
- **Autor:** `SyntaxSanctuary_RGG`
