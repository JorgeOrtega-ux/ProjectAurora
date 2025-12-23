<?php
// public/loader.php
session_start();

// Iniciar I18n
require_once __DIR__ . '/../includes/libs/I18n.php';

// Obtenemos el idioma de la sesión o usamos el default
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$i18n = new I18n($userLang);
// ==============================================

// Definimos qué secciones son públicas (se pueden ver sin login)
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
// Limpiamos posibles parámetros extra de la URL
$section = strtok($section, '?');

// VERIFICACIÓN DE SEGURIDAD
// Si el usuario NO está logueado Y la sección NO es pública -> Bloquear
if (!isset($_SESSION['user_id']) && !in_array($section, $publicSections)) {
    http_response_code(401);
    echo "<div class='auth-container'><p>" . $i18n->t('errors.session_expired') . "</p></div>";
    exit;
}

// Cargamos las rutas
$routes = require __DIR__ . '/../config/routes.php';

if (array_key_exists($section, $routes)) {
    $file = $routes[$section];
} else {
    $file = $routes['404'];
}

if (file_exists($file)) {
    // Al incluir el archivo aquí, heredará la variable $i18n definida arriba
    include $file;
} else {
    echo "<h1>Error 500</h1><p>" . $i18n->t('errors.server_error') . "</p>";
}
?>