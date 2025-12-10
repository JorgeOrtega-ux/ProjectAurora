<?php
// public/loader.php

require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../config/helpers/i18n.php';

// Detectar idioma
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
// MODIFICADO: Usamos el archivo de rutas centralizado
$sections = require __DIR__ . '/../config/routes.php';

$section = $_GET['section'] ?? 'main';

if (!array_key_exists($section, $sections)) {
    $section = '404';
}

$file = $sections[$section];

// Verificación simple de existencia antes de incluir
// Nota: Como las rutas en config/routes.php son absolutas (__DIR__), no necesitamos concatenar nada extra.
if (file_exists($file)) {
    include $file;
} else {
    echo '<div class="component-header-card"><h1>Sección no encontrada</h1><p>El archivo para la sección ' . htmlspecialchars($section) . ' no se encuentra.</p></div>';
}
?>