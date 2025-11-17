<?php
$host = 'localhost'; // Cambia esto si es necesario
$db   = 'project_aurora_db'; // Pon el nombre de tu base de datos
$user = 'root'; // Tu usuario de BD
$pass = ''; // Tu contraseña de BD
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
    // En producción no muestres el error real
    die(json_encode(['success' => false, 'message' => 'Error de conexión a base de datos']));
}