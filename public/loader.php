<?php
session_start();

// SEGURIDAD: Si no hay sesión, denegar acceso.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Acceso denegado");
}

// Mapeo de rutas permitidas a archivos físicos
$sections = [
    'main'     => '../includes/sections/main.php',
    'explorer' => '../includes/sections/explorer.php',
    '404'      => '../includes/sections/404.php'
];

$section = $_GET['section'] ?? 'main';

if (!array_key_exists($section, $sections)) {
    $section = '404';
}

$file = $sections[$section];

if (file_exists($file)) {
    include $file;
} else {
    echo "Error: Archivo de contenido no encontrado.";
}
?>