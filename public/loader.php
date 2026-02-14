<?php
// public/loader.php

require_once __DIR__ . '/../includes/bootstrap.php';

// === [CORRECCIÓN CRÍTICA] ===
// Definimos el basePath manualmente porque este archivo no pasa por router.php.
// Esto asegura que las imágenes en las vistas cargadas por AJAX tengan rutas absolutas.
$basePath = '/ProjectAurora/'; 
// ============================

// Cargar rutas
$routes = require __DIR__ . '/../config/routes.php';

// Obtener la sección solicitada y limpiarla
$section = $_GET['section'] ?? 'main';

// 1. Limpieza básica (quitar barras al inicio/final y posibles prefijos "s/")
$cleanSection = preg_replace('#^s/#', '', $section);
$cleanSection = trim($cleanSection, '/');

$matchedFile = null;
$routeParams = []; 
$viewingChannelUUID = null; 

// === Lógica específica para canales (c/UUID) ===
if (strpos($cleanSection, 'c/') === 0) {
    // Extraemos el UUID 
    $parts = explode('/', $cleanSection);
    
    if (isset($parts[1]) && !empty($parts[1])) {
        $viewingChannelUUID = $parts[1]; 
        $routeParams['uuid'] = $parts[1];
    }
    
    $cleanSection = 'channel-profile';
}

// 2. BÚSQUEDA DE RUTA UNIFICADA
if (array_key_exists($cleanSection, $routes)) {
    $matchedFile = $routes[$cleanSection];
} 
else {
    foreach ($routes as $routeKey => $filePath) {
        if (strpos($cleanSection, $routeKey . '/') === 0) {
            $matchedFile = $filePath;
            $remaining = substr($cleanSection, strlen($routeKey) + 1);
            $remaining = trim($remaining, '/'); 
            
            if (!empty($remaining)) {
                $routeParams['uuid'] = $remaining;
            }
            break; 
        }
    }
}

if ($matchedFile && file_exists($matchedFile)) {
    // Ahora channel-profile.php recibirá $basePath correctamente
    include $matchedFile;
} else {
    http_response_code(404);
    ?>
    <div class="component-studio-state-screen">
        <div class="component-studio-state-content">
            <h2 class="component-studio-state-title">404</h2>
            <p class="component-studio-state-text">No se encontró la sección solicitada (<?php echo htmlspecialchars($section); ?>)</p>
        </div>
    </div>
    <?php
}
?>