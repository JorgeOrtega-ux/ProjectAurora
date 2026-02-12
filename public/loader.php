<?php
// public/loader.php

require_once __DIR__ . '/../includes/bootstrap.php';

// Cargar rutas
$routes = require __DIR__ . '/../config/routes.php';

// Obtener la sección solicitada y limpiarla
$section = $_GET['section'] ?? 'main';

// 1. Limpieza básica (quitar barras al inicio/final y posibles prefijos "s/")
$cleanSection = preg_replace('#^s/#', '', $section);
$cleanSection = trim($cleanSection, '/');

$matchedFile = null;
$routeParams = []; 

// 2. BÚSQUEDA DE RUTA UNIFICADA
// A) Primero buscamos coincidencia exacta (ej: "main" o "settings/profile")
if (array_key_exists($cleanSection, $routes)) {
    $matchedFile = $routes[$cleanSection];
} 
// B) Si no es exacta, buscamos si coincide con alguna ruta base (Dinámica)
else {
    foreach ($routes as $routeKey => $filePath) {
        // Verificamos si la sección solicitada EMPIEZA con esta ruta clave seguida de una barra
        // Ejemplo: "channel/upload/123" empieza por "channel/upload/"
        if (strpos($cleanSection, $routeKey . '/') === 0) {
            $matchedFile = $filePath;
            
            // Extraemos lo que sobra de la URL y lo guardamos como UUID
            // Ejemplo: de "channel/upload/123" quitamos "channel/upload/" y queda "123"
            $remaining = substr($cleanSection, strlen($routeKey) + 1);
            $remaining = trim($remaining, '/'); // Limpieza extra
            
            if (!empty($remaining)) {
                $routeParams['uuid'] = $remaining;
            }
            
            break; // ¡Encontrado! Dejamos de buscar
        }
    }
}

if ($matchedFile && file_exists($matchedFile)) {
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