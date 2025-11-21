<?php
// public/loader.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/utilities.php'; 

// 1. Seguridad básica
$publicSections = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'forgot-password', 
    'reset-password', // <--- AGREGADO
    'status-page',
    'login/verification-additional'
];

$section = $_GET['section'] ?? 'main';
$section = str_replace(['..', '.php'], '', $section);

// Verificar sesión para rutas privadas
if (!isset($_SESSION['user_id']) && !in_array($section, $publicSections)) {
    http_response_code(401);
    exit('<div style="padding:20px; text-align:center">Sesión expirada. Recarga la página.</div>');
}

// 2. Mapa COMPLETO de rutas
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
    'reset-password'                => 'auth/reset-password', // <--- AGREGADO

    // Settings
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
    $section = '404'; 
}

// 4. SIMULAR ROUTER
$CURRENT_SECTION = $section;
$basePath = '/ProjectAurora/'; 

// 5. Cargar el archivo
if ($realFile && file_exists($realFile)) {
    include $realFile;
} else {
    http_response_code(404);
    echo '<div style="padding:20px; text-align:center;">Archivo no encontrado en el servidor.</div>';
}
?>