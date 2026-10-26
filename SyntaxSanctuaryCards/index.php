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
            "imagen" => "componente1.jpg", // Tu imagen generada
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
    <style>
        /* Variables de diseño - Tema Oscuro Moderno */
        :root {
            --bg-color: #09090b;
            --card-bg: #18181b;
            --text-primary: #f4f4f5;
            --text-secondary: #a1a1aa;
            --accent: #00f0ff; /* Cyan neón inspirado en tu componente */
            --border-color: #27272a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            line-height: 1.5;
            padding: 20px;
        }

        header {
            text-align: center;
            margin: 40px 0;
        }

        header h1 {
            font-size: 2.5rem;
            background: linear-gradient(90deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        /* 
         * Grid Responsivo
         * Comienza a apilarse en pantallas de 320px
         * minmax(280px, 1fr) asegura que entre en pantallas de 320px dejando margen
         */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            grid-auto-rows: 250px; /* Altura base de las filas */
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .bento-item {
            position: relative;
            background-color: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .bento-item:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 30px rgba(0, 240, 255, 0.15);
            border-color: var(--accent);
            z-index: 10;
        }

        /* Clases para tamaños variados de la cuadrícula */
        .grid-large {
            grid-column: span 1;
            grid-row: span 2;
        }
        
        .grid-tall {
            grid-row: span 2;
        }

        @media (min-width: 640px) {
            .grid-large {
                grid-column: span 2;
                grid-row: span 2;
            }
        }

        .bento-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
            transition: filter 0.3s ease;
        }

        /* Capa de contenido superpuesta */
        .bento-content {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 24px;
            background: linear-gradient(to top, rgba(9, 9, 11, 0.95) 0%, rgba(9, 9, 11, 0.8) 50%, transparent 100%);
            z-index: 2;
            transform: translateY(65%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            height: 100%;
        }

        /* Revelar descripción en hover para escritorio */
        @media (hover: hover) {
            .bento-item:hover .bento-content {
                transform: translateY(0);
            }
            .bento-item:hover .bento-img {
                filter: brightness(0.4) blur(2px);
            }
        }

        /* Para móviles (320px+), el contenido siempre está visible */
        @media (hover: none) {
            .bento-content {
                transform: translateY(0);
                background: linear-gradient(to top, rgba(9, 9, 11, 0.95) 0%, rgba(9, 9, 11, 0.6) 100%);
            }
        }

        .bento-content h3 {
            font-size: 1.25rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bento-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 16px;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        /* CORRECCIÓN: Separamos las reglas de hover y de media query */
        .bento-item:hover .bento-content p {
            opacity: 1;
        }

        @media (hover: none) {
            .bento-content p {
                opacity: 1;
            }
        }

        .bento-link {
            align-self: flex-start;
            padding: 8px 16px;
            background-color: var(--accent);
            color: #000;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: background-color 0.2s;
        }

        .bento-link:hover {
            background-color: #fff;
        }
    </style>
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