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

// [MODIFICADO] Lógica de Rutas Dinámicas para Creator Studio
// Captura: s/channel/{view}/{uuid}
// Views permitidas: panel-control, manage-content, upload
if (preg_match('#^s/channel/(panel-control|manage-content|upload)/([a-f0-9\-]+)$#', $path, $matches)) {
    // Si coincide, forzamos la sección interna al layout de studio
    $currentSection = 'studio/layout';
    
    // Variables globales para que el layout.php las use
    $studioView = $matches[1]; // 'panel-control', 'manage-content' o 'upload'
    $targetUuid = $matches[2]; // el uuid
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