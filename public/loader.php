<?php
// public/loader.php
session_start();

// Seguridad: Si no está logueado, prohibir acceso (Excepto si quisieras cargar login via ajax, 
// pero en nuestra lógica actual Login es carga completa)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<div class='auth-container'><p>Sesión expirada. Por favor recarga la página.</p></div>";
    exit;
}

// Cargamos las rutas
$routes = require __DIR__ . '/../config/routes.php';

$section = $_GET['section'] ?? 'main';
$section = strtok($section, '?');

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