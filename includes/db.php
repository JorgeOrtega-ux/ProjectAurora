<?php
// includes/db.php
// UBICACIÓN: Raíz del proyecto /includes/

// ==============================================================================
// 1. CONFIGURACIÓN DE LOGS Y MANEJO DE ERRORES (SISTEMA DE LOGS)
// ==============================================================================

// Definir ruta del archivo de log (Raíz del proyecto /logs/aurora_errors.log)
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/aurora_errors.log';

// Crear la carpeta de logs si no existe
if (!file_exists($logDir)) {
    // 0755 permite lectura/escritura al propietario y lectura a otros (seguro en la mayoría de hostings)
    @mkdir($logDir, 0755, true);
}

// Directivas de PHP para ocultar errores al usuario y guardarlos en el archivo
ini_set('display_errors', 0);           // CRÍTICO: No mostrar nada en pantalla (HTML/JSON)
ini_set('display_startup_errors', 0);   // CRÍTICO: No mostrar errores de inicio de PHP
ini_set('log_errors', 1);               // Activar el guardado de logs
ini_set('error_log', $logFile);         // Definir dónde se guardan
error_reporting(E_ALL);                 // Reportar TODOS los errores al log (Warnings, Notices, Fatal)

// ==============================================================================
// 2. INICIO DE SESIÓN Y ENTORNO
// ==============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Mexico_City'); 

// --- CARGADOR DE .ENV ---
$envFile = __DIR__ . '/../.env';
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

// 3. Generación del Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==============================================================================
// 4. CONEXIÓN A BASE DE DATOS (CON LOGGING SEGURO)
// ==============================================================================

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = 'utf8mb4';

if (!$host || !$db || !$user) {
    // Registramos el error técnico en el log
    error_log("[CRITICAL CONFIG] Faltan variables de entorno para la BD en .env");
    // Mensaje genérico al usuario
    die("Error de configuración del sistema. Contacte al administrador.");
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
        // Warning silencioso al log
        error_log("[DB Warning] No se pudo sincronizar zona horaria: " . $e->getMessage());
    }

} catch (\PDOException $e) {
    // 1. Escribir el error real (usuario, contraseña fallida, ip) SOLO en el archivo log
    error_log("[DB Connection Error] " . $e->getMessage());
    
    // 2. Detener la ejecución con un mensaje limpio para el usuario
    // Usamos http_response_code 500 para indicar error de servidor sin dar detalles
    http_response_code(500); 
    die("El servicio no está disponible momentáneamente. Por favor intente más tarde.");
}

$basePath = '/ProjectAurora/'; 
?>