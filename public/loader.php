<?php
// public/loader.php

define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');
define('MENUS_PATH', PROJECT_ROOT . '/includes/menus'); // Nueva constante

// 1. Obtener sección
$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?');
// Variable normalizada para usar dentro de los partials
$currentSection = $section; 

// 2. Cargar Rutas de Contenido
$routes = require CONFIG_PATH . '/routes.php';
$fileToLoad = $routes[$section] ?? $routes['404'];

// 3. Determinar Contexto del Menú
$helpSections = ['help', 'privacy', 'terms', 'cookies', 'feedback'];
$settingsSections = ['settings/preferences'];

$menuFile = 'app.php'; // Default
if (in_array($section, $helpSections)) {
    $menuFile = 'help.php';
} elseif (in_array($section, $settingsSections)) {
    $menuFile = 'settings.php';
}

// 4. Capturar HTML del Menú (Aquí está la magia limpia)
ob_start();
if (file_exists(MENUS_PATH . '/' . $menuFile)) {
    include MENUS_PATH . '/' . $menuFile;
}
$menuHtml = ob_get_clean();

// 5. Capturar HTML del Contenido Principal
ob_start();
if (file_exists($fileToLoad)) {
    include $fileToLoad;
} else {
    echo "<h1>Error 404</h1><p>Archivo no encontrado.</p>";
}
$contentHtml = ob_get_clean();

// 6. Respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'section'  => $section,
    'content'  => $contentHtml,
    'menuHTML' => $menuHtml,
    'title'    => ucfirst($section) . ' - Project Aurora'
]);
?>