<?php
// config/routers/router.php

// Definimos la ruta base de tu proyecto
$basePath = '/ProjectAurora/'; 

// Análisis de la URL
$requestUri = $_SERVER['REQUEST_URI'];

// Quitamos la base path de la URI para obtener la sección
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

// Limpiamos parámetros GET y slashes finales
$path = strtok($path, '?');
$currentSection = rtrim($path, '/');

// Si está vacío, es la página principal
if ($currentSection === '') { 
    $currentSection = 'main'; 
}

// Mapa de rutas permitidas (Validación simple)
$routes = require __DIR__ . '/../routes.php';

if (!array_key_exists($currentSection, $routes)) {
    $currentSection = '404'; 
}
?>