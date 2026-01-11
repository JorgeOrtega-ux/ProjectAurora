<?php
// public/loader.php

define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');
define('MENUS_PATH', PROJECT_ROOT . '/includes/menus');

// 1. BOOTSTRAP
require_once PROJECT_ROOT . '/includes/core/boot.php';

// 2. Obtener sección solicitada
$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?');

// --- SEGURIDAD: VERIFICACIÓN DE SESIÓN EN SPA ---
$protectedRoutes = [
    'settings/profile',
    'settings/security'
];
$isLoggedIn = isset($_SESSION['user_id']);

if (in_array($section, $protectedRoutes) && !$isLoggedIn) {
    // Si intenta cargar algo privado sin sesión, forzamos la carga de "login"
    // Esto mostrará el formulario de login al usuario inmediatamente.
    $section = 'login';
}
// ------------------------------------------------

$currentSection = $section; 

// 3. Cargar Rutas
$routes = require CONFIG_PATH . '/routes.php';
$fileToLoad = $routes[$section] ?? $routes['404'];

// 4. Determinar Contexto del Menú
$helpSections = ['help', 'privacy', 'terms', 'cookies', 'feedback'];
$settingsSections = ['settings/preferences', 'settings/profile', 'settings/security'];

$menuFile = 'app.php'; 
if (in_array($section, $helpSections)) {
    $menuFile = 'help.php';
} elseif (in_array($section, $settingsSections)) {
    $menuFile = 'settings.php';
}

// 5. Capturar HTML del Menú
ob_start();
if (file_exists(MENUS_PATH . '/' . $menuFile)) {
    include MENUS_PATH . '/' . $menuFile;
}
$menuHtml = ob_get_clean();

// 6. Capturar HTML del Contenido Principal
ob_start();
if (file_exists($fileToLoad)) {
    include $fileToLoad;
} else {
    echo "<h1>Error 404</h1><p>Archivo no encontrado.</p>";
}
$contentHtml = ob_get_clean();

// 7. Respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'section'  => $section,
    'content'  => $contentHtml,
    'menuHTML' => $menuHtml,
    'title'    => ucfirst($section) . ' - ' . __('app.title')
]);
?>