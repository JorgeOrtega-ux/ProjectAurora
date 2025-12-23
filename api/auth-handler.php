<?php
// api/auth-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../includes/libs/TOTP.php'; // Necesario para verificar códigos 2FA

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

// =========================================================================
//  DETECCIÓN INTELIGENTE DE IDIOMA
// =========================================================================
function get_best_match_language() {
    $available_langs = ['es-latam', 'es-mx', 'en-us', 'en-gb', 'fr-fr'];
    $default_lang = 'es-latam';

    $http_accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    
    if (empty($http_accept_language)) return $default_lang;

    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $http_accept_language, $matches);

    if (count($matches[1])) {
        $langs = array_combine($matches[1], $matches[4]);
        $langs = array_change_key_case($langs, CASE_LOWER);

        foreach ($langs as $lang => $val) {
            if ($val === '') $langs[$lang] = 1;
        }
        arsort($langs, SORT_NUMERIC);

        foreach ($langs as $lang => $val) {
            if (in_array($lang, $available_langs)) return $lang;
            
            $prefix = substr($lang, 0, 2); 
            if ($prefix === 'es') return 'es-latam';
            if ($prefix === 'en') return 'en-us'; 
            if ($prefix === 'fr') return 'fr-fr';
        }
    }
    return $default_lang;
}

// =========================================================================
//  FUNCIONES DE SEGURIDAD
// =========================================================================

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function logSecurityEvent($pdo, $identifier, $actionType) {
    $ip = get_client_ip();
    $stmt = $pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$identifier, $actionType, $ip]);
}

function checkSecurityBlock($pdo, $actionType) {
    $ip = get_client_ip();
    $limit = 5; 
    $minutes = 15; 

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as failures 
        FROM security_logs 
        WHERE ip_address = ? 
        AND action_type = ? 
        AND created_at > (NOW() - INTERVAL $minutes MINUTE)
    ");
    $stmt->execute([$ip, $actionType]);
    $result = $stmt->fetch();

    if ($result && $result['failures'] >= $limit) {
        return true; 
    }
    return false;
}

// --- NUEVA FUNCIÓN: CREAR TOKEN DE PERSISTENCIA (ACTUALIZADA) ---
function create_persistence_token($pdo, $userId) {
    // 1. Selector (identificador público)
    $selector = bin2hex(random_bytes(12)); 
    // 2. Validador (secreto)
    $validator = bin2hex(random_bytes(32));
    
    // Guardamos el HASH del validador
    $hashedValidator = hash('sha256', $validator);
    $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 días

    // DATOS DE DISPOSITIVO
    $ip = get_client_ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // BD
    $sql = "INSERT INTO user_auth_tokens (user_id, selector, hashed_validator, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $selector, $hashedValidator, $ip, $userAgent, $expiresAt]);

    // Cookie: selector:validator
    // Nombre genérico para la cookie
    $cookieName = 'auth_persistence_token';
    $cookieValue = "$selector:$validator";
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; 
    
    setcookie($cookieName, $cookieValue, [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'domain' => '', 
        'secure' => $isSecure, 
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Función auxiliar para completar login exitoso (Reutilizable)
function completeLogin($pdo, $user) {
    // Regenerar ID de sesión
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['avatar'] = $user['avatar_path'];
    $_SESSION['email'] = $user['email'];

    // Cargar preferencias
    $prefStmt = $pdo->prepare("SELECT language, open_links_new_tab FROM user_preferences WHERE user_id = ?");
    $prefStmt->execute([$user['id']]);
    $prefs = $prefStmt->fetch();

    if ($prefs) {
        $_SESSION['preferences'] = [
            'language' => $prefs['language'],
            'open_links_new_tab' => (bool)$prefs['open_links_new_tab']
        ];
    } else {
        $_SESSION['preferences'] = [
            'language' => 'es-latam',
            'open_links_new_tab' => true
        ];
    }

    // SIEMPRE CREAR TOKEN DE PERSISTENCIA
    create_persistence_token($pdo, $user['id']);

    jsonResponse(true, 'Bienvenido.', ['redirect' => '/ProjectAurora/']);
}

// =========================================================================

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

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Este correo electrónico ya está registrado.');
    }

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
//  REENVIAR CÓDIGO
// =========================================================================
} elseif ($action === 'resend_code') {
    $email = $_SESSION['pending_verification_email'] ?? '';

    if (empty($email)) {
        jsonResponse(false, 'No hay proceso de verificación activo.');
    }

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

    $stmt = $pdo->prepare("SELECT payload FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email]);
    $lastCode = $stmt->fetch();

    if (!$lastCode) {
        jsonResponse(false, 'Error: No se encontraron datos de registro previos. Vuelve a iniciar.');
    }

    $payload = $lastCode['payload'];
    $newCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $del = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'");
    $del->execute([$email]);

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

        // === INSERTAR PREFERENCIAS ===
        $detectedLang = get_best_match_language();
        $prefStmt = $pdo->prepare("INSERT INTO user_preferences (user_id, language, open_links_new_tab) VALUES (?, ?, 1)");
        $prefStmt->execute([$newUserId, $detectedLang]);

        $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$verification['id']]);

        $pdo->commit();

        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        $_SESSION['avatar'] = $dbAvatarPath;
        $_SESSION['email'] = $email;
        
        $_SESSION['preferences'] = [
            'language' => $detectedLang,
            'open_links_new_tab' => true
        ];
        
        // AUTO-ACTIVAR PERSISTENCIA AL REGISTRAR
        create_persistence_token($pdo, $newUserId);
        
        unset($_SESSION['temp_register']);
        unset($_SESSION['pending_verification_email']);

        jsonResponse(true, 'Registro exitoso.', ['redirect' => '/ProjectAurora/']);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error crítico BD: ' . $e->getMessage());
    }

// =========================================================================
//  LOGIN MODIFICADO CON 2FA
// =========================================================================
} elseif ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (checkSecurityBlock($pdo, 'login_fail')) {
        jsonResponse(false, 'Demasiados intentos fallidos. Por seguridad, espera 15 minutos antes de volver a intentar.');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        
        // --- NUEVO: CHECK 2FA ---
        // Verificamos si la columna existe y si está activada (1)
        if (isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
            // No logueamos todavía. Guardamos ID temporalmente en sesión.
            $_SESSION['2fa_pending_user_id'] = $user['id'];
            
            jsonResponse(true, 'Credenciales correctas. Se requiere código 2FA.', [
                'require_2fa' => true
            ]);
        }

        // --- FLUJO NORMAL (SIN 2FA) ---
        completeLogin($pdo, $user);

    } else {
        logSecurityEvent($pdo, $email, 'login_fail');
        jsonResponse(false, 'Credenciales incorrectas.');
    }

// =========================================================================
//  NUEVO: VERIFICAR LOGIN 2FA (ETAPA 2)
// =========================================================================
} elseif ($action === 'verify_2fa_login') {
    $code = trim($_POST['code'] ?? '');
    
    if (!isset($_SESSION['2fa_pending_user_id'])) {
        jsonResponse(false, 'Sesión expirada. Vuelve a iniciar sesión.');
    }

    $userId = $_SESSION['2fa_pending_user_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(false, 'Usuario no encontrado.');
    }

    $isValid = false;

    // 1. Intentar como código TOTP (App Authenticator)
    if (TOTP::verifyCode($user['two_factor_secret'], $code)) {
        $isValid = true;
    } else {
        // 2. Intentar como código de recuperación (Backup)
        $recoveryCodes = json_decode($user['two_factor_recovery_codes'] ?? '[]', true);
        if (is_array($recoveryCodes) && in_array($code, $recoveryCodes)) {
            $isValid = true;
            // Remover código usado para que no se use dos veces
            $recoveryCodes = array_diff($recoveryCodes, [$code]);
            $newJson = json_encode(array_values($recoveryCodes)); // Reindexar
            $pdo->prepare("UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?")->execute([$newJson, $userId]);
        }
    }

    if ($isValid) {
        unset($_SESSION['2fa_pending_user_id']);
        completeLogin($pdo, $user);
    } else {
        jsonResponse(false, 'Código inválido.');
    }

// =========================================================================
//  RECUPERAR CONTRASEÑA - PASO 1
// =========================================================================
} elseif ($action === 'request_reset') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Correo electrónico inválido.');
    }

    if (checkSecurityBlock($pdo, 'recovery_fail')) {
        jsonResponse(false, 'Has excedido el límite de solicitudes. Intenta más tarde.');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if (!$stmt->fetch()) {
        logSecurityEvent($pdo, $email, 'recovery_fail');
        jsonResponse(false, 'No encontramos una cuenta con este correo.');
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

    $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
    try {
        $pdo->prepare($sql)->execute([$email, $token, $expiresAt]);
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST']; 
        $resetLink = "$protocol://$host/ProjectAurora/reset-password?token=$token";

        jsonResponse(true, 'Enlace generado (Simulación)', [
            'debug_link' => $resetLink,
            'message_user' => 'Te hemos enviado un correo (Revisa la consola/respuesta para el link de prueba).'
        ]);

    } catch (Exception $e) {
        jsonResponse(false, 'Error al generar solicitud: ' . $e->getMessage());
    }

// =========================================================================
//  RECUPERAR CONTRASEÑA - PASO 2
// =========================================================================
} elseif ($action === 'reset_password') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        jsonResponse(false, 'Faltan datos.');
    }

    if (strlen($newPassword) < 6) {
        jsonResponse(false, 'La contraseña es muy corta (min 6 caracteres).');
    }

    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();

    if (!$resetRequest) {
        jsonResponse(false, 'El enlace es inválido o ha expirado.');
    }

    $email = $resetRequest['email'];
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update->execute([$newHash, $email]);

        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // Cerrar sesiones previas por seguridad
        $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtUser->execute([$email]);
        $uData = $stmtUser->fetch();
        if($uData) {
            $pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?")->execute([$uData['id']]);
        }

        $pdo->commit();
        jsonResponse(true, 'Contraseña actualizada correctamente.', ['redirect' => '/ProjectAurora/login']);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al actualizar: ' . $e->getMessage());
    }

// =========================================================================
//  LOGOUT
// =========================================================================
} elseif ($action === 'logout') {
    // 1. Borrar token específico de BD si existe cookie
    if (isset($_COOKIE['auth_persistence_token'])) {
        $parts = explode(':', $_COOKIE['auth_persistence_token']);
        if (count($parts) === 2) {
            $selector = $parts[0];
            $del = $pdo->prepare("DELETE FROM user_auth_tokens WHERE selector = ?");
            $del->execute([$selector]);
        }
    }

    // 2. Destruir cookie
    setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    unset($_COOKIE['auth_persistence_token']);

    // 3. Destruir sesión
    session_destroy();
    jsonResponse(true, 'Bye.', ['redirect' => '/ProjectAurora/login']);
}

jsonResponse(false, 'Acción desconocida.');
?>