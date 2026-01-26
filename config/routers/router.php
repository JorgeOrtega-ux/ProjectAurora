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
$currentSection = $path ?: 'main';

// Mapa de Rutas (Lo cargamos del archivo de configuración)
$routes = require __DIR__ . '/../routes.php';

// Validar si la ruta existe en el mapa
if (array_key_exists($currentSection, $routes)) {
    // Si existe, la usamos
    // (La lógica de carga del archivo se hace en index.php)
} else {
    // Si no existe, mandamos a 404
    $currentSection = '404';
}
?>