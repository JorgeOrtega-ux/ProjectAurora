<?php
// public/loader.php
session_start();

// Definimos qué secciones son públicas (se pueden ver sin login)
$publicSections = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verification-account',
    '404'
];

$section = $_GET['section'] ?? 'main';
// Limpiamos posibles parámetros extra de la URL
$section = strtok($section, '?');

// VERIFICACIÓN DE SEGURIDAD
// Si el usuario NO está logueado Y la sección NO es pública -> Bloquear
if (!isset($_SESSION['user_id']) && !in_array($section, $publicSections)) {
    http_response_code(401);
    echo "<div class='auth-container'><p>Sesión expirada. Por favor recarga la página.</p></div>";
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
    include $file;
} else {
    echo "<h1>Error 500</h1><p>El archivo de la sección no se encuentra.</p>";
}
?>