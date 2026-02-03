<?php
// config/database/db.php

// Carga de variables de entorno manual (si no usas una librería como vlucas/phpdotenv en producción)
// Para desarrollo local, asumimos que .env está en la raíz.
$envFile = __DIR__ . '/../../.env';
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

// [SEGURIDAD] Validación Estricta de Variables de Entorno (Zero Fallback)

if (empty($_ENV['DB_HOST'])) {
    error_log("CRITICAL ERROR: DB_HOST no configurado.");
    die("Error de configuración: Falta DB_HOST.");
}
$host = $_ENV['DB_HOST'];

if (empty($_ENV['DB_NAME'])) {
    error_log("CRITICAL ERROR: DB_NAME no configurado.");
    die("Error de configuración: Falta DB_NAME.");
}
$db = $_ENV['DB_NAME'];

if (empty($_ENV['DB_USER'])) {
    error_log("CRITICAL ERROR: DB_USER no configurado.");
    die("Error de configuración: Falta DB_USER.");
}
$user = $_ENV['DB_USER'];

// Nota: DB_PASS puede ser una cadena vacía en algunos entornos locales, 
// pero la variable debe ESTAR DEFINIDA en el .env.
if (!isset($_ENV['DB_PASS'])) {
    error_log("CRITICAL ERROR: DB_PASS no configurado.");
    die("Error de configuración: Falta DB_PASS.");
}
$pass = $_ENV['DB_PASS'];

$charset = 'utf8mb4';

// Calcular el offset de la zona horaria actual de PHP
$now = new DateTime();
$mins = $now->getOffset() / 60;
$sgn = ($mins < 0 ? -1 : 1);
$mins = abs($mins);
$hrs = floor($mins / 60);
$mins -= $hrs * 60;
$offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Sincronizar la sesión de MySQL con la zona horaria de PHP
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '$offset'"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // En producción, no mostrar el error real al usuario
    error_log("Error de conexión a BD: " . $e->getMessage());
    die("Error de conexión con la base de datos."); 
}
?>