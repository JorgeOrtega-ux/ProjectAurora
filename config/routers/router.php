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

// 1. Canal - Contenido (My Content)
if (strpos($path, 's/channel/my-content/') === 0) {
    // Explotamos la ruta: s / channel / my-content / {uuid}
    $segments = explode('/', $path);
    
    if (isset($segments[3])) {
        $routeParams['uuid'] = $segments[3];
        $currentSection = 'channel/my-content'; 
    }
}

// 2. Canal - Subir Video (Upload) [NUEVO]
if (strpos($path, 's/channel/upload/') === 0) {
    // Explotamos la ruta: s / channel / upload / {uuid}
    $segments = explode('/', $path);
    
    if (isset($segments[3])) {
        $routeParams['uuid'] = $segments[3];
        $currentSection = 'channel/upload'; // Clave interna para routes.php
    }
}

// Mapa de Rutas
$routes = require __DIR__ . '/../routes.php';

// [CORRECCIÓN] Normalización de rutas
// Primero intentamos buscar la ruta tal cual viene
if (array_key_exists($currentSection, $routes)) {
    // Coincidencia exacta
} 
// Si no, intentamos quitarle el prefijo 's/'
elseif (strpos($currentSection, 's/') === 0) {
    $cleanPath = preg_replace('#^s/#', '', $currentSection);
    
    if (array_key_exists($cleanPath, $routes)) {
        $currentSection = $cleanPath;
    } else {
        $currentSection = '404';
    }
} 
// [NUEVO] Permitir paso de rutas de canales 'c/UUID'
elseif (strpos($currentSection, 'c/') === 0) {
    // Dejamos pasar la ruta intacta (ej: c/uuid...) para que routing-logic.php 
    // la capture con su expresión regular y asigne la vista 'channel-profile'.
}
else {
    // Si no existe de ninguna forma, mandamos a 404
    $currentSection = '404';
}