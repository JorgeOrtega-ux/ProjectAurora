<?php
// public/index.php

// 1. Cargar dependencias
require_once __DIR__ . '/../includes/core/Loader.php';
require_once __DIR__ . '/../includes/core/Router.php';
$routes = require __DIR__ . '/../includes/config/routes.php';

// 2. Inicializar
$loader = new Loader();
$router = new Router($routes);

// 3. Resolver qué archivo toca cargar según la URL
$currentView = $router->resolve();

// 4. DETECCIÓN SPA:
// Si la petición tiene el header 'X-SPA-Request', solo devolvemos el contenido de la vista.
$isSpaRequest = !empty($_SERVER['HTTP_X_SPA_REQUEST']);

if ($isSpaRequest) {
    // Modo SPA: Solo devolver el HTML interno
    $loader->load($currentView);
    exit; 
}

// 5. Modo Navegador: Cargar estructura completa
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <base href="/ProjectAurora/"> 

    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="assets/css/components/components.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <title>Project Aurora</title>
</head>
<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                
                <div class="general-content-top">
                    <?php include __DIR__ . '/../includes/layout/header.php'; ?>
                </div>

                <div class="general-content-bottom">
                    <?php include __DIR__ . '/../includes/modules/moduleSurface.php'; ?>
                    
                    <div class="general-content-scrolleable" id="app-router-outlet">
                        <?php 
                            // Carga inicial del servidor (SEO friendly y primera pintura)
                            $loader->load($currentView); 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="assets/js/app-init.js"></script>
</body>
</html>