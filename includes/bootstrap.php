<?php
// includes/bootstrap.php

// 1. Carga del Autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Carga de Variables de Entorno (.env)
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

// Establecer la Zona Horaria de PHP
$timezone = $_ENV['APP_TIMEZONE'] ?? 'America/Mexico_City';
date_default_timezone_set($timezone);

// 3. Configuración de Redis (Tolerante a Fallos)
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = $_ENV['REDIS_PORT'] ?? 6379;
$redisPass = $_ENV['REDIS_PASSWORD'] ?? null;

$redisConfig = [
    'scheme' => 'tcp',
    'host'   => $redisHost,
    'port'   => $redisPort,
    'timeout'=> 2.0, // Importante: Timeout corto para no colgar la web si Redis muere
];

if (!empty($redisPass)) {
    $redisConfig['password'] = $redisPass;
}

$redis = null; // Inicializamos como null por defecto

try {
    // Intentamos conectar
    $client = new Predis\Client($redisConfig);
    $client->connect(); // Forzamos conexión para verificar estado
    $redis = $client;
} catch (Exception $e) {
    // [FALLBACK ACTIVADO] 
    // No matamos la app con die(). Solo logueamos y seguimos sin Redis.
    error_log("ADVERTENCIA: Redis no disponible. El sistema usará la base de datos como respaldo. Error: " . $e->getMessage());
    $redis = null;
}

// 4. Gestión de Sesiones (Híbrida)
if ($redis) {
    // Si Redis está vivo, lo usamos para sesiones (Más rápido)
    try {
        $sessionHandler = new Predis\Session\Handler($redis, ['gc_maxlifetime' => 86400]);
        $sessionHandler->register();
    } catch (Exception $e) {
        error_log("Fallo al registrar Session Handler de Redis. Usando almacenamiento nativo (archivos).");
        // PHP usará automáticamente 'files' si esto falla
    }
} else {
    // Si Redis está muerto, PHP usará el manejador por defecto (archivos en disco)
    // Esto permite que los usuarios sigan logueados o puedan loguearse
    ini_set('session.save_handler', 'files');
    // Opcional: Definir ruta si es necesario, por defecto usa la del sistema
    // session_save_path(__DIR__ . '/../storage/sessions'); 
}

// 5. Configuración de Cookies de Sesión
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 86400, // 1 día
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

// 7. Carga de Utilidades y Manejo de Errores
require_once __DIR__ . '/libs/Utils.php';

// Inyectar Redis (puede ser null, Utils::setRedis lo soporta)
Utils::setRedis($redis);
Utils::initErrorHandlers();

// 8. Conexión a Base de Datos (MySQL) - CRÍTICO (Si esto falla, la app sí debe morir)
require_once __DIR__ . '/../config/database/db.php';

// 9. Inicializar I18n
$i18n = Utils::initI18n();

// 10. Retornar servicios globales
return [
    'pdo'   => $pdo,
    'i18n'  => $i18n,
    'redis' => $redis // Será null si falló la conexión
];
?>