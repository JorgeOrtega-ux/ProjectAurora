<?php
// includes/db.php
// UBICACIÓN: Raíz del proyecto /includes/

// 1. Iniciar sesión siempre para tener acceso a $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Generación del Token CSRF (Si no existe, creamos uno)
if (empty($_SESSION['csrf_token'])) {
    // bin2hex(random_bytes(32)) genera un string aleatorio seguro de 64 caracteres
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CONFIGURACIÓN DE BASE DE DATOS
$host = 'localhost';
$db   = 'project_aurora_db';
$user = 'root';
$pass = '';
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
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Ruta base del proyecto
$basePath = '/ProjectAurora/'; 
?>