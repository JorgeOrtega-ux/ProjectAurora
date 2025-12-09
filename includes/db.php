<?php
// includes/db.php
session_start();

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