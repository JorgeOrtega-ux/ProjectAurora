<?php
// public/index.php
require_once __DIR__ . '/../includes/core/Bootstrap.php';

use App\Core\Loader;
use App\Core\Router;

$routes = require __DIR__ . '/../includes/config/routes.php';
$bodyClass = isset($_SESSION['user_id']) ? 'is-logged-in' : '';

$loader = new Loader();
$router = new Router($routes);

$routeData = $router->resolve();
$currentView = $routeData['view'];
$layout = $routeData['layout'] ?? 'main';

$isSpaRequest = !empty($_SERVER['HTTP_X_SPA_REQUEST']);

if ($isSpaRequest) {
    $loader->load($currentView);
    exit; 
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_COOKIE['aurora_lang'] ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/ProjectAurora/"> 
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="assets/css/components/components.css">
    <link rel="stylesheet" type="text/css" href="assets/css/root.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <title>Project Aurora</title>
</head>
<body class="<?= $bodyClass; ?>">
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
                        <?php $loader->load($currentView); ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script>
        // Entregamos tanto el lenguaje como las configuraciones globales del servidor al JS
        window.i18n = <?= json_encode(empty($translations) ? new stdClass() : $translations) ?>;
        window.APP_CONFIG = <?= json_encode($APP_CONFIG ?? new stdClass()) ?>;
        
        window.t = function(key, replacements = null) { 
            let text = window.i18n[key] || key; 
            
            // Si no se proveen reemplazos explícitos, usamos los que vengan del APP_CONFIG del servidor
            let data = replacements || window.APP_CONFIG;
            
            if (data && typeof data === 'object') {
                for (const prop in data) {
                    // split/join previene errores que pueden ocurrir con RegEx al encontrar strings con caracteres raros
                    text = text.split('{' + prop + '}').join(data[prop]);
                }
            }
            return text;
        };
    </script>
    <script type="module" src="assets/js/app-init.js"></script>
</body>
</html>