<?php
// public/loader.php

// Cargamos las rutas
$routes = require __DIR__ . '/../config/routes.php';

// Obtenemos la sección solicitada
$section = $_GET['section'] ?? 'main';

// Limpieza básica
$section = strtok($section, '?');

// Buscamos el archivo físico
if (array_key_exists($section, $routes)) {
    $file = $routes[$section];
} else {
    $file = $routes['404'];
}

// Si el archivo existe, lo incluimos (y solo él)
if (file_exists($file)) {
    include $file;
} else {
    echo "<h1>Error 500</h1><p>El archivo de la sección no se encuentra.</p>";
}
?>