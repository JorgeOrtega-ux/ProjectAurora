<?php
// api/index.php

// 1. Cargar el Bootstrap (Entorno, Sesiones Seguras, Base de Datos y Utilidades)
require_once __DIR__ . '/../includes/core/bootstrap.php';

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
        Utils::sendResponse(['success' => false, 'message' => 'Error interno: Handler no encontrado.'], 500);
    }
} else {
    Utils::sendResponse(['success' => false, 'message' => 'Ruta de API no encontrada: ' . htmlspecialchars($endpoint)], 404);
}
?>