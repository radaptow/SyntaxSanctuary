# 🛰️ Syntax Sanctuary — Bit Telemetry Client & UI Template

Módulo oficial de interfaz, documentación e inyección de telemetría en tiempo real (`bit.js`) para el ecosistema **Syntax Sanctuary**[cite: 5, 6, 7, 8]. Este proyecto consta de 4 archivos modulares que proveen tanto el agente ligero de rastreo (*heartbeat*) hacia el **Unified Telemetry Dashboard** como una plantilla base con estética *Glassmorphism*, acentos neón y fondo interactivo de partículas en HTML5 Canvas[cite: 5, 6, 7, 8].

---

## 📂 Arquitectura del Proyecto (4 Archivos)

| Archivo | Rol Principal | Descripción Técnica |
| :--- | :--- | :--- |
| **`index.php`** *(Plantilla Principal)* | Estructura & Navegación | Vista principal de presentación del servicio de radar web. Integra el motor declarativo propio (`../../../src/mycode.php`) mediante las funciones anidadas `decodificar(lista(enlace([...])))` para construir el menú lateral hacia todos los módulos del ecosistema[cite: 8]. |
| **`bit.js`** | Agente de Telemetría | Script autónomo encapsulado en una IIFE (`'use strict'`) que genera un identificador persistente por nodo (`localStorage`) y emite pulsos asíncronos (`POST` CORS) cada 5 segundos al servidor central. |
| **`bit.css`** | Sistema de Diseño | Hoja de estilos con estética *Minimalista, Espejo (Glassmorphism) y Neón (Toque Cyber-Mexica)*. Utiliza las tipografías `Space Grotesk` y `Fira Code`, variables CSS y diseño responsivo adaptable hasta pantallas de `320px`[cite: 5]. |
| **`bitHtml.js`** | Motor Visual & UI | Controlador frontend encargado de renderizar la red de partículas conectadas en el `<canvas id="particle-canvas">`, gestionar el menú lateral móvil (`toggleSidebar()`) y alternar contenido multilenguaje (`setLang('es' | 'en')`)[cite: 7]. |

---

## ✨ Características Principales

### 1. Agente de Rastreo Asíncrono (`bit.js`)
* **Cero Dependencias:** Escrito en Vanilla JavaScript puro mediante `fetch()` con modo `cors`[cite: 6].
* **Identificación Persistente de Nodos:** Genera automáticamente un hash único por navegador (`node_<random>_<timestamp>`) y lo almacena en `localStorage` bajo la llave `bit_client_id`[cite: 6].
* **Detección Dinámica de Usuario:** Inspecciona el DOM buscando su propia etiqueta `<script>` para extraer el atributo personalizado `data-user` (asignando `'guest'` por defecto si se omite)[cite: 6].
* **Latido Continuo (*Heartbeat*):** Envía un reporte inicial al cargar el documento y mantiene un intervalo constante cada `5000 ms` (5 segundos) reportando la URL activa y marca de tiempo UNIX[cite: 6].

### 2. Interfaz Glassmorphism & Motor Canvas (`bit.css` + `bitHtml.js`)
* **Red de Partículas Adaptativa:** Ajusta automáticamente la densidad de partículas según el dispositivo (`80` en escritorio, `40` en pantallas `< 768px`) y traza líneas de conexión dinámicas basadas en la distancia euclidiana sin bloquear los clics del usuario (`pointer-events: none`)[cite: 5, 7].
* **Integración con el Core de Syntax Sanctuary:** El `<aside class="sidebar">` enlaza de forma nativa con los módulos centrales del servidor (`Videos`, `Area Priv`, `Chat`, `Aryi`, `GitHub`, `Juego`, `Vision`, `Texto`, `Blog`, `Status`, `Plantilla` y `Archivos`)[cite: 8].
* **Soporte Multilenguaje Integrado:** Preparado con clases `.lang-es` y `.lang-en` conmutables en vivo mediante la función `setLang()`[cite: 5, 7].

---

## 🛠️ Requisitos del Sistema

* **Servidor Web:** Apache / Nginx con PHP 7.4+ (para la ejecución de `index.php` y la carga de `../../../src/mycode.php`)[cite: 8].
* **Endpoint de Destino (`bit.js`):** Instancia activa del receptor de telemetría en `https://syntaxsanctuary.com/machine_status/index.php?action=register` (o tu propio endpoint configurado en `CONFIG.endpoint`)[cite: 6].

---

## 🚀 Instalación y Uso

### A. Despliegue de la Plantilla Completa
1. Clona o copia los 4 archivos (`index.php`, `bit.css`, `bit.js`, `bitHtml.js`) dentro del directorio correspondiente en tu árbol de servidor (ej. `/public/machine_status/Template/`)[cite: 5, 6, 7, 8].
2. Verifica que la ruta relativa hacia la librería principal en `index.php` coincida con tu estructura de directorios[cite: 8]:
   ```php
   <?php require_once "../../../src/mycode.php"; syntax(); ?>