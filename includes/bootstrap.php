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

// 3. Configuración de Redis
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = $_ENV['REDIS_PORT'] ?? 6379;
$redisPass = $_ENV['REDIS_PASSWORD'] ?? null;

$redisConfig = [
    'scheme' => 'tcp',
    'host'   => $redisHost,
    'port'   => $redisPort,
];

if (!empty($redisPass)) {
    $redisConfig['password'] = $redisPass;
}

try {
    $redis = new Predis\Client($redisConfig);
} catch (Exception $e) {
    error_log("Fallo crítico: No se pudo conectar a Redis. " . $e->getMessage());
    // Fallback silencioso o die() dependiendo de la severidad deseada
    die("Error interno del sistema de sesiones (Redis).");
}

// 4. Registrar Redis como Handler de Sesiones
// IMPORTANTE: Esto debe ocurrir ANTES de session_start()
$sessionHandler = new Predis\Session\Handler($redis, ['gc_maxlifetime' => 86400]);
$sessionHandler->register();

// 5. Configuración de Cookies de Sesión
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 86400, // 1 día
    'path'     => '/',
    'domain'   => $cookieParams['domain'], // O dejar null para automático
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

// 6. Iniciar Sesión (Si no está iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 7. Carga de Utilidades y Manejo de Errores
require_once __DIR__ . '/libs/Utils.php';
Utils::initErrorHandlers();

// 8. Conexión a Base de Datos (MySQL)
require_once __DIR__ . '/../config/database/db.php';

// 9. Inicializar I18n
$i18n = Utils::initI18n();

// 10. Retornar servicios globales para uso en los handlers
return [
    'pdo'   => $pdo,
    'i18n'  => $i18n,
    'redis' => $redis
];
?>