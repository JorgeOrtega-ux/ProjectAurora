<?php
// public/loader.php

// 1. Configuración Básica
define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');

// IMPORTANTE: Ajuste de ruta base para subcarpeta
$basePath = '/ProjectAurora/'; 

// 2. Obtener qué sección se pide
$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?'); // Limpiar query params

// 3. Cargar Mapa de Rutas
// Asegúrate de haber actualizado config/routes.php con las nuevas rutas (includes/sections/app/...)
$routes = require CONFIG_PATH . '/routes.php';

// 4. Validar existencia
$fileToLoad = $routes[$section] ?? $routes['404'];

// 5. Output Buffering (Capturar el HTML)
ob_start();

if (file_exists($fileToLoad)) {
    include $fileToLoad;
} else {
    // Fallback simple si el archivo físico no existe
    echo "<h1>Error 404</h1><p>El archivo de contenido no se encuentra (Loader).</p>";
}

$contentHtml = ob_get_clean();

// 6. Respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'section' => $section,
    'content' => $contentHtml,
    'title'   => ucfirst($section) . ' - Project Aurora' 
]);
?>