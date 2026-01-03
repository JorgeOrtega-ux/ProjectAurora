<?php
// public/loader.php

$services = require_once __DIR__ . '/../includes/bootstrap.php';
extract($services); 

$basePath = '/ProjectAurora/'; 
$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?');

// === VERIFICACIÓN DE MANTENIMIENTO (LOADER) ===
$maintenanceMode = Utils::getServerConfig($pdo, 'maintenance_mode', '0');
$userRole = $_SESSION['role'] ?? 'guest';
$allowedRoles = ['founder', 'administrator', 'moderator'];

// LISTA BLANCA: Secciones que SIEMPRE se deben cargar, incluso en mantenimiento
$alwaysVisibleSections = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verification-account', 
    'recover-password', 
    'reset-password'
];

// Bloquear solo si: Mantenimiento activo Y No es staff Y No es una sección pública
if ($maintenanceMode === '1' && !in_array($userRole, $allowedRoles) && !in_array($section, $alwaysVisibleSections)) {
    include __DIR__ . '/maintenance.php';
    exit;
}
// ==============================================

// Definimos secciones públicas para el control de sesión
$publicRoutes = $alwaysVisibleSections; 
$publicRoutes[] = '404'; // Agregamos 404 a las públicas

// VERIFICACIÓN DE SEGURIDAD (Sesión expirada)
if (!isset($_SESSION['user_id']) && !in_array($section, $publicRoutes)) {
    http_response_code(401);
    echo "<div class='auth-container'><p>" . $i18n->t('errors.session_expired') . "</p></div>";
    exit;
}

$userRole = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);
$globalAvatarSrc = Utils::getGlobalAvatarSrc();

$routes = require __DIR__ . '/../config/routes.php';

if (array_key_exists($section, $routes)) {
    $file = $routes[$section];
} else {
    $file = $routes['404'];
}

if (file_exists($file)) {
    include $file;
} else {
    echo "<h1>Error 500</h1><p>" . $i18n->t('errors.server_error') . "</p>";
}
?>