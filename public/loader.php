<?php
// public/loader.php
session_start();

require_once __DIR__ . '/../includes/libs/Utils.php';
Utils::initErrorHandlers(); // <--- AGREGAR ESTA LÍNEA JUSTO AQUÍ
require_once __DIR__ . '/../config/database/db.php';

// === BASEPATH ===
$basePath = '/ProjectAurora/'; 

// === I18n desde Utils ===
$i18n = Utils::initI18n();

// Definimos qué secciones son públicas
$publicSections = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verification-account',
    'recover-password',
    'reset-password',
    '404'
];

$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?');

// VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['user_id']) && !in_array($section, $publicSections)) {
    http_response_code(401);
    echo "<div class='auth-container'><p>" . $i18n->t('errors.session_expired') . "</p></div>";
    exit;
}

// === Carga de datos de usuario y Avatar desde Utils ===
$userRole = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);
$globalAvatarSrc = Utils::getGlobalAvatarSrc();

// Cargamos las rutas
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