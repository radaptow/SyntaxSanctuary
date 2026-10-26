# PHP Helper Library (Syntax Sanctuary)

Una librería modular en PHP diseñada para simplificar la generación de código HTML, la estructuración de documentos web, la manipulación de metadatos, el manejo de archivos y cálculos matemáticos/financieros básicos de forma rápida y limpia.

---

## 🚀 Características Principales

- **Generación de HTML semántico y estructurado:** Funciones dedicadas para crear elementos estructurales (`header`, `footer`, `nav`, `main`, `section`, `article`, `aside`, `div`), encabezados, párrafos y listas.
- **Control de metadatos:** Creación automática de etiquetas `<meta>` y gestión de documentos HTML5 completos mediante `syntax()` y `sanctuary()`.
- **Manejo de Enlaces y Listas:** Conversión sencilla de arreglos asociativos en enlaces (`<a>`) y generación de listas ordenadas o desordenadas.
- **Utilidades de Cálculo:** Funciones integradas para evaluación matemática completa y cálculo de pagos netos de turnos laborales.
- **Gestión de Archivos:** Creación automatizada de nuevos archivos PHP con plantillas predeterminadas listas para usar.
- **Seguridad contra XSS:** Implementación automática de `htmlspecialchars()` y decodificación controlada.

---

## 📋 Constantes Disponibles

Para definir los tamaños de los encabezados de forma rápida, la librería incluye constantes de mapeo:

| Constante | Equivalente | Descripción |
| :--- | :--- | :--- |
| `P` | `principal` | Nivel `<h1>` |
| `S` | `secundario` | Nivel `<h2>` |
| `M` | `mediano` | Nivel `<h3>` |
| `MI` | `minusculo` | Nivel `<h4>` |
| `MIN` | `miniatura` | Nivel `<h5>` |
| `D` | `diminuto` | Nivel `<h6>` |

Constantes de control de apertura/cierre para estructuras:
- `INI` (`false`) -> Apertura de etiqueta.
- `FIN` (`true`) -> Cierre de etiqueta.

---

## 🛠️ Referencia de Funciones

### 1. Estructura HTML y SEO
* **`syntax(string $title = 'AryiCodTel', string $seo = '')`**  
  Inicia el documento HTML5 completo incluyendo el `<!DOCTYPE html>`, la etiqueta `<html>`, el `head` con metadatos predeterminados y la apertura del `<body>`.
* **`sanctuary()`**  
  Cierra las etiquetas abiertas del `<body>` y `<html>`.
* **`estructura(string $nombre, bool $barra_diagonal = INI)`**  
  Genera etiquetas HTML válidas (`header`, `footer`, `nav`, `main`, `section`, `article`, `aside`, `div`) con soporte para atributos adicionales (ej. `estructura('div class="container"')`).

### 2. Contenido Visual
* **`encabezado(string $texto, string $tamano)`**  
  Genera encabezados HTML del 1 al 6 utilizando las constantes de tamaño.
* **`parrafo(array $texto)`**  
  Recibe un arreglo de líneas de texto, aplica saltos de línea (`<br>`) y los envuelve en una etiqueta `<p>`.
* **`enlace(array $enlaces, string $modo = 'default')`**  
  Transforma un arreglo asociativo `['Nombre' => 'URL']` en un conjunto de etiquetas ancla `<a>` con `target="_blank"`.
* **`lista(array $items, string $tipo = '')`**  
  Genera listas ordenadas (`<ol>`) o desordenadas (`<ul>`) a partir de un arreglo plano.
* **`meta(string $atributos)`**  
  Genera una etiqueta `<meta>` con los atributos personalizados o predeterminados (`viewport`).
* **`codigocss(string $path)`**  
  Inserta una etiqueta `<link rel="stylesheet">` apuntando a la ruta de la hoja de estilos.

### 3. Utilidades y Operaciones
* **`evaluarMatematicas(int $a, int $b)`**  
  Calcula suma, resta, multiplicación, división, módulo y potencia entre dos números, manejando divisiones entre cero de manera segura.
* **`calcularPagoTurno(float $horasTrabajadas, float $tarifaHora, float $deducciones)`**  
  Calcula el pago neto basado en horas y tarifa menos deducciones (retorna 0 si el resultado es negativo).
* **`nuevo(string $path, string $nombreArchivo = 'index.php')`**  
  Crea carpetas y archivos PHP con una plantilla base predeterminada de manera automatizada.
* **`decodificar(string $string)`**  
  Decodifica entidades HTML especiales aplicando `htmlspecialchars_decode`.

---

## 💻 Ejemplo de Uso

```php
<?php
require_once "mycode.php";

// Iniciar documento HTML
syntax("Mi Sitio Web");

echo encabezado("Bienvenido a mi proyecto", P);
echo parrafo(["Este es el primer renglón.", "Este es el segundo renglón."]);

$links = [
    "Google" => "https://google.com",
    "GitHub" => "https://github.com"
];
echo enlace($links, 'string');

// Cerrar documento HTML
sanctuary();
?>
```

---

## ⚙️ Requisitos
- PHP 8.0 o superior (compatible con tipado estricto y operadores modernos como el de potenciación `**`).