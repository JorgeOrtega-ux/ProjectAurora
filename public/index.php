<?php
// public/index.php

// 1. Cargar el Bootstrap
require_once __DIR__ . '/../includes/core/Bootstrap.php';

use App\Core\Loader;
use App\Core\Router;

$routes = require __DIR__ . '/../includes/config/routes.php';

$bodyClass = isset($_SESSION['user_id']) ? 'is-logged-in' : '';

// 2. Inicializar
$loader = new Loader();
$router = new Router($routes);

// 3. Resolver vista
$routeData = $router->resolve();
$currentView = $routeData['view'];
$layout = $routeData['layout'] ?? 'main';

// 4. DETECCIÓN SPA
$isSpaRequest = !empty($_SERVER['HTTP_X_SPA_REQUEST']);

if ($isSpaRequest) {
    $loader->load($currentView);
    exit; 
}

// 5. Modo Navegador
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
<body class="<?php echo $bodyClass; ?>">
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                
                <?php if ($layout !== 'auth'): ?>
                <div class="general-content-top">
                    <?php include __DIR__ . '/../includes/layout/header.php'; ?>
                </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <?php if ($layout !== 'auth'): ?>
                        <?php include __DIR__ . '/../includes/modules/moduleSurface.php'; ?>
                    <?php endif; ?>
                    
                    <div class="general-content-scrolleable" id="app-router-outlet">
                        <?php 
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