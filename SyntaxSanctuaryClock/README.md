# # SyntaxSanctuaryPayroll (hkpgReloj)

**SyntaxSanctuaryPayroll** (también conocido internamente como `hkpgReloj`) es un sistema monolítico ligero y autónomo desarrollado en PHP puro. Proporciona control de asistencia (reloj checador), gestión de tarifas por empleado, cálculo automático de nómina bisemanal con deducciones/impuestos y herramientas de respaldo/restauración de datos, todo sin requerir un motor de base de datos externo (almacenamiento en JSON plano).

---

## 📋 Características Principales

* **Monolito de Archivo Único:** Todo el backend, lógica de negocio, templates HTML, estilos CSS y scripts JavaScript residen en un único archivo (`jornada.php`).
* **Sin Dependencias de BD (Flat-File JSON):** Almacena registros, empleados y tarifas en un archivo local (`asistencia_db.json`) con bloqueo concurrente (`LOCK_EX`).
* **Seguridad y Control de Acceso:**
  * Acceso protegido por PIN configurable (vía formulario o query parameter `?pin=...`).
  * Validación segura contra ataques de temporización con `hash_equals()`.
* **Modo Dual de Operación:**
  * **Reloj (Turno):** Vista simplificada y optimizada para dispositivos móviles/tablets para registrar entradas (`clock_in`) y salidas (`clock_out`).
  * **Administración y Nómina:** Panel completo para monitorear turnos, consultar periodos bisemanales (actuales y pasados), gestionar empleados y ajustar tarifas por hora.
* **Cálculo Automático de Nómina:**
  * Deducción fija configurable para receso/almuerzo (por defecto 30 minutos).
  * Horas extra a partir de 40 horas semanales calculadas a factor 1.5x.
  * Soporte para días festivos (`is_holiday`) a tarifa especial.
  * Deducciones antes de impuestos y retenciones automáticas (Seguro Social, Medicare, Federal y Estatal).
* **Gestión de Respaldos:**
  * Descarga instantánea de copias de seguridad en formato JSON.
  * Importador con dos modalidades: combinación inteligente (`merge`) sin duplicados o reemplazo total (`replace`).

---

## ⚙️ Reglas y Configuración de Nómina

Las constantes principales están definidas al inicio de `jornada.php`:

| Constante | Valor por Defecto | Descripción |
| :--- | :--- | :--- |
| `ACCESS_PIN` | `'1234'` | Clave de acceso numérico para el sistema |
| `DATA_FILE` | `asistencia_db.json` | Ruta del archivo de persistencia JSON |
| `DEFAULT_RATE` | `$10.00` | Tarifa horaria base por defecto |
| `LUNCH_MINUTES` | `30` | Minutos descontados por turno para receso |
| `OVERTIME_LIMIT` | `40.0` | Umbral de horas regulares por semana |
| `SPECIAL_MULTIPLIER` | `1.5` | Multiplicador para horas extra y días festivos |
| `ANCHOR_PAY_PERIOD_START`| `'09/14/2026'` | Fecha ancla para calcular periodos bisemanales |
| `PAY_PERIOD_DAYS` | `14` | Duración del ciclo de nómina (días) |

### Impuestos y Retenciones

El cálculo impositivo toma el ingreso bruto, descuenta las deducciones previas (`PRE_TAX_DEDUCTION = $88.02`) y aplica los siguientes porcentajes:

* **Social Security (SS):** 6.20% (`TAX_SS = 0.062`)
* **Medicare:** 1.45% (`TAX_MEDICARE = 0.0145`)
* **Federal Tax:** ~4.17% (`TAX_FEDERAL = 0.041723`)
* **State Tax:** ~2.05% (`TAX_STATE = 0.020518`)

---

## 📁 Estructura del Archivo de Base de Datos (`asistencia_db.json`)

El archivo se genera automáticamente al primer inicio si no existe:

```json
{
  "users": [
    "Ramiro Garcia Gonzalez"
  ],
  "tarifas": {
    "Ramiro Garcia Gonzalez": 15.00
  },
  "entries": [
    {
      "user": "Ramiro Garcia Gonzalez",
      "date": "10/26/2026",
      "time_in": "08:00 AM",
      "time_out": "04:30 PM",
      "is_holiday": false
    }
  ]
}
```

---

## 🚀 Requisitos e Instalación

### Requisitos

* PHP 8.1 o superior.
* Servidor web compatible (Apache, Nginx, Caddy o el servidor integrado de PHP).
* Permisos de lectura y escritura en el directorio de la aplicación (para que PHP pueda leer y escribir `asistencia_db.json`).

### Despliegue Rápido (Servidor de Desarrollo)

1. Clona o descarga los archivos en tu máquina o servidor:
   ```bash
   git clone <URL-del-repositorio> SyntaxSanctuaryPayroll
   cd SyntaxSanctuaryPayroll
   ```

2. Inicia el servidor embebido de PHP:
   ```bash
   php -S 0.0.0.0:8000 jornada.php
   ```

3. Abre tu navegador en:
   ```text
   http://localhost:8000
   ```

4. Ingresa el PIN por defecto (`1234`).

---

## 🔒 Consideraciones de Seguridad en Producción

1. **Cambiar el PIN Predeterminado:** Edita `ACCESS_PIN` en `jornada.php` antes de usar el sistema en un entorno productivo.
2. **Proteger el archivo JSON:** Asegúrate de que `asistencia_db.json` no sea accesible directamente desde la URL pública configurando las reglas de tu servidor web (por ejemplo, en `.htaccess` o bloques de Nginx):
   ```apache
   # Apache: .htaccess
   <Files "asistencia_db.json">
       Require all denied
   </Files>
   ```
3. **HTTPS Obligatorio:** Implementa SSL/TLS para proteger la transmisión de credenciales y datos de asistencia.

---

## 📄 Licencia

Este proyecto está bajo la licencia [MIT](LICENSE) o uso privado autorizado por SyntaxSanctuary.
