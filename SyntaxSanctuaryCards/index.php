<?php
// Ruta del archivo de persistencia JSON
$json_file = 'portafolio_data.json';

// Si el archivo JSON no existe, lo creamos con el proyecto inicial (tu imagen generada)
if (!file_exists($json_file)) {
    $datos_iniciales = [
        [
            "id" => "01",
            "titulo" => "Syntax Sanctuary - Componente 01: cuadricula background",
            "descripcion" => "Diseño de componente frontend con patrón de cuadrícula y estilo neón trending 2026. Optimizado para fondos de interfaz.",
            "imagen" => "./assets/01.jpg", // Tu imagen generada
            "enlace" => "#",
            "clase_tamano" => "grid-large" // Clase CSS para variar el tamaño de la tarjeta
        ]
    ];
    // Guardar el JSON inicial
    file_put_contents($json_file, json_encode($datos_iniciales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Cargar los datos desde el archivo JSON
$json_data = file_get_contents($json_file);
$proyectos = json_decode($json_data, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portafolio | Syntax Sanctuary</title>
    <link rel="stylesheet" href="./css/styles.css">
</head>
<body>

    <header>
        <h1>Portafolio Digital</h1>
        <p>Desarrollo Web & Componentes UI</p>
    </header>

    <main class="bento-grid">
        <?php foreach ($proyectos as $proyecto): ?>
            <article class="bento-item <?php echo htmlspecialchars($proyecto['clase_tamano']) ?>">
                <img src="<?php echo htmlspecialchars($proyecto['imagen']) ?>" alt="<?php echo htmlspecialchars($proyecto['titulo']) ?>" class="bento-img" loading="lazy">
                <div class="bento-content">
                    <h3><?php echo htmlspecialchars($proyecto['titulo']) ?></h3>
                    <p><?php echo htmlspecialchars($proyecto['descripcion']) ?></p>
                    <a href="<?php echo htmlspecialchars($proyecto['enlace']) ?>" class="bento-link" target="_blank">Ver Proyecto</a>
                </div>
            </article>
        <?php endforeach; ?>
    </main>

    <script src="https://syntaxsanctuary.com/bit.js" data-user="SyntaxSanctuary_RGG"></script>
</body>
</html>