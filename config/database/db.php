<?php
// config/database/db.php

// ==============================================================================
// 1. CONFIGURACIÓN DE LOGS
// ==============================================================================

// CORRECCIÓN: Subir dos niveles para llegar a la carpeta logs en la raíz
$logDir = __DIR__ . '/../../logs';
$logFile = $logDir . '/aurora_errors.log';

if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}

ini_set('display_errors', 0);           
ini_set('display_startup_errors', 0);   
ini_set('log_errors', 1);               
ini_set('error_log', $logFile);         
error_reporting(E_ALL);                 

// ==============================================================================
// 2. INICIO DE SESIÓN Y ENTORNO
// ==============================================================================

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 60; 

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '', 
        'secure' => false, // Pon false si es localhost sin https
        'httponly' => true, 
        'samesite' => 'Strict' 
    ]);

    ini_set('session.gc_maxlifetime', $lifetime);
    session_start();
}

date_default_timezone_set('America/Mexico_City'); 

// --- CARGADOR DE .ENV ---
// CORRECCIÓN: Subir dos niveles para llegar al .env en la raíz
$envFile = __DIR__ . '/../../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
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
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==============================================================================
// 4. CONEXIÓN A BASE DE DATOS
// ==============================================================================

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = 'utf8mb4';

if (!$host || !$db || !$user) {
    error_log("[CRITICAL CONFIG] Faltan variables de entorno para la BD en .env");
    die("Error de configuración del sistema.");
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    try {
        $offset = date('P'); 
        $pdo->exec("SET time_zone = '$offset';");
    } catch (Exception $e) {
        error_log("[DB Warning] No se pudo sincronizar zona horaria: " . $e->getMessage());
    }

} catch (\PDOException $e) {
    error_log("[DB Connection Error] " . $e->getMessage());
    http_response_code(500); 
    die("El servicio no está disponible momentáneamente.");
}

$basePath = '/ProjectAurora/'; 
?>