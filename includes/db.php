<?php
session_start();

// CONFIGURACIÓN DE BASE DE DATOS
$host = 'localhost';
$db   = 'project_aurora_db'; // CAMBIA ESTO POR TU NOMBRE DE BD
$user = 'root';              // CAMBIA ESTO POR TU USUARIO
$pass = '';                  // CAMBIA ESTO POR TU CONTRASEÑA
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

// --- LÓGICA DE AUTENTICACIÓN (POST) ---

$error = '';
$success = '';

// 1. REGISTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($username && $email && $password) {
        // Verificar si existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->rowCount() > 0) {
            $error = "El usuario o correo ya existen.";
        } else {
            // Crear usuario
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $hash])) {
                // --- AUTO-LOGIN TRAS REGISTRO ---
                $newUserId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['username'] = $username;

                // Redirigir a la página principal
                // Usamos un fallback por si $basePath no está definido en este contexto
                $redirect = isset($basePath) ? $basePath : '/ProjectAurora/';
                header("Location: " . $redirect);
                exit;
            } else {
                $error = "Error al registrar.";
            }
        }
    } else {
        $error = "Todos los campos son obligatorios.";
    }
}

// 2. LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Login Exitoso
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        $redirect = isset($basePath) ? $basePath : '/ProjectAurora/';
        header("Location: " . $redirect); // Recargar en la home
        exit;
    } else {
        $error = "Credenciales incorrectas.";
    }
}

// 3. LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    $redirect = isset($basePath) ? $basePath : '/ProjectAurora/';
    header("Location: " . $redirect . "login");
    exit;
}
?>