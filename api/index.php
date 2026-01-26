<?php
// api/index.php

// 1. Cargar el mapa de rutas
$routes = require_once __DIR__ . '/route-map.php';

// 2. Obtener la ruta solicitada desde el POST
$requestedRoute = $_POST['route'] ?? '';

// 3. Validar si la ruta existe en el mapa
if (array_key_exists($requestedRoute, $routes)) {
    $config = $routes[$requestedRoute];
    
    $targetFile = $config['file'];
    $targetAction = $config['action'];

    // 4. Seguridad: Verificar que el archivo existe
    if (file_exists(__DIR__ . '/' . $targetFile)) {
        
        // 5. Inyectar la acción en $_POST para que el handler antiguo funcione sin cambios
        $_POST['action'] = $targetAction;

        // 6. Cargar el handler original
        // El handler original hará sus propios require (db, utils) y session_start
        require __DIR__ . '/' . $targetFile;
        exit;
    }
}

// Si llegamos aquí, la ruta no es válida
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid API Route']);
exit;
?>