<?php
// api/auth-handler.php
session_start();

// ACTUALIZADO: La ruta ahora busca en config/database/
require_once __DIR__ . '/../config/database/db.php';

header('Content-Type: application/json');

// Helper para responder JSON
function jsonResponse($success, $message, $redirect = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect]);
    exit;
}

// Generador de UUID v4
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        jsonResponse(false, 'Todos los campos son obligatorios.');
    }

    // Verificar si existe email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'El correo electrónico ya está registrado.');
    }

    // Hash contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $uuid = generate_uuid();
    
    // Generar Avatar (UI Avatars)
    // Fondo aleatorio (random), letra del nombre, color blanco
    $firstLetter = substr($username, 0, 1);
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=random&color=fff&size=128";
    
    // Definir ruta de guardado
    // storage > profilePicture > default
    $storageDir = __DIR__ . '/../storage/profilePicture/default/';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0777, true);
    }

    $fileName = $uuid . '.png';
    $filePath = $storageDir . $fileName;
    
    // Descargar y guardar imagen
    $imageContent = file_get_contents($avatarUrl);
    if ($imageContent !== false) {
        file_put_contents($filePath, $imageContent);
        // Ruta relativa para guardar en BD
        $dbAvatarPath = 'storage/profilePicture/default/' . $fileName; 
    } else {
        $dbAvatarPath = null; // Fallback
    }

    // Insertar en BD
    $sql = "INSERT INTO users (uuid, username, email, password, role, avatar_path) VALUES (?, ?, ?, ?, 'user', ?)";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([$uuid, $username, $email, $passwordHash, $dbAvatarPath]);
        
        // Auto-login
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        $_SESSION['avatar'] = $dbAvatarPath;

        jsonResponse(true, 'Registro exitoso.', '/ProjectAurora/');
    } catch (Exception $e) {
        jsonResponse(false, 'Error en base de datos: ' . $e->getMessage());
    }

} elseif ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar_path'];

        jsonResponse(true, 'Bienvenido.', '/ProjectAurora/');
    } else {
        jsonResponse(false, 'Credenciales incorrectas.');
    }

} elseif ($action === 'logout') {
    session_destroy();
    jsonResponse(true, 'Sesión cerrada.', '/ProjectAurora/login');
}

jsonResponse(false, 'Acción no válida.');
?>