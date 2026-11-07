<div align="center">

  <img src="data:image/svg+xml;utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 240 240' width='150' height='150'%3E%3Cdefs%3E%3ClinearGradient id='g1' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' stop-color='%2334D399'/%3E%3Cstop offset='50%25' stop-color='%2338BDF8'/%3E%3Cstop offset='100%25' stop-color='%23818CF8'/%3E%3C/linearGradient%3E%3ClinearGradient id='g2' x1='0%25' y1='0%25' x2='0%25' y2='100%25'%3E%3Cstop offset='0%25' stop-color='%236EE7B7'/%3E%3Cstop offset='100%25' stop-color='%233B82F6'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect x='10' y='10' width='220' height='220' rx='48' fill='%230F172A' stroke='url(%23g1)' stroke-width='4'/%3E%3Cpath d='M54 186 V112 C54 66 120 38 120 38 C120 38 186 66 186 112 V186' fill='none' stroke='url(%23g1)' stroke-width='5' stroke-linecap='round' opacity='0.35'/%3E%3Ccircle cx='120' cy='60' r='5' fill='%2334D399'/%3E%3Cpath d='M98 80 C82 80 82 92 82 105 V113 C82 120 72 125 66 125 C72 125 82 130 82 137 V145 C82 158 82 170 98 170' fill='none' stroke='url(%23g1)' stroke-width='8' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M142 80 C158 80 158 92 158 105 V113 C158 120 168 125 174 125 C168 125 158 130 158 137 V145 C158 158 158 170 142 170' fill='none' stroke='url(%23g1)' stroke-width='8' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpolygon points='120,98 135,125 120,152 105,125' fill='url(%23g2)'/%3E%3Cline x1='76' y1='194' x2='164' y2='194' stroke='%23334155' stroke-width='4' stroke-linecap='round'/%3E%3C/svg%3E" alt="SyntaxSanctuary Logo" />

  # SyntaxSanctuary

  **Un refugio sagrado para el código limpio, la arquitectura serena y el desarrollo sin ruido.**

  [![Versión](https://img.shields.io/badge/version-1.0.0-34D399?style=for-the-badge&logoColor=white&labelColor=0F172A)](https://github.com/tu-usuario/syntaxsanctuary)
  [![Estado](https://img.shields.io/badge/build-passing-38BDF8?style=for-the-badge&labelColor=0F172A)](https://github.com/tu-usuario/syntaxsanctuary)
  [![Licencia](https://img.shields.io/badge/license-MIT-818CF8?style=for-the-badge&labelColor=0F172A)](./LICENSE)
  [![PRs Welcome](https://img.shields.io/badge/PRs-welcome-6EE7B7?style=for-the-badge&labelColor=0F172A)](./CONTRIBUTING.md)

  <p align="center">
    <a href="#-inicio-rápido"><strong>Inicio Rápido</strong></a> ·
    <a href="#-pilares-del-santuario"><strong>Características</strong></a> ·
    <a href="#-arquitectura"><strong>Arquitectura</strong></a> ·
    <a href="#-hoja-de-ruta"><strong>Roadmap</strong></a> ·
    <a href="#-comunidad"><strong>Comunidad</strong></a>
  </p>

</div>

---

## 🌿 La Filosofía

En un ecosistema saturado de configuraciones interminables, dependencias innecesarias y fatiga tecnológica, **SyntaxSanctuary** nace como un espacio de claridad. Aquí cada línea tiene un propósito, cada abstracción está justificada y la experiencia de desarrollo (*Developer Experience*) se trata como un arte.

> *"Escribe código como quien construye un santuario: sólido en sus cimientos, limpio en su estructura y sereno para quien lo habita."*

---

## ✨ Pilares del Santuario

| Pilar | Descripción |
| :--- | :--- |
| 🏛️ **Arquitectura Intencional** | Estructura modular y predecible diseñada para escalar sin deuda técnica oculta. |
| ⚡ **Cero Fricción (Zen DX)** | Arranque instantáneo, tipado estricto y herramientas configuradas listas para producir desde el minuto uno. |
| 🧩 **Patrones Curados** | Colección selecta de *snippets*, componentes y utilidades probadas en batalla y libres de *bloatware*. |
| 🛡️ ** tipado & Seguridad** | Validación de extremo a extremo, manejo elegante de errores y pruebas automatizadas integradas. |

---

## 🚀 Inicio Rápido

Pon en marcha tu entorno local en menos de un minuto.

### Prerrequisitos

* **Node.js** `>= 20.x` (o **Bun** `>= 1.1`)
* **Git** instalado en tu sistema

### Instalación

```bash
# 1. Clona el santuario en tu máquina local
git clone https://github.com/tu-usuario/syntaxsanctuary.git

# 2. Accede al directorio
cd syntaxsanctuary

# 3. Instala las dependencias limpias
npm install

# 4. Inicia el entorno en modo desarrollo
npm run dev
```

---

## 💻 Ejemplo de Uso

**SyntaxSanctuary** prioriza APIs declarativas, legibles y autoexplicativas:

```typescript
import { createSanctuary, guard, compose } from '@syntaxsanctuary/core';

const app = createSanctuary({
  mode: 'zen',
  strictSyntax: true,
});

app.register(
  compose(
    guard.validateSchema(),
    async (ctx) => {
      return ctx.respond({
        status: 'serene',
        message: 'Bienvenido a SyntaxSanctuary ✨',
      });
    }
  )
);

await app.ignite(3000);
```

---

## 🗂️ Arquitectura

Una organización pensada para encontrar cualquier pieza del sistema de un vistazo:

```text
syntaxsanctuary/
├── assets/              # Recursos gráficos y logo oficial (logo.svg)
├── docs/                # Guías de estilo, ADRs y documentación técnica
├── packages/
│   ├── core/            # Motor principal y primitivas base
│   ├── cli/             # Herramientas de línea de comandos
│   └── patterns/        # Biblioteca de patrones y utilidades puras
├── tests/               # Suites de pruebas unitarias y de integración
└── README.md            # Puerta de entrada al santuario
```

---

## 🗺️ Hoja de Ruta

- [x] Diseño del núcleo modular y definición de estándares de código
- [x] CLI interactivo para generación de plantillas limpias
- [ ] Sistema de *plugins* minimalistas sin dependencias externas
- [ ] Portal interactivo de documentación y *playground* en vivo
- [ ] Extensión oficial para editores (VS Code / Cursor / Zed)

---

## 🎨 Logo Oficial (Exportar para GitHub)

> **Nota para GitHub:** Por políticas de seguridad, GitHub bloquea imágenes `data:image/svg+xml` en línea dentro de los repositorios públicos. Para que el logo luzca perfecto en tu repositorio, guarda el siguiente código en `assets/logo.svg` y cambia el `src` superior a `./assets/logo.svg`.

<details>
<summary><strong>👉 Haz clic aquí para copiar el código fuente de <code>assets/logo.svg</code></strong></summary>

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" width="240" height="240">
  <defs>
    <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#34D399"/>
      <stop offset="50%" stop-color="#38BDF8"/>
      <stop offset="100%" stop-color="#818CF8"/>
    </linearGradient>
    <linearGradient id="g2" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="#6EE7B7"/>
      <stop offset="100%" stop-color="#3B82F6"/>
    </linearGradient>
  </defs>
  <!-- Base oscura del santuario -->
  <rect x="10" y="10" width="220" height="220" rx="48" fill="#0F172A" stroke="url(#g1)" stroke-width="4"/>
  <!-- Arco arquitectónico sagrado -->
  <path d="M54 186 V112 C54 66 120 38 120 38 C120 38 186 66 186 112 V186" fill="none" stroke="url(#g1)" stroke-width="5" stroke-linecap="round" opacity="0.35"/>
  <!-- Nodo cenital -->
  <circle cx="120" cy="60" r="5" fill="#34D399"/>
  <!-- Pilares de llaves { } -->
  <path d="M98 80 C82 80 82 92 82 105 V113 C82 120 72 125 66 125 C72 125 82 130 82 137 V145 C82 158 82 170 98 170" fill="none" stroke="url(#g1)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M142 80 C158 80 158 92 158 105 V113 C158 120 168 125 174 125 C168 125 158 130 158 137 V145 C158 158 158 170 142 170" fill="none" stroke="url(#g1)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
  <!-- Piedra angular luminosa -->
  <polygon points="120,98 135,125 120,152 105,125" fill="url(#g2)"/>
  <!-- Umbral inferior -->
  <line x1="76" y1="194" x2="164" y2="194" stroke="#334155" stroke-width="4" stroke-linecap="round"/>
</svg>
```

</details>

---

## 🤝 Comunidad y Contribuciones

Las puertas del santuario están abiertas para cualquier desarrollador que valore la artesanía del software.

1. Haz un **Fork** del repositorio.
2. Crea una rama descriptiva (`git checkout -b feat/nueva-mejora`).
3. Asegúrate de mantener la pureza del código (`npm run lint && npm test`).
4. Abre un **Pull Request** detallando tu aporte.

---

<div align="center">
  <sub>Construido con calma, precisión y pasión por el código limpio en <strong>SyntaxSanctuary</strong>.</sub><br/>
  <sub>Distribuido bajo la licencia <a href="./LICENSE">MIT</a> © 2026</sub>
</div>
