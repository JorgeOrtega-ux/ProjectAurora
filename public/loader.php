<?php
// public/loader.php

// CORRECCIÓN 1: Ruta correcta hacia db.php en config/database
require_once __DIR__ . '/../config/database/db.php';

// CORRECCIÓN 2: Cargar el sistema de traducciones (i18n).
// Es vital porque loader.php se ejecuta independientemente de index.php en las peticiones AJAX.
require_once __DIR__ . '/../config/helpers/i18n.php';

// Detectar idioma para cargar las traducciones (igual que en router.php)
// Intentamos usar la sesión si existe, o el navegador.
if (session_status() === PHP_SESSION_NONE) session_start();

$langToLoad = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $langToLoad = $stmt->fetchColumn();
    } catch(Exception $e){}
}
if (!$langToLoad) {
    $langToLoad = detect_browser_language();
}
load_translations($langToLoad);


// SEGURIDAD: Si no hay sesión, denegar acceso.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Acceso denegado");
}

// Mapeo de rutas permitidas a archivos físicos
$sections = [
    'main'     => '../includes/sections/app/main.php', // Ojo: Verifica si moviste main.php a sections/app/ o sigue en sections/
    'explorer' => '../includes/sections/app/explorer.php',
    '404'      => '../includes/sections/system/404.php',
    'recover-password' => '../includes/sections/auth/recover-password.php',
    
    // --- SECCIONES DE SETTINGS ---
    'settings/your-profile'       => '../includes/sections/settings/your-profile.php',
    'settings/login-and-security' => '../includes/sections/settings/login-security.php',
    'settings/accessibility'      => '../includes/sections/settings/accessibility.php'
];

// NOTA: He ajustado 'main', 'explorer' y '404' arriba basándome en tu lista de archivos.
// Si 'main.php' está directo en 'includes/sections/', cambia la ruta de arriba.
// Según tus archivos subidos: 
// includes/sections/app/main.php (CORRECTO)
// includes/sections/app/explorer.php (CORRECTO)
// includes/sections/system/404.php (CORRECTO)

$section = $_GET['section'] ?? 'main';

if (!array_key_exists($section, $sections)) {
    $section = '404';
}

$file = $sections[$section];

// Verificación simple de existencia antes de incluir
if (file_exists(__DIR__ . '/' . $file)) {
    include __DIR__ . '/' . $file;
} else {
    echo '<div class="component-header-card"><h1>Sección no encontrada</h1><p>El archivo ' . htmlspecialchars($section) . ' no se encuentra.</p></div>';
}
?>