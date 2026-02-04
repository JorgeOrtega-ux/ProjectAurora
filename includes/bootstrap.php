<?php
// includes/bootstrap.php

// 1. Carga del Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Carga de Variables de Entorno
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
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

$redisConfig = [
    'scheme' => 'tcp',
    'host'   => $redisHost,
    'port'   => $redisPort,
    'timeout'=> 2.0, 
];

if (!empty($redisPass)) {
    $redisConfig['password'] = $redisPass;
}

$redis = null; 

try {
    $client = new Predis\Client($redisConfig);
    $client->connect(); 
    $redis = $client;
} catch (Exception $e) {
    error_log("ADVERTENCIA: Redis no disponible. Fallback activo. Error: " . $e->getMessage());
    $redis = null;
}

// 4. Gestión de Sesiones
if ($redis) {
    try {
        $sessionHandler = new Predis\Session\Handler($redis, ['gc_maxlifetime' => 86400]);
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

// 7. Utilidades
require_once __DIR__ . '/libs/Utils.php';
Utils::setRedis($redis);
Utils::initErrorHandlers();

// 8. [REFACTORIZADO] Conexión BD Controlada
// Aquí decidimos cómo morir si la BD falla (JSON vs HTML)
try {
    require_once __DIR__ . '/../config/database/db.php';
} catch (Exception $e) {
    // A) Si tenemos Logger, usamos Logger. Si no (porque falló carga), usamos error_log nativo.
    if (class_exists('Logger')) {
        Logger::db("Database Connection Critical Failure", ['msg' => $e->getMessage()]);
    } else {
        error_log("CRITICAL BOOTSTRAP DB ERROR: " . $e->getMessage());
    }

    // B) Detección de Contexto (API vs Navegador)
    $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) || 
             (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

    if (!headers_sent()) {
        http_response_code(500);
    }

    if ($isApi) {
        // Respuesta JSON para el Frontend (evita que JS explote al parsear)
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'El servicio de base de datos no está disponible temporalmente.',
            'error_code' => 'DB_CONN_FAIL'
        ]);
    } else {
        // Respuesta HTML para el usuario
        echo '<!DOCTYPE html>
        <html>
        <head><title>Error de Servicio</title><style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f9f9f9;color:#333;}</style></head>
        <body>
            <h1 style="color:#d32f2f;">Servicio Interrumpido</h1>
            <p>No se pudo establecer conexión con la base de datos principal.</p>
            <p>El equipo técnico ha sido notificado. Por favor intenta de nuevo en unos minutos.</p>
        </body>
        </html>';
    }
    
    // Detener ejecución totalmente
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