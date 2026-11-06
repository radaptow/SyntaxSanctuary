# Política de Seguridad / Security Policy — SyntaxSanctuary

*[Español](#español) | [English](#english)*

---

## Español

La seguridad del ecosistema **SyntaxSanctuary** es una prioridad. Agradecemos a la comunidad por ayudarnos a mantener el código seguro mediante la divulgación responsable de vulnerabilidades.

### Versiones Soportadas

SyntaxSanctuary sigue un modelo **OSCR (Often Small Changes Release)** de mejoras continuas e incrementales. Actualmente, los parches de seguridad se aplican directamente sobre la versión activa y la rama principal:

| Versión | Soportada          | Notas                        |
| ------- | ------------------ | ---------------------------- |
| 0.1.0   | :white_check_mark: | Versión actual activa (OSCR) |
| < 0.1.0 | :x:                | Versiones previas sin soporte |

### Cómo Reportar una Vulnerabilidad

**Por favor, NO reportes vulnerabilidades de seguridad a través de issues públicos.**

Si encuentras un fallo de seguridad, repórtalo de forma privada por cualquiera de estos canales:
- **Correo electrónico:** Envía los detalles técnicos a `mostlyphpsoftware@gmail.com` (canal recomendado para adjuntar código o pruebas de concepto).
- **Instagram:** Envía un mensaje directo a [@syntaxsanctuary](https://instagram.com/syntaxsanctuary) para contacto rápido o aviso inicial.
- **GitHub Security Advisories:** Desde la pestaña **Security** del repositorio seleccionando **Report a vulnerability**.

#### Qué incluir en el reporte
1. Descripción clara de la vulnerabilidad y su impacto potencial.
2. Pasos detallados para reproducir el fallo (fragmentos de código, peticiones o capturas).
3. Entorno o configuración específica donde se detectó.

### Tiempos y Proceso de Respuesta
- **Respuesta inicial:** Recibirás confirmación y acuse de recibo entre **12 y 24 horas**.
- **Evaluación y seguimiento:** Gracias a nuestro flujo OSCR, analizamos el impacto de inmediato y te mantendremos al tanto del progreso hasta su resolución.
- **Resolución:**
  - **Si es aceptada:** Desarrollaremos y publicaremos el parche en la siguiente actualización rápida, dándote crédito público por el hallazgo (salvo que prefieras mantener el anonimato).
  - **Si es rechazada:** Te compartiremos una explicación técnica clara sobre por qué no aplica como vulnerabilidad dentro de nuestra arquitectura.

---

## English

Security across the **SyntaxSanctuary** ecosystem is a top priority. We appreciate the community's efforts in keeping our projects safe through responsible disclosure.

### Supported Versions

SyntaxSanctuary follows an **OSCR (Often Small Changes Release)** model, delivering frequent, incremental updates. Security patches are applied directly to the latest active version:

| Version | Supported          | Notes                         |
| ------- | ------------------ | ----------------------------- |
| 0.1.0   | :white_check_mark: | Active release (OSCR)         |
| < 0.1.0 | :x:                | Unsupported earlier builds    |

### Reporting a Vulnerability

**Please DO NOT report security vulnerabilities through public GitHub issues.**

Instead, reach out privately via one of the following channels:
- **Email:** Send full technical details to `mostlyphpsoftware@gmail.com` (recommended for code snippets and proofs-of-concept).
- **Instagram:** Send a direct message to [@syntaxsanctuary](https://instagram.com/syntaxsanctuary) for a quick heads-up.
- **GitHub Security Advisories:** Go to the **Security** tab of this repository and click **Report a vulnerability**.

#### What to Include
1. A clear description of the vulnerability and its potential impact.
2. Step-by-step instructions to reproduce the issue (payloads, code samples, or screenshots).
3. Relevant environment details or configuration settings.

### Response Timeline & Workflow
- **Initial Response:** Expect an acknowledgment within **12 to 24 hours**.
- **Triage & Updates:** Following our OSCR workflow, we evaluate reports promptly and provide direct status updates until the issue is resolved.
- **Resolution:**
  - **If accepted:** A fix will be rolled out in an upcoming incremental release, and we will publicly credit your discovery in our release notes (unless you prefer to remain anonymous).
  - **If declined:** We will provide a transparent technical explanation detailing why the finding falls outside our security threat model.
