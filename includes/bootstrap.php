<?php
// includes/bootstrap.php

// 1. Carga del Autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Carga de Variables de Entorno (.env)
// Lo hacemos antes de iniciar sesión para tener acceso a credenciales de Redis
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
// Usamos Redis tanto para sesiones como para la lógica de Websockets
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
    // Verificar conexión básica (opcional, pero recomendado en dev)
    // $redis->connect(); 
} catch (Exception $e) {
    // Si falla Redis crítico, registramos y detenemos, ya que no habrá sesiones
    error_log("Fallo crítico: No se pudo conectar a Redis. " . $e->getMessage());
    die("Error interno del sistema de sesiones.");
}

// 4. Configuración del Handler de Sesiones de PHP para usar Redis
$sessionHandler = new Predis\Session\Handler($redis, ['gc_maxlifetime' => 86400]);
$sessionHandler->register();

// 5. CONFIGURACIÓN DE SEGURIDAD PARA LA SESIÓN
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path'     => '/',
    'domain'   => $cookieParams['domain'],
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Iniciamos la sesión (Ahora se guarda en Redis automáticamente)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 6. Carga de Utilidades y Manejo de Errores
require_once __DIR__ . '/libs/Utils.php';
Utils::initErrorHandlers();

// 7. Conexión a Base de Datos (MySQL)
require_once __DIR__ . '/../config/database/db.php';

// 8. Inicializar Sistema de Internacionalización (I18n)
$i18n = Utils::initI18n();

// 9. Retornar variables globales críticas
// Agregamos 'redis' al array retornado para inyectarlo en los servicios
return [
    'pdo'  => $pdo,
    'i18n' => $i18n,
    'redis' => $redis
];
?>