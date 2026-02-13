<?php
// includes/bootstrap.php

// 1. Carga del Autoloader (Composer)
// [CRÍTICO] Esto permite cargar clases como Aurora\Services\InteractionService automáticamente
require_once __DIR__ . '/../vendor/autoload.php';

// === IMPORTACIONES (Namespaces) ===
use Aurora\Libs\Utils;
use Aurora\Libs\Logger;
use Predis\Client;
use Predis\Session\Handler;

// 2. Carga de Variables de Entorno (.env)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        // [MEJORA] Limite de explosión a 2 para evitar romper valores con '=' (ej. base64)
        list($name, $value) = explode('=', $line, 2) + [NULL, NULL]; 
        
        if ($name) {
            $name = trim($name);
            $value = trim($value ?? '');
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Zona Horaria
$timezone = $_ENV['APP_TIMEZONE'] ?? 'America/Mexico_City';
date_default_timezone_set($timezone);

// 3. Configuración de Redis
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = $_ENV['REDIS_PORT'] ?? 6379;
$redisPass = $_ENV['REDIS_PASSWORD'] ?? null;

// Configuración de conexión (TCP o TLS según entorno)
$redisConfig = [
    'scheme'   => getenv('REDIS_SCHEME') ?: 'tls',
    'host'     => $redisHost,
    'port'     => $redisPort,
    'timeout'  => 2.0,
    'read_write_timeout' => 2.0, // [MEJORA] Timeout de lectura/escritura
    'ssl'      => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];

if (!empty($redisPass)) {
    $redisConfig['password'] = $redisPass;
}

$redis = null; 

try {
    $client = new Client($redisConfig);
    $client->connect(); 
    $redis = $client;
} catch (Exception $e) {
    error_log("ADVERTENCIA: Redis no disponible. Fallback activo. Error: " . $e->getMessage());
    $redis = null;
}

// 4. Gestión de Sesiones
if ($redis) {
    try {
        $sessionHandler = new Handler($redis, ['gc_maxlifetime' => 86400]);
        $sessionHandler->register();
    } catch (Exception $e) {
        error_log("Fallo al registrar Session Handler de Redis.");
    }
} else {
    ini_set('session.save_handler', 'files');
}

// 5. Cookies
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => $cookieParams['domain'],
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

// 6. Iniciar Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 7. Utilidades e Inicialización
Utils::setRedis($redis);
Utils::initErrorHandlers();

// 8. Conexión BD Controlada
try {
    require_once __DIR__ . '/../config/database/db.php';
} catch (Exception $e) {
    if (class_exists(Logger::class)) {
        Logger::db("Database Connection Critical Failure", ['msg' => $e->getMessage()]);
    } else {
        error_log("CRITICAL BOOTSTRAP DB ERROR: " . $e->getMessage());
    }

    $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) || 
             (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

    if (!headers_sent()) {
        http_response_code(500);
    }

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'El servicio de base de datos no está disponible temporalmente.',
            'error_code' => 'DB_CONN_FAIL'
        ]);
    } else {
        echo 'Error crítico de conexión a base de datos.';
    }
    
    exit;
}

// 9. I18n
$i18n = Utils::initI18n();

// 10. Retorno de Servicios
return [
    'pdo'   => $pdo,
    'i18n'  => $i18n,
    'redis' => $redis
];
?>