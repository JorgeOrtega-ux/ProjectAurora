<?php
// config/database/db.php

// 1. CARGADOR DE VARIABLES DE ENTORNO (.ENV)
// Buscamos el archivo .env subiendo dos niveles desde /config/database/
$envPath = __DIR__ . '/../../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) continue;
        
        // Separar nombre y valor
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Guardar en variables de entorno y $_ENV
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }
    }
}

// 2. CONFIGURACIÓN DE LA BASE DE DATOS
// Usamos getenv() para obtener los valores, o un valor por defecto (fallback)
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'project_aurora_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // En producción, es mejor no mostrar el mensaje detallado del error
    // throw new \PDOException($e->getMessage(), (int)$e->getCode());
    error_log("Error de conexión BD: " . $e->getMessage());
    die("Error de conexión con la base de datos.");
}
?>