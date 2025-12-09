<?php
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

// --- FUNCIONES AUXILIARES ---

function logUserAccess($pdo, $userId) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ipList[0]);
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $ip, $userAgent]);
    } catch (Exception $e) {
        // Ignorar error de log
    }
}

// Generador de UUID v4 compatible
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
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
        // Verificar existencia
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->rowCount() > 0) {
            $error = "El usuario o correo ya existen.";
        } else {
            // Generar UUID en PHP para usarlo en el nombre del archivo
            $uuid = generate_uuid();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insertar usuario
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, uuid) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $hash, $uuid])) {
                
                // --- GENERAR Y GUARDAR FOTO DE PERFIL ---
                try {
                    // API de UI Avatars
                    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=random&color=fff&size=128";
                    $imageData = @file_get_contents($avatarUrl);

                    if ($imageData) {
                        // Definir ruta: public/assets/uploads/profile_pictures/
                        $targetDir = __DIR__ . '/../public/assets/uploads/profile_pictures/';
                        
                        // Crear carpeta si no existe
                        if (!is_dir($targetDir)) {
                            mkdir($targetDir, 0777, true);
                        }

                        // Guardar archivo: uuid.png
                        file_put_contents($targetDir . $uuid . '.png', $imageData);
                    }
                } catch (Exception $e) {
                    // Si falla la imagen, no detenemos el registro
                }

                // --- AUTO-LOGIN ---
                $newUserId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['username'] = $username;
                $_SESSION['uuid'] = $uuid; // Guardamos UUID en sesión para buscar la foto

                logUserAccess($pdo, $newUserId);

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

    // Recuperamos también el UUID
    $stmt = $pdo->prepare("SELECT id, username, password, uuid FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['uuid'] = $user['uuid']; // Guardar UUID en sesión
        
        logUserAccess($pdo, $user['id']);
        
        $redirect = isset($basePath) ? $basePath : '/ProjectAurora/';
        header("Location: " . $redirect);
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