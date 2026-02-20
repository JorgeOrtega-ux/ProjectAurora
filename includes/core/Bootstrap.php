<?php
// includes/core/Bootstrap.php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Utils;
use App\Config\Database;

try {
    Utils::loadEnv(__DIR__ . '/../../.env');
} catch (Exception $e) {
    die("Error crítico del sistema: No se pudo cargar el archivo de configuración de entorno.");
}

$appEnv = getenv('APP_ENV') ?: 'local';
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

if ($appEnv === 'production' && !$isSecure) {
    $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirectUrl);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://ui-avatars.com; connect-src 'self'; frame-ancestors 'self';");

if ($isSecure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

date_default_timezone_set('America/Mexico_City');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);

if ($appEnv === 'production' || $isSecure) {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- SISTEMA DE INTERNACIONALIZACIÓN (i18n) ---
$auroraLang = $_COOKIE['aurora_lang'] ?? 'en-us'; // Define el idioma por defecto si no hay cookie
$fileName = ($auroraLang === 'es-latam') ? 'es-419' : $auroraLang; 
$translations = [];
$langFile = __DIR__ . '/../../translations/' . $fileName . '.json';

// Si el archivo existe, lo cargamos. Si no, $translations se queda vacío.
if (file_exists($langFile)) {
    $translations = json_decode(file_get_contents($langFile), true);
}

// Helper global para vistas y controladores
if (!function_exists('t')) {
    function t($key) {
        global $translations;
        // Si el array está vacío o no encuentra la clave, imprime la clave directamente
        return $translations[$key] ?? $key;
    }
}

// --- CONEXIÓN A BASE DE DATOS ---
try {
    $database = new Database();
    $dbConnection = $database->getConnection();
} catch (PDOException $e) {
    $isApiRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) ||
                    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                    (isset($_SERVER['HTTP_X_SPA_REQUEST']));

    if ($isApiRequest) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => 'Error crítico: No se pudo establecer conexión con la base de datos.']);
        exit;
    } else {
        http_response_code(500);
        die("<div style='font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f5f5fa; margin: 0;'><div style='text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 400px;'><h1 style='color: #dc2626; font-size: 24px; margin-bottom: 12px;'>Error 500</h1><p style='color: #666; font-size: 15px; line-height: 1.5;'>Servicio no disponible temporalmente.</p></div></div>");
    }
}
?>