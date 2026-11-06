# SyntaxSanctuary ⚡

*[English](#english) | [Español](#español)*

![Version](https://img.shields.io/badge/version-0.1.0_Genesis-00ff9d?style=flat-square&labelColor=0d1117)
![Release Model](https://img.shields.io/badge/release-OSCR-00b4d8?style=flat-square&labelColor=0d1117)
![Stack](https://img.shields.io/badge/stack-LAMP_%7C_Docker-777bb4?style=flat-square&labelColor=0d1117)
![License](https://img.shields.io/badge/license-MIT-ffffff?style=flat-square&labelColor=0d1117)

---

## English

**SyntaxSanctuary** is a self-hosted, full-stack web ecosystem and modular micro-framework built on a clean, zero-bloat LAMP architecture. Designed with a dark cyber-terminal aesthetic, it unifies native Blockchain authentication, isolated per-user filesystems, an integrated code/file manager (**FM Plus**), a hidden Linux-style web terminal, and modular AI interfaces (Chat, Image Generation, and TTS) inside a Docker-ready environment.

### Key Features

- **Zero-Bloat Modular PHP Core:** Built natively with PHP (`pdo_mysql`, `curl`), HTML5, CSS3, and vanilla JavaScript without heavy external framework dependencies.
- **Native Blockchain Authentication:** Custom cryptographic identity and transactional authentication layer.
- **Isolated User Workspaces & FM Plus:** Sandboxed user directories managed through **FM Plus**, a secure browser-based file manager and code editor routed under `src/`.
- **Hidden Linux-Style Web Terminal:** Built-in interactive command console for fast system navigation and power-user workflows.
- **AI-Ready Modules:** Dedicated interfaces for conversational Chat, Image Generation, and Text-to-Speech (TTS) management, ready to connect with local or self-hosted inference engines.
- **Self-Initializing Database Layer:** Automated schema and table provisioning on first run via PDO (`conf.php` & `conexion.php`).
- **Containerized Orchestration:** Reproducible Apache + PHP + MariaDB deployment powered by Docker and Docker Compose.

### Architecture Overview

```text
SyntaxSanctuary/
├── src/                  # Central router, FM Plus editor, terminal & AI modules (Chat, Image, TTS)
├── conf.php              # Core environment & application settings
├── conexion.php          # PDO database engine & automatic table provisioning
├── Dockerfile            # Custom PHP + Apache image (pdo_mysql, curl, core extensions)
├── docker-compose.yml    # Multi-container orchestration (Web + MariaDB)
├── .env                  # Environment variables & credentials (local/container)
├── SECURITY.md           # Bilingual OSCR security & vulnerability disclosure policy
└── LICENSE               # MIT License
