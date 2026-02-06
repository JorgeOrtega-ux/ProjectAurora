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

// === PREGUNTAMOS AL PORTERO ===
$decision = Gatekeeper::check($section, $pdo);

switch ($decision['action']) {
    case Gatekeeper::SHOW_MAINTENANCE:
        $isMaintenanceContext = true;
        include __DIR__ . '/../includes/sections/system/status-screen.php';
        exit;

    case Gatekeeper::REDIRECT:
        // En AJAX no podemos usar header('Location'), enviamos script o 401
        http_response_code(401);
        $target = $decision['target'];
        echo "<script>window.location.href = '{$basePath}{$target}';</script>";
        exit;

    case Gatekeeper::SHOW_LOCK:
        // Cargamos el bloqueo inmediatamente
        include __DIR__ . '/../includes/sections/system/security-lock.php';
        exit;

    case Gatekeeper::SHOW_404:
        $section = '404'; // Dejamos que el flujo continúe para cargar el 404 abajo
        break;
        
    case Gatekeeper::ALLOW:
        // Todo bien, continuamos con la carga normal
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
    // Si el archivo físico no existe (aunque esté en rutas), cargamos la vista 404
    $file404 = $routes['404'];
    
    if (file_exists($file404)) {
        include $file404;
    } else {
        // Fallback de emergencia solo si el propio archivo 404 fue borrado
        http_response_code(500);
        echo "<div class='component-layout-centered'>
                <h1 class='component-page-title'>Error Crítico</h1>
                <p class='component-page-description'>No se pudo cargar el contenido solicitado ni la página de error.</p>
              </div>";
    }
}
?>