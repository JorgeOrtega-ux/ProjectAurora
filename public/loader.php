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
    '404'      => '../includes/sections/404.php',
    'recover-password' => '../includes/sections/recover-password.php',
    
    // --- SECCIONES DE SETTINGS ---
    'settings/your-profile'       => '../includes/sections/settings/your-profile.php',
    'settings/login-and-security' => '../includes/sections/settings/login-security.php',
    'settings/accessibility'      => '../includes/sections/settings/accessibility.php'
];

$section = $_GET['section'] ?? 'main';

if (!array_key_exists($section, $sections)) {
    $section = '404';
}

$file = $sections[$section];

// Verificación simple de existencia antes de incluir
if (file_exists($file)) {
    include $file;
} else {
    // Si el archivo físico no existe aún (durante desarrollo), mostramos un placeholder
    echo '<div class="component-header-card"><h1>Sección en construcción</h1><p>El archivo ' . htmlspecialchars($section) . ' no existe.</p></div>';
}
?>