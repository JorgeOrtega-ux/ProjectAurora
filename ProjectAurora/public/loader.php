<?php
// public/loader.php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Seguridad básica
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('No autorizado');
}

require_once __DIR__ . '/../config/database.php';

// 2. Recibir qué sección queremos cargar
$section = $_GET['section'] ?? '';

// 3. Limpieza básica de la ruta (evitar subir directorios con ../)
$section = str_replace(['.', '/'], '', $section); // Solo permitimos nombres de archivo simples por seguridad básica aquí, o ajustamos lógica
// MEJOR: Permitimos rutas relativas controladas.
$fileMap = [
    'main' => 'app/main',
    'explorer' => 'app/explorer',
    'search' => 'app/search-results', // Mapeamos 'search' al archivo real
    'settings-your-profile' => 'settings/your-profile',
    'settings-login-security' => 'settings/login-security',
    'settings-accessibility' => 'settings/accessibility',
    // Agrega aquí otros si faltan
];

// Si la sección pedida existe en el mapa, definimos la ruta
if (array_key_exists($section, $fileMap)) {
    $realFile = __DIR__ . '/../includes/sections/' . $fileMap[$section] . '.php';
} else {
    // Intento genérico para admin u otros (con cuidado)
    // Para este fix, nos centraremos en que funcione search
    $realFile = null;
}

// 4. Cargar el archivo si existe
if ($realFile && file_exists($realFile)) {
    include $realFile;
} else {
    http_response_code(404);
    echo '<div style="padding:20px; text-align:center;">Sección no encontrada (Loader).</div>';
}
?>