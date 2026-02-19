<?php
// api/index.php

// 1. Cargar el Bootstrap (Entorno, Sesiones Seguras, Base de Datos y Composer Autoloader)
require_once __DIR__ . '/../includes/core/Bootstrap.php';

use App\Core\Utils;

header("Content-Type: application/json; charset=UTF-8");

$routeMap = require __DIR__ . '/route-map.php';
$endpoint = isset($_GET['endpoint']) ? rtrim($_GET['endpoint'], '/') : '';

if (array_key_exists($endpoint, $routeMap)) {
    $routeConfig = $routeMap[$endpoint];
    $handlerFile = __DIR__ . '/' . $routeConfig['file'];
    
    // Inyectamos la acción para que el handler sepa qué hacer
    $action = $routeConfig['action']; 

    if (file_exists($handlerFile)) {
        // El handler ahora retorna una función, la cual guardamos
        $handler = require $handlerFile;
        
        // Ejecutamos la función inyectando las dependencias necesarias
        $handler($dbConnection, $action);
    } else {
        Utils::sendResponse(['success' => false, 'message' => 'Error interno: Handler no encontrado.'], 500);
    }
} else {
    Utils::sendResponse(['success' => false, 'message' => 'Ruta de API no encontrada: ' . htmlspecialchars($endpoint)], 404);
}
?>