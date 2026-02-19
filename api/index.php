<?php
// api/index.php
session_start();
header("Content-Type: application/json; charset=UTF-8");

$routeMap = require __DIR__ . '/route-map.php';
$endpoint = isset($_GET['endpoint']) ? rtrim($_GET['endpoint'], '/') : '';

if (array_key_exists($endpoint, $routeMap)) {
    $routeConfig = $routeMap[$endpoint];
    $handlerFile = __DIR__ . '/' . $routeConfig['file'];
    
    // Inyectamos la acción para que el handler sepa qué hacer
    $action = $routeConfig['action']; 

    if (file_exists($handlerFile)) {
        require_once $handlerFile;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error interno: Handler no encontrado.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ruta de API no encontrada: ' . htmlspecialchars($endpoint)]);
}
?>