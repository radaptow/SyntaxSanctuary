# 🚀 Syntax Sanctuary // Sistema Multi-Agente & Autenticación Blockchain (LAMP)

> **Computadoras, Programación e Internet**  
> *Derechos reservados © Ramiro G. González (2019 - Presente)*

---

## 🌟 Descripción General del Proyecto

Este proyecto es una plataforma web modular y de alta seguridad construida bajo un entorno **LAMP** (Linux, Apache, MySQL, PHP). Combina conceptos avanzados de ingeniería de software como:
* **Cadenas de Bloques (Blockchain Simulado en PHP):** Generación de hashes criptográficos (SHA-256) vinculados a bloques de datos de usuarios (con prueba de trabajo y *nonce*).
* **Terminal Criptográfica Oculta (`Ctrl + Shift + L`):** Consola interactiva integrada en el cliente con emulación de comandos estilo Linux para logins alternativos.
* **Estación Central Unificada (`area_privada.php`):** Un panel de control privado que incluye:
  * **Gestor de Perfil dinámico:** Actualización en tiempo real con re-hasheo automático en la cadena de bloques.
  * **Sandbox de Archivos Aislado:** Sistema de carpetas y archivos independientes por cada usuario.
  * **Nexus Blog & Apuntes:** Muro de notas públicas y privadas con control de visibilidad.
  * **Test Bench (Centro de Pruebas):** Guía de pruebas interactivas.

---

## 🗄️ Esquema de Base de Datos y Sentencia SQL

Ejecuta el siguiente bloque SQL en tu servidor MySQL para configurar la base de datos y la tabla de usuarios:

```sql
-- 1. Crear la base de datos
CREATE DATABASE IF NOT EXISTS syntax_sanctuary 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- 2. Seleccionar la base de datos
USE syntax_sanctuary;

-- 3. Crear la tabla de usuarios con soporte para datos personales, hora blockchain y contraseñas hasheadas (Bcrypt)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    edad INT NOT NULL,
    pais VARCHAR(50) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    hora TIME NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🛠️ Estructura del Proyecto y Archivos

| Archivo | Rol y Descripción Principal |
| :--- | :--- |
| `conf.php` | Archivo de configuración central, constantes globales y utilidades de renderizado HTML. |
| `conexion.php` | Conexión PDO a MySQL y definición de las clases criptográficas `Block` y `Blockchain`. |
| `guardar_usuario.php` | API Endpoint que almacena nuevos usuarios en MySQL y registra el ID en sesión. |
| `generar_hash.php` | Motor dual: genera el hash Blockchain del usuario o verifica su existencia de forma ciega y segura. |
| `validar_acceso.php` | Endpoint de validación de hashes para otorgar acceso al área privada sin filtrar datos públicos. |
| `auth_credenciales.php` | Administrador de credenciales (Bcrypt / `password_hash`) para accesos mediante contraseña. |
| `index.html` | Pantalla de **Paso 1: Registro** con interfaz futurista y modal interactivo para copiar el hash. |
| `verificar.php` | Pantalla de **Verificación Pública** y puerta de entrada a la Terminal Secreta. |
| `area_privada.php` | **Estación Central:** Perfil, Archivador, Blog y Test Bench protegidos por sesión Blockchain. |
| `app.js` | Controlador frontend para el registro, validación y flujos asíncronos (`fetch`). |
| `terminal_secreta_login.js` | Consola interactiva activada por atajo de teclado (`Ctrl + Shift + L`). |
| `ui_credenciales_privada.js` | Widget inyectado para gestionar credenciales y contraseñas hasheadas en tiempo real. |

---

## ⚙️ Características Técnicas Destacadas

### 1. Mecanismo Blockchain Nativo (`conexion.php`)
Las clases `Block` y `Blockchain` simulan una estructura de bloques inmutable:
```php
class Block {
    public $index, $timestamp, $data, $previousHash, $hash, $nonce;
    
    public function __construct($index, $timestamp, $data, $previousHash = '') {
        $this->index = $index;
        $this->timestamp = $timestamp;
        $this->data = $data;
        $this->previousHash = $previousHash;
        $this->nonce = 0;
        $this->hash = $this->calculateHash();
    }

    public function calculateHash() {
        return hash('sha256', $this->index . $this->timestamp . json_encode($this->data) . $this->previousHash . $this->nonce);
    }
}
```

### 2. Consola Estilo Linux (`terminal_secreta_login.js`)
* **Atajo:** Presiona `Ctrl + Shift + L` (o `Ctrl + Alt + T`).
* **Comandos útiles:**
  * `help`: Muestra la lista de comandos disponibles.
  * `login` / `sudo login`: Despliega el cajón oculto de inicio de sesión por credenciales tradicionales.
  * `clear`: Limpia la terminal.
  * `redirect`: Ejecuta una secuencia de cuenta regresiva y redirección.

### 3. Sandbox de Archivos Aislado
Cada usuario autenticado posee un directorio raíz exclusivo en el servidor (`storage_usuarios/user_<id>/`), previniendo fugas de información y permitiendo la creación, edición, descarga y eliminación segura de carpetas y archivos.

---

## 🚀 Guía de Despliegue Rápido (LAMP Stack / Docker)

1. **Clonar o colocar los archivos** en el directorio raíz de tu servidor web Apache con soporte PHP 8.1+ y MySQL.
2. **Configurar la Base de Datos (`conexion.php`):**
   Asegúrate de apuntar a tu host de MySQL (ej. `localhost` o contenedor Docker `mysql`) con las credenciales correspondientes:
   ```php
   $host = 'mysql'; // o '127.0.0.1'
   $db   = 'syntax_sanctuary';
   $user = 'root';
   $pass = '1993';
   ```
3. **Ejecutar el script SQL** mostrado anteriormente para crear la base de datos y la tabla `usuarios`.
4. **Abrir la aplicación** en tu navegador (`index.html`) y comenzar el registro de prueba.

---

<p align="center">
  <b>Syntax Sanctuary</b> • Computadoras, Programación e Internet 🌐
</p>