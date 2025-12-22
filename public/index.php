<?php
// public/index.php

// 1. Cargar el Router para saber dónde estamos
require_once __DIR__ . '/../config/routers/router.php';

// 2. Definir rutas físicas para inclusión inicial
$routesMap = require __DIR__ . '/../config/routes.php';
$fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Aurora Alpha</title>
    
    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
    </script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/styles.css">
    
    <script type="module" src="<?php echo $basePath; ?>public/assets/js/app-init.js"></script>
</head>
<body>

    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                
                <div class="general-content-top">
                    <?php include __DIR__ . '/../includes/layouts/header.php'; ?>
                </div>

                <div class="general-content-bottom">
                    <?php include __DIR__ . '/../includes/modules/module-surface.php'; ?>
                    
                    <div class="general-content-scrolleable" data-container="main-section">
                        <?php 
                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            echo "Error: Archivo base no encontrado.";
                        }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>