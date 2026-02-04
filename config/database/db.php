<?php
// config/database/db.php

// Carga de variables de entorno manual
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

// Validación Estricta de Variables
if (empty($_ENV['DB_HOST'])) throw new Exception("Config Error: Falta DB_HOST.");
$host = $_ENV['DB_HOST'];

if (empty($_ENV['DB_NAME'])) throw new Exception("Config Error: Falta DB_NAME.");
$db = $_ENV['DB_NAME'];

if (empty($_ENV['DB_USER'])) throw new Exception("Config Error: Falta DB_USER.");
$user = $_ENV['DB_USER'];

if (!isset($_ENV['DB_PASS'])) throw new Exception("Config Error: Falta DB_PASS.");
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
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '$offset'"
];

// [REFACTORIZADO] Ya no matamos el proceso con die().
// Intentamos conectar y si falla, lanzamos una excepción hacia arriba.
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Logueamos el error técnico internamente (para el sysadmin)
    error_log("CRITICAL DB CONNECTION ERROR: " . $e->getMessage());
    
    // Lanzamos una excepción genérica hacia bootstrap.php (para el usuario/frontend)
    // No incluimos $e->getMessage() aquí para no exponer credenciales o IPs al cliente.
    throw new Exception("No se pudo establecer conexión con la base de datos.");
}
?>