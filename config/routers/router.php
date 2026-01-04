<?php
// config/routers/router.php

// Definir la ruta base del proyecto
// IMPORTANTE: Cambia '/ProjectAurora/' si tu carpeta tiene otro nombre o si está en la raíz '/'
$basePath = '/ProjectAurora/'; 

// Obtener la URL solicitada
$requestUri = $_SERVER['REQUEST_URI'];

// Limpiar la URL para obtener solo la ruta relativa (quitando base path y query params)
$path = str_replace($basePath, '', $requestUri);
$path = strtok($path, '?'); // Quitar query string (?id=1...)
$path = trim($path, '/'); // Quitar slashes al inicio y final

// Si la ruta está vacía, es el home (main)
$routeRequest = $path ?: 'main';

// Mapa de Rutas (Lo cargamos del archivo de configuración)
$routes = require __DIR__ . '/../routes.php';

$matchedRoute = null;
$params = [];

// Validar si la ruta existe en el mapa (Lógica con soporte Regex)
if (array_key_exists($routeRequest, $routes)) {
    // 1. Coincidencia Exacta (ej. 'login', 'main')
    $matchedRoute = $routes[$routeRequest];
} else {
    // 2. Búsqueda por Patrones (ej. 'whiteboard/uuid')
    foreach ($routes as $routePattern => $file) {
        // Convertimos la llave del array en un Regex válido
        // Escapamos los slashes del patrón, pero permitimos el regex interno (ej. [a-z0-9-]+)
        // Usamos '#' como delimitador para evitar conflictos con '/'
        $regex = "#^" . str_replace('/', '\/', $routePattern) . "$#";
        
        if (preg_match($regex, $routeRequest, $matches)) {
            $matchedRoute = $file;
            // Guardamos los parámetros capturados (el UUID estará en $matches[1])
            array_shift($matches); // Quitar el match completo (índice 0)
            $params = $matches;
            break;
        }
    }
}

// Despachar la ruta
if ($matchedRoute && file_exists($matchedRoute)) {
    // Hacemos disponibles los parámetros para la vista
    // Ej: $_ROUTE_PARAMS[0] será el UUID
    $_ROUTE_PARAMS = $params; 
    
    // (Opcional) Asignar variable $uuid directamente si es el primer parámetro
    if (!empty($params)) {
        $uuid = $params[0] ?? null;
    }

    require_once $matchedRoute;
} else {
    // Si no existe, mandamos a 404
    require_once __DIR__ . '/../includes/sections/system/404.php';
}
?>