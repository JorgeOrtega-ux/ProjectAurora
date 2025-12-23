<?php
// api/auth-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';

header('Content-Type: application/json');

function jsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// === SEGURIDAD CSRF ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        jsonResponse(false, 'Error de seguridad: Token CSRF inválido. Recarga la página.');
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

// =========================================================================
//  PASO 1 (Guardar credenciales temporales)
// =========================================================================
if ($action === 'register_step_1') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        jsonResponse(false, 'Por favor completa todos los campos.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'El correo electrónico no es válido.');
    }

    // Verificar si ya existe en la BD
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Este correo electrónico ya está registrado.');
    }

    // Guardar en sesión
    $_SESSION['temp_register'] = [
        'email' => $email,
        'password' => $password
    ];

    jsonResponse(true, 'Paso 1 completado', ['next_url' => 'register/aditional-data']);

// =========================================================================
//  PASO 2 -> 3 (Iniciar Verificación)
// =========================================================================
} elseif ($action === 'initiate_verification') {
    $username = trim($_POST['username'] ?? '');
    
    if (!isset($_SESSION['temp_register']['email'])) {
        jsonResponse(false, 'Sesión expirada. Vuelve a comenzar el registro.');
    }
    
    $email = $_SESSION['temp_register']['email'];
    $password = $_SESSION['temp_register']['password'];

    if (empty($username)) {
        jsonResponse(false, 'Elige un nombre de usuario.');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'El nombre de usuario ya está ocupado.');
    }

    $_SESSION['temp_register']['username'] = $username;
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $payload = json_encode([
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash
    ]);

    $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Limpiar anteriores
    $del = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'");
    $del->execute([$email]);

    $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'account_activation', ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$email, $code, $payload, $expiresAt]);
        $_SESSION['pending_verification_email'] = $email;

        jsonResponse(true, 'Código enviado.', [
            'debug_code' => $code,
            'next_url' => 'register/verification-account'
        ]); 
    } catch (Exception $e) {
        jsonResponse(false, 'Error interno: ' . $e->getMessage());
    }

// =========================================================================
//  NUEVO: REENVIAR CÓDIGO (¡CON SEGURIDAD DE SERVIDOR!)
// =========================================================================
} elseif ($action === 'resend_code') {
    $email = $_SESSION['pending_verification_email'] ?? '';

    if (empty($email)) {
        jsonResponse(false, 'No hay proceso de verificación activo.');
    }

    // --- SEGURIDAD 1: RATE LIMITING (Anti-Spam) ---
    // Verificamos si existe algún código creado en los últimos 60 SEGUNDOS.
    // Usamos NOW() de SQL para evitar problemas de sincronización de hora entre PHP y DB.
    $checkTime = $pdo->prepare("
        SELECT created_at 
        FROM verification_codes 
        WHERE identifier = ? 
        AND code_type = 'account_activation' 
        AND created_at > (NOW() - INTERVAL 60 SECOND)
        ORDER BY id DESC LIMIT 1
    ");
    $checkTime->execute([$email]);
    
    if ($checkTime->fetch()) {
        jsonResponse(false, 'Por favor espera 60 segundos antes de solicitar otro código.');
    }
    // ---------------------------------------------

    // 1. Recuperar el payload del último código (para no perder username/pass)
    $stmt = $pdo->prepare("SELECT payload FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email]);
    $lastCode = $stmt->fetch();

    if (!$lastCode) {
        jsonResponse(false, 'Error: No se encontraron datos de registro previos. Vuelve a iniciar.');
    }

    $payload = $lastCode['payload'];

    // 2. Generar nuevo código
    $newCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // 3. Eliminar códigos anteriores (Limpieza)
    $del = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'");
    $del->execute([$email]);

    // 4. Guardar nuevo
    $insert = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'account_activation', ?, ?, ?)");
    
    try {
        $insert->execute([$email, $newCode, $payload, $expiresAt]);
        jsonResponse(true, 'Nuevo código generado', ['debug_code' => $newCode]);
    } catch (Exception $e) {
        jsonResponse(false, 'Error al guardar código: ' . $e->getMessage());
    }

// =========================================================================
//  PASO 3 (Finalizar)
// =========================================================================
} elseif ($action === 'complete_register') {
    $code = trim($_POST['code'] ?? '');
    $email = $_SESSION['pending_verification_email'] ?? '';

    if (empty($code) || empty($email)) {
        jsonResponse(false, 'Faltan datos de verificación.');
    }

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

    $payload = json_decode($verification['payload'], true);
    $username = $payload['username'];
    $passwordHash = $payload['password_hash']; 
    $uuid = generate_uuid();

    // Generar Avatar
    $firstLetter = substr($username, 0, 1);
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=random&color=fff&size=128&format=png";
    
    $storageDir = __DIR__ . '/../storage/profilePicture/default/';
    if (!is_dir($storageDir)) { mkdir($storageDir, 0777, true); }
    
    $fileName = $uuid . '.png';
    $filePath = $storageDir . $fileName;
    $imageContent = @file_get_contents($avatarUrl);
    
    $dbAvatarPath = ($imageContent !== false && file_put_contents($filePath, $imageContent)) 
        ? 'storage/profilePicture/default/' . $fileName 
        : null;

    $pdo->beginTransaction();
    try {
        $insertUser = $pdo->prepare("INSERT INTO users (uuid, username, email, password, role, avatar_path) VALUES (?, ?, ?, ?, 'user', ?)");
        $insertUser->execute([$uuid, $username, $email, $passwordHash, $dbAvatarPath]);
        $newUserId = $pdo->lastInsertId();

        $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$verification['id']]);

        $pdo->commit();

        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        $_SESSION['avatar'] = $dbAvatarPath;
        
        unset($_SESSION['temp_register']);
        unset($_SESSION['pending_verification_email']);

        jsonResponse(true, 'Registro exitoso.', ['redirect' => '/ProjectAurora/']);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error crítico BD: ' . $e->getMessage());
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

        jsonResponse(true, 'Bienvenido.', ['redirect' => '/ProjectAurora/']);
    } else {
        jsonResponse(false, 'Credenciales incorrectas.');
    }

} elseif ($action === 'logout') {
    session_destroy();
    jsonResponse(true, 'Bye.', ['redirect' => '/ProjectAurora/login']);
}

jsonResponse(false, 'Acción desconocida.');
?>