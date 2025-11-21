<?php
// public/loader.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/utilities.php'; // Útil para funciones globales si se necesitan

// 1. Seguridad básica: Si NO hay sesión y NO es una página pública, bloquear.
// Definimos qué secciones son públicas para el loader
$publicSections = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'forgot-password', 
    'status-page',
    'login/verification-additional'
];

$section = $_GET['section'] ?? 'main';
// Limpieza básica
$section = str_replace(['..', '.php'], '', $section);

// Verificar sesión para rutas privadas
if (!isset($_SESSION['user_id']) && !in_array($section, $publicSections)) {
    http_response_code(401);
    exit('<div style="padding:20px; text-align:center">Sesión expirada. Recarga la página.</div>');
}

// 2. Mapa COMPLETO de rutas (Clave URL => Ruta de Archivo relativa a includes/sections/)
$fileMap = [
    // App
    'main'      => 'app/main',
    'explorer'  => 'app/explorer',
    'search'    => 'app/search-results',

    // Auth
    'login'                         => 'auth/login',
    'login/verification-additional' => 'auth/login',
    'register'                      => 'auth/register',
    'register/additional-data'      => 'auth/register',
    'register/verification-account' => 'auth/register',
    'forgot-password'               => 'auth/forgot-password',

    // Settings (Aquí estaba tu error, faltaban claves)
    'settings'                  => 'settings/your-profile',
    'settings/your-profile'     => 'settings/your-profile',
    'settings/login-security'   => 'settings/login-security',
    'settings/accessibility'    => 'settings/accessibility',

    // Admin
    'admin'             => 'admin/dashboard',
    'admin/dashboard'   => 'admin/dashboard',
    'admin/users'       => 'admin/users',
    'admin/backups'     => 'admin/backups',
    'admin/server'      => 'admin/server',
    
    // System
    'status-page' => 'system/status-page',
    '404'         => 'system/404'
];

// 3. Verificar existencia en el mapa
if (array_key_exists($section, $fileMap)) {
    $realFile = __DIR__ . '/../includes/sections/' . $fileMap[$section] . '.php';
} else {
    $realFile = __DIR__ . '/../includes/sections/system/404.php';
    $section = '404'; // Forzar sección 404 para la UI
}

// 4. SIMULAR ROUTER
// Esto es crucial: tus archivos (header.php, sidebar, register.php) usan $CURRENT_SECTION
// para saber qué botón marcar como "active".
$CURRENT_SECTION = $section;

// Variable base path por si se necesita dentro de los includes
$basePath = '/ProjectAurora/'; 

// 5. Cargar el archivo
if ($realFile && file_exists($realFile)) {
    include $realFile;
} else {
    http_response_code(404);
    echo '<div style="padding:20px; text-align:center;">Archivo no encontrado en el servidor.</div>';
}
?>