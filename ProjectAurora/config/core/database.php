<?php
// config/database.php

// 1. Función para cargar variables del archivo .env (Nativo, sin librerías extra)
// Busca el archivo .env en el directorio padre (ProjectAurora/)
$envFile = __DIR__ . '/../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) continue;
        
        // Separar nombre y valor
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Eliminar comillas si existen
            $value = trim($value, '"\'');
            
            // Guardar en variables de entorno y superglobales
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 2. Obtener credenciales de las variables cargadas
// Se usa getenv() o $_ENV, con valores por defecto por seguridad
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'project_aurora_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Opcional: Sincronizar zona horaria de SQL con PHP si es necesario
    // $pdo->exec("SET time_zone = '" . date('P') . "'");
    
} catch (\PDOException $e) {
    // En producción no muestres el error real
    // error_log($e->getMessage()); // Descomentar para logs del servidor
    die(json_encode(['success' => false, 'message' => 'Error de conexión a base de datos']));
}
?>