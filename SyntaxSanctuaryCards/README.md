# 🎨 Portafolio Digital | Bento Grid UI (Syntax Sanctuary)

Un sistema de portafolio web dinámico, ligero y autogestionado en un solo archivo PHP (`index.php`), diseñado bajo una arquitectura **Bento Grid** responsiva con estética oscura y acentos en cyan neón (`#00f0ff`). No requiere bases de datos relacionales (MySQL/PostgreSQL), utilizando persistencia plana en formato JSON autogenerable.

---

## ✨ Características Principales

* **Persistencia JSON Autogenerable (`portafolio_data.json`):** Al ejecutarse por primera vez, el sistema verifica la existencia del archivo de datos; si no existe, lo crea automáticamente con una estructura inicial formateada (`JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE`).
* **Diseño Bento Grid Responsivo:** Cuadrícula basada en CSS Grid (`repeat(auto-fill, minmax(280px, 1fr))`) diseñada para adaptarse fluidamente desde pantallas móviles pequeñas (320px) hasta monitores de escritorio.
* **Tamaños de Tarjeta Modulares:** Soporte nativo para variar las dimensiones de cada proyecto en la cuadrícula mediante clases dinámicas (`grid-large`, `grid-tall` o estándar) definidas directamente desde el JSON.
* **Experiencia de Usuario Adaptativa (`@media (hover)`):**
  * **Escritorio (`hover: hover`):** Animación de elevación, resplandor neón, desenfoque de fondo (`blur(2px)`) y revelación suave de la descripción al pasar el cursor.
  * **Dispositivos Táctiles (`hover: none`):** Visualización permanente del contenido y descripción sin requerir interacción previa para garantizar accesibilidad móvil.
* **Rendimiento y Seguridad:** Carga diferida de imágenes nativa (`loading="lazy"`) y sanitización de salidas en el DOM con `htmlspecialchars()` para prevenir inyecciones XSS.

---

## 🛠️ Requisitos del Sistema

* **Servidor Web:** Apache, Nginx o servidor integrado de PHP.
* **PHP:** 7.4 o superior (con extensión `json` habilitada).
* **Permisos de Directorio:** Permisos de escritura en el directorio raíz para permitir la generación automática de `portafolio_data.json`[cite: 2].

---

## 🚀 Instalación y Uso Rápido

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/tu-usuario/tu-repositorio.git](https://github.com/tu-usuario/tu-repositorio.git)
   cd tu-repositorio