<?php
// config/routers/router.php

// Definir la ruta base del proyecto
$basePath = '/ProjectAurora/'; 

// Obtener la URL solicitada
$requestUri = $_SERVER['REQUEST_URI'];

// Limpiar la URL para obtener solo la ruta relativa
$path = str_replace($basePath, '', $requestUri);
$path = strtok($path, '?'); 
$path = trim($path, '/'); 

$currentSection = $path ?: 'main';

// Variables para rutas dinámicas
$routeParams = [];

// === LÓGICA DE RUTAS DINÁMICAS ===
// Detectar si la ruta es "s/channel/my-content/{uuid}"
if (strpos($path, 's/channel/my-content/') === 0) {
    // Explotamos la ruta: s / channel / my-content / {uuid}
    $segments = explode('/', $path);
    
    // El UUID debería estar en el índice 3
    if (isset($segments[3])) {
        $routeParams['uuid'] = $segments[3];
        $currentSection = 'channel/my-content'; // Clave interna para routes.php
    }
}

// Mapa de Rutas
$routes = require __DIR__ . '/../routes.php';

// Validar si la ruta existe en el mapa
if (array_key_exists($currentSection, $routes)) {
    // Si existe, la usamos
} else {
    // Si no existe, mandamos a 404
    $currentSection = '404';
}
?>