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

// AQUI ESTÁ LA SOLUCIÓN: Se agregó https://unpkg.com a connect-src
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://ui-avatars.com; connect-src 'self' https://unpkg.com; frame-ancestors 'self';");

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

// --- CONEXIÓN A BASE DE DATOS Y CONFIGURACIÓN DEL SERVIDOR ---
try {
    $database = new Database();
    $dbConnection = $database->getConnection();
    
    // --- CARGAR CONFIGURACIÓN DINÁMICA DEL SERVIDOR ---
    global $APP_CONFIG;
    $APP_CONFIG = [];
    try {
        $stmt = $dbConnection->query("SELECT setting_key, setting_value FROM server_config");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $APP_CONFIG[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        // Ignorar el error si la tabla aún no existe al momento de iniciar por primera vez
    }
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

// --- SISTEMA DE INTERNACIONALIZACIÓN (i18n) ---
$auroraLang = $_COOKIE['aurora_lang'] ?? 'en-us'; 
$fileName = ($auroraLang === 'es-latam') ? 'es-419' : $auroraLang; 
$translations = [];
$langFile = __DIR__ . '/../../translations/' . $fileName . '.json';

if (file_exists($langFile)) {
    $translations = json_decode(file_get_contents($langFile), true);
}

// Helper global para vistas y controladores con reemplazo dinámico
if (!function_exists('t')) {
    function t($key, $replacements = null) {
        global $translations, $APP_CONFIG;
        
        $text = $translations[$key] ?? $key;
        
        $data = $replacements ?? $APP_CONFIG ?? [];
        if (is_array($data) && !empty($data)) {
            foreach ($data as $search => $replace) {
                if (is_scalar($replace)) {
                    $text = str_replace('{' . $search . '}', (string)$replace, $text);
                }
            }
        }
        return $text;
    }
}
?>