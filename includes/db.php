<?php
// includes/db.php
// UBICACIÓN: Raíz del proyecto /includes/

// 1. Iniciar sesión siempre para tener acceso a $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Configurar Zona Horaria de PHP (Opcional pero recomendado)
// Ajusta esto a tu zona local para que los logs de error tengan sentido
date_default_timezone_set('America/Mexico_City'); 

// 3. Generación del Token CSRF
if (empty($_SESSION['csrf_token'])) {
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
    
    // Intentar sincronizar la zona horaria de MySQL con la de PHP
    // Esto ayuda, pero nuestra nueva lógica basada en NOW() es la verdadera protección.
    try {
        $offset = date('P'); // Ej: -06:00
        $pdo->exec("SET time_zone = '$offset';");
    } catch (Exception $e) {
        // Si falla (ej. permisos), no detenemos la ejecución,
        // confiamos en las funciones nativas de SQL implementadas en auth_handler.
    }

} catch (\PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Ruta base del proyecto
$basePath = '/ProjectAurora/'; 
?>