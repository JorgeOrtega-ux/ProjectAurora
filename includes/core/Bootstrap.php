<?php
// includes/core/Bootstrap.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Logger.php'; // Cargamos el Logger inmediatamente

use App\Core\Utils;
use App\Config\Database;
use App\Core\Logger;

// --- MANEJADOR GLOBAL DE ERRORES Y EXCEPCIONES ---
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $level = Logger::LEVEL_WARNING;
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        $level = Logger::LEVEL_ERROR;
    }
    Logger::system("PHP Error [$errno]: $errstr in $errfile on line $errline", $level);
    return false; // Permitir que PHP continúe su flujo normal
});

set_exception_handler(function($e) {
    Logger::system("Uncaught Exception: " . $e->getMessage(), Logger::LEVEL_CRITICAL, $e);
});
// ------------------------------------------------

try {
    Utils::loadEnv(__DIR__ . '/../../.env');
} catch (\Exception $e) {
    Logger::system("No se pudo cargar el archivo .env", Logger::LEVEL_CRITICAL, $e);
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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://ui-avatars.com; connect-src 'self' https://unpkg.com; frame-src 'self'; frame-ancestors 'self';");
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
    } catch (\Throwable $e) {
        Logger::database("Fallo al cargar server_config. ¿Aún no se crean las tablas?", Logger::LEVEL_WARNING, $e);
    }
} catch (\Throwable $e) { 
    Logger::system("Fallo crítico en la inicialización (BD o Configuración)", Logger::LEVEL_CRITICAL, $e);

    $isApiRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) ||
                    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                    (isset($_SERVER['HTTP_X_SPA_REQUEST']));

    if ($isApiRequest) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => 'Error crítico: ' . $e->getMessage()]);
        exit;
    } else {
        http_response_code(500);
        die("<div style='font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f5f5fa; margin: 0;'><div style='text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 400px;'><h1 style='color: #dc2626; font-size: 24px; margin-bottom: 12px;'>Error 500</h1><p style='color: #666; font-size: 15px; line-height: 1.5;'>Servicio no disponible temporalmente. Detalles: " . htmlspecialchars($e->getMessage()) . "</p></div></div>");
    }
}

// --- MIDDLEWARE DE GESTIÓN DE DISPOSITIVOS Y ESTADO DE USUARIO ---
if (isset($_SESSION['user_id']) && isset($dbConnection)) {
    $currentSessionId = session_id();
    $userId = $_SESSION['user_id'];
    
    try {
        // Validación de sesión remota
        $stmt = $dbConnection->prepare("SELECT session_id FROM user_sessions WHERE session_id = :sid AND user_id = :uid LIMIT 1");
        $stmt->execute([':sid' => $currentSessionId, ':uid' => $userId]);
        
        $isApiRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) ||
                        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                        (isset($_SERVER['HTTP_X_SPA_REQUEST']));

        if ($stmt->rowCount() === 0) {
            Logger::system("Sesión revocada o inválida detectada y destruida. ID: $currentSessionId, User ID: $userId", Logger::LEVEL_INFO);
            session_unset();
            session_destroy();
            
            if ($isApiRequest) {
                header('X-SPA-Redirect: /ProjectAurora/login');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Sesión expirada o revocada desde otro dispositivo.']);
                exit;
            } else {
                header("Location: /ProjectAurora/login");
                exit;
            }
        } else {
            if (!isset($_SESSION['last_activity_update']) || (time() - $_SESSION['last_activity_update']) > 300) {
                $updStmt = $dbConnection->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = :sid");
                $updStmt->execute([':sid' => $currentSessionId]);
                $_SESSION['last_activity_update'] = time();
            }
        }

        // --- SINCRONIZACIÓN EN TIEMPO REAL: ACTUALIZAR DATOS DE USUARIO ---
        $stmtUser = $dbConnection->prepare("SELECT username, email, role, avatar_path, status FROM users WHERE id = :uid LIMIT 1");
        $stmtUser->execute([':uid' => $userId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userData || $userData['status'] !== 'active') {
            Logger::system("Usuario inactivo/suspendido/eliminado detectado en recarga. ID: $userId", Logger::LEVEL_INFO);
            session_unset();
            session_destroy();
            
            $statusMessage = ($userData && $userData['status'] === 'suspended') ? 'Tu cuenta ha sido suspendida.' : 'Tu cuenta ha sido eliminada o no existe.';
            
            if ($isApiRequest) {
                header('X-SPA-Redirect: /ProjectAurora/login');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => $statusMessage]);
                exit;
            } else {
                header("Location: /ProjectAurora/login");
                exit;
            }
        }

        // Mantener las sesiones actualizadas
        $_SESSION['user_name'] = $userData['username'];
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_role'] = $userData['role'];
        $_SESSION['user_avatar'] = $userData['avatar_path'];

        // Sincronizar Preferencias
        $stmtPrefs = $dbConnection->prepare("SELECT language FROM user_preferences WHERE user_id = :uid LIMIT 1");
        $stmtPrefs->execute([':uid' => $userId]);
        $prefData = $stmtPrefs->fetch(PDO::FETCH_ASSOC);
        if ($prefData && isset($prefData['language'])) {
            if (($_COOKIE['aurora_lang'] ?? '') !== $prefData['language']) {
                setcookie('aurora_lang', $prefData['language'], time() + 31536000, '/');
                $_COOKIE['aurora_lang'] = $prefData['language']; 
            }
        }

    } catch (\Throwable $e) {
        Logger::database("Fallo al validar la sesión o sincronizar datos de usuario", Logger::LEVEL_ERROR, $e);
    }
}
// ----------------------------------------------------------------

// --- SISTEMA DE INTERNACIONALIZACIÓN (i18n) ---
$auroraLang = $_COOKIE['aurora_lang'] ?? 'en-us'; 
$fileName = ($auroraLang === 'es-latam') ? 'es-419' : $auroraLang; 
$translations = [];
$langFile = __DIR__ . '/../../translations/' . $fileName . '.json';

if (file_exists($langFile)) {
    $translations = json_decode(file_get_contents($langFile), true);
} else {
    Logger::system("Archivo de idioma no encontrado: $langFile", Logger::LEVEL_WARNING);
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