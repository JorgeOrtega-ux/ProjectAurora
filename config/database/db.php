<?php
// config/database/db.php

// Requerimos el Logger inmediatamente
require_once __DIR__ . '/../../includes/libs/Logger.php';

// 1. CARGADOR DE VARIABLES DE ENTORNO (.ENV)
$envPath = __DIR__ . '/../../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(sprintf('%s=%s', trim($name), trim($value)));
            $_ENV[trim($name)] = trim($value);
        }
    }
}

// 2. CONFIGURACIÓN DE LA BASE DE DATOS
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'project_aurora_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Importante: Lanzar excepciones
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 1. LOGUEAR EL ERROR TÉCNICO REAL (Oculto al usuario)
    // Guardamos: Mensaje del driver, Código de error y Archivo donde ocurrió
    Logger::db('Error de conexión PDO', [
        'exception_message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    // 2. MOSTRAR MENSAJE GENÉRICO (Visible al usuario)
    // Usamos HTTP 500 y terminamos la ejecución.
    http_response_code(500);
    die("<h1>Servicio no disponible</h1><p>Ocurrió un error al conectar con el sistema. Por favor intente más tarde.</p>");
}
?>