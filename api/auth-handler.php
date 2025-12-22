<?php
// api/auth-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';

header('Content-Type: application/json');

function jsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// === SEGURIDAD CSRF (VALIDACIÓN) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Si el token no viene, no existe en sesión o no coincide
        jsonResponse(false, 'Error de seguridad: Token CSRF inválido. Por favor recarga la página.');
    }
}

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$action = $_POST['action'] ?? '';

// === PASO 1 Y 2: PRE-VALIDACIÓN Y GENERACIÓN DE CÓDIGO ===
if ($action === 'initiate_verification') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($username) || empty($password)) {
        jsonResponse(false, 'Todos los campos son obligatorios.');
    }

    // 1. Verificar si el email o usuario ya existen en la tabla real users
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'El correo electrónico o el usuario ya están registrados.');
    }

    // 2. Generar código de 6 dígitos
    $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // 3. Hash de contraseña para guardarla en el payload temporal
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 4. Preparar Payload JSON
    $payload = json_encode([
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash
    ]);

    // 5. Definir expiración (ej. 15 minutos)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // 6. Guardar en tabla verification_codes
    // Limpiamos códigos previos de este email para evitar basura
    $del = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'");
    $del->execute([$email]);

    $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'account_activation', ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$email, $code, $payload, $expiresAt]);

        // AQUÍ IRÍA EL ENVÍO DE EMAIL REAL
        // Por ahora, simulamos éxito.
        jsonResponse(true, 'Código enviado.', ['debug_code' => $code]); 
    } catch (Exception $e) {
        jsonResponse(false, 'Error al generar verificación: ' . $e->getMessage());
    }

// === PASO 3: FINALIZAR REGISTRO ===
} elseif ($action === 'complete_register') {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (empty($email) || empty($code)) {
        jsonResponse(false, 'Faltan datos de verificación.');
    }

    // 1. Buscar el código válido
    $sql = "SELECT * FROM verification_codes 
            WHERE identifier = ? 
            AND code = ? 
            AND code_type = 'account_activation' 
            AND expires_at > NOW() 
            ORDER BY id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email, $code]);
    $verification = $stmt->fetch();

    if (!$verification) {
        jsonResponse(false, 'Código inválido o expirado.');
    }

    // 2. Recuperar datos del payload
    $payload = json_decode($verification['payload'], true);
    if (!$payload) {
        jsonResponse(false, 'Error en los datos temporales.');
    }

    $username = $payload['username'];
    $passwordHash = $payload['password_hash']; 
    $uuid = generate_uuid();

    // 3. Generar Avatar (CORREGIDO PARA FORZAR PNG)
    $firstLetter = substr($username, 0, 1);
    // Agregamos &format=png para asegurar que la API devuelva una imagen rasterizada
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=random&color=fff&size=128&format=png";
    
    $storageDir = __DIR__ . '/../storage/profilePicture/default/';
    if (!is_dir($storageDir)) { mkdir($storageDir, 0777, true); }
    
    $fileName = $uuid . '.png';
    $filePath = $storageDir . $fileName;
    $imageContent = @file_get_contents($avatarUrl);
    
    $dbAvatarPath = ($imageContent !== false && file_put_contents($filePath, $imageContent)) 
        ? 'storage/profilePicture/default/' . $fileName 
        : null;

    // 4. Insertar usuario real en tabla users
    $pdo->beginTransaction();
    try {
        $insertUser = $pdo->prepare("INSERT INTO users (uuid, username, email, password, role, avatar_path) VALUES (?, ?, ?, ?, 'user', ?)");
        $insertUser->execute([$uuid, $username, $email, $passwordHash, $dbAvatarPath]);
        $newUserId = $pdo->lastInsertId();

        // 5. Borrar el código usado
        $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$verification['id']]);

        $pdo->commit();

        // 6. Auto-login
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        $_SESSION['avatar'] = $dbAvatarPath;

        jsonResponse(true, 'Registro completado exitosamente.', ['redirect' => '/ProjectAurora/']);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error crítico al crear usuario: ' . $e->getMessage());
    }

// === LOGIN NORMAL ===
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

        jsonResponse(true, 'Bienvenido.', ['redirect' => '/ProjectAurora/']);
    } else {
        jsonResponse(false, 'Credenciales incorrectas.');
    }

// === LOGOUT ===
} elseif ($action === 'logout') {
    session_destroy();
    jsonResponse(true, 'Sesión cerrada.', ['redirect' => '/ProjectAurora/login']);
}

jsonResponse(false, 'Acción no válida.');
?>