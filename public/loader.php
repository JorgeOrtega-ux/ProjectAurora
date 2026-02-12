<?php
// public/loader.php

$services = require_once __DIR__ . '/../includes/bootstrap.php';
// [REFACTORIZADO] Asignación explícita para el contexto de las vistas
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

// Importamos el Portero Central
use Aurora\Core\Gatekeeper;
use Aurora\Libs\Utils;

$basePath = '/ProjectAurora/'; 
$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?');

// [NUEVO] Inicializamos routeParams para evitar errores en las vistas
$routeParams = [];

// =========================================================
// [MODIFICADO] INTERCEPTOR DE RUTAS DINÁMICAS
// =========================================================

// CASO 1: Studio (Panel de control, upload, etc)
if (preg_match('#^s/channel/(panel-control|manage-content|upload)/([a-f0-9\-]+)$#', $section, $matches)) {
    $section = 'studio/layout';
    $studioView = $matches[1]; 
    $targetUuid = $matches[2]; 
    $routeParams['uuid'] = $targetUuid; // Sincronizamos con routeParams
}

// CASO 2: Mi Contenido (La parte que te faltaba)
// Detectamos s/channel/my-content/UUID y lo enviamos a 'channel/my-content'
elseif (preg_match('#^s/channel/my-content/([a-f0-9\-]+)$#', $section, $matches)) {
    $section = 'channel/my-content'; // Clave correcta en routes.php
    $routeParams['uuid'] = $matches[1]; // Pasamos el UUID a la vista
}

// =========================================================

// === PREGUNTAMOS AL PORTERO ===
$decision = Gatekeeper::check($section, $pdo);

switch ($decision['action']) {
    case Gatekeeper::SHOW_MAINTENANCE:
        $isMaintenanceContext = true;
        include __DIR__ . '/../includes/sections/system/status-screen.php';
        exit;

    case Gatekeeper::REDIRECT:
        http_response_code(401);
        $target = $decision['target'];
        echo "<script>window.location.href = '{$basePath}{$target}';</script>";
        exit;

    case Gatekeeper::SHOW_LOCK:
        include __DIR__ . '/../includes/sections/system/security-lock.php';
        exit;

    case Gatekeeper::SHOW_404:
        $section = '404'; 
        break;
        
    case Gatekeeper::ALLOW:
        break;
}

$isLoggedIn = isset($_SESSION['user_id']);
$globalAvatarSrc = Utils::getGlobalAvatarSrc();

$routes = require __DIR__ . '/../config/routes.php';

if (array_key_exists($section, $routes)) {
    $file = $routes[$section];
} else {
    $file = $routes['404'];
}

// Lógica de carga robusta
if (file_exists($file)) {
    include $file;
} else {
    $file404 = $routes['404'];
    
    if (file_exists($file404)) {
        include $file404;
    } else {
        http_response_code(500);
        echo "<div class='component-layout-centered'>
                <h1 class='component-page-title'>Error Crítico</h1>
                <p class='component-page-description'>No se pudo cargar el contenido solicitado.</p>
              </div>";
    }
}
?>