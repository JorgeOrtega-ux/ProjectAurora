<?php
// public/loader.php

$services = require_once __DIR__ . '/../includes/bootstrap.php';
extract($services); 

$basePath = '/ProjectAurora/'; 
$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?');

// === CARGA DE REGLAS DE SEGURIDAD ===
$securityRules = require __DIR__ . '/../config/security.php';
$authRoutes = $securityRules['auth_routes'];
$protectedRoutes = $securityRules['protected_routes'];

// === 1. VERIFICACIÓN DE MANTENIMIENTO ===
$maintenanceMode = Utils::getServerConfig($pdo, 'maintenance_mode', '0');
$userRole = $_SESSION['role'] ?? 'guest';
$allowedSystemRoles = ['founder', 'administrator', 'moderator'];

if ($maintenanceMode === '1' && !in_array($userRole, $allowedSystemRoles) && !in_array($section, $authRoutes)) {
    // [MODIFICADO] Cargar status-screen.php con bandera
    $isMaintenanceContext = true;
    include __DIR__ . '/../includes/sections/system/status-screen.php'; 
    exit;
}

// === 2. VERIFICACIÓN DE SESIÓN (MODO ABIERTO) ===
$isAdminRoute = strpos($section, 'admin/') === 0;

if (!isset($_SESSION['user_id'])) {
    if ($isAdminRoute || in_array($section, $protectedRoutes)) {
        http_response_code(401);
        echo "<div class='auth-container' style='padding: 24px; text-align: center;'>
                <span class='material-symbols-rounded' style='font-size: 48px; color: var(--text-secondary);'>lock</span>
                <h2 style='margin-top: 16px;'>" . $i18n->t('errors.access_denied') . "</h2>
                <p style='color: var(--text-secondary); margin-top: 8px;'>Debes iniciar sesión para ver esta sección.</p>
                <a href='".$basePath."login' class='component-button primary' style='margin-top: 16px; display: inline-flex;'>Iniciar Sesión</a>
              </div>";
        exit;
    }
}

// === 3. PROTECCIÓN ADMIN ===
$allowedAdminRoles = ['founder', 'administrator'];
if ($isAdminRoute) {
    if (!in_array($userRole, $allowedAdminRoles)) {
        $section = '404';
    }
}

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