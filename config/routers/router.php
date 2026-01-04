<?php
// config/routers/router.php

// Definir la ruta base del proyecto
$basePath = '/ProjectAurora/'; 

// Obtener la URL solicitada
$requestUri = $_SERVER['REQUEST_URI'];

// Limpiar la URL para obtener solo la ruta relativa
$path = str_replace($basePath, '', $requestUri);
$path = strtok($path, '?'); // Quitar query string
$path = trim($path, '/');   // Quitar slashes

// Si la ruta está vacía, es el home (main)
$currentSection = $path ?: 'main';

// --- NUEVA LÓGICA PARA RUTAS DINÁMICAS ---
// Detectamos si la ruta comienza con "whiteboard/"
if (strpos($currentSection, 'whiteboard/') === 0) {
    // Extraemos el UUID para que esté disponible globalmente si se necesita
    $parts = explode('/', $currentSection);
    $dynamicUuid = $parts[1] ?? ''; 
    
    // Forzamos la sección a 'whiteboard' para que coincida con el mapa de rutas
    $currentSection = 'whiteboard';
}
// -----------------------------------------

// Mapa de Rutas (Lo cargamos del archivo de configuración)
$routes = require __DIR__ . '/../routes.php';

// Validar si la ruta (ahora normalizada) existe en el mapa
if (!array_key_exists($currentSection, $routes)) {
    // Si no existe, mandamos a 404
    $currentSection = '404';
}
?>