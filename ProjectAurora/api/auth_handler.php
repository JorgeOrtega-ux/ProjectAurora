<?php
// api/auth_handler.php

// --- CONFIGURACIÓN DE LOGS ---
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/php_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE); 
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

function logger($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] [CUSTOM] $message" . PHP_EOL, FILE_APPEND);
}
// --- FIN CONFIGURACIÓN ---

session_start();
header('Content-Type: application/json'); 

date_default_timezone_set('America/Matamoros');

require_once '../config/database.php';
require_once '../config/utilities.php'; 

// VALIDACIÓN CSRF
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

try {
    $now = new DateTime();
    $mins = $now->getOffset() / 60;
    $sgn = ($mins < 0 ? -1 : 1);
    $mins = abs($mins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
    $pdo->exec("SET time_zone='$offset';");
} catch (Exception $e) {
    logger("Warning: No se pudo sincronizar time_zone SQL: " . $e->getMessage());
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// --- FUNCIONES AUXILIARES ---
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

function get_random_color() {
    // Lista de colores permitidos (sin el # para la API de UI Avatars)
    $colors = [
        'C84F4F', // Rojo esmeralda-like
        '4F7AC8', // Azul esmeralda-like
        '8C4FC8', // Morado esmeralda-like
        'C87A4F', // Naranja esmeralda-like
        '4FC8C8'  // Cian esmeralda-like
    ];
    return $colors[array_rand($colors)];
}

function generate_verification_code() {
    return strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}
function is_allowed_domain($email) {
    return preg_match('/@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i', $email);
}

function set_user_session($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_uuid'] = $user['uuid'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['user_role'] = $user['role'];
}

function mask_email($email) {
    $parts = explode('@', $email);
    if(count($parts) == 2){
        return substr($parts[0], 0, 3) . '***@' . $parts[1];
    }
    return $email;
}

$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    // ==================================================================
    // REGISTRO - ETAPA 1
    // ==================================================================
    if ($action === 'register_step_1') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) throw new Exception('Completa todos los campos.');
        if (strlen($email) < 4) throw new Exception('Correo muy corto.');
        if (!is_allowed_domain($email)) throw new Exception('Dominio no permitido.');
        if (strlen($password) < 8) throw new Exception('Contraseña muy corta.');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) throw new Exception('El correo ya está registrado.');

        if (!isset($_SESSION['temp_register'])) $_SESSION['temp_register'] = [];
        $_SESSION['temp_register']['email'] = $email;
        $_SESSION['temp_register']['password'] = $password; 

        $response = ['success' => true, 'message' => 'Paso 1 OK'];

    // ==================================================================
    // REGISTRO - ETAPA 2
    // ==================================================================
    } elseif ($action === 'register_step_2') {
        $username = trim($data['username'] ?? '');
        $email = $_SESSION['temp_register']['email'] ?? '';
        $rawPassword = $_SESSION['temp_register']['password'] ?? '';

        if (empty($email) || empty($rawPassword)) throw new Exception('Sesión expirada.');
        
        if (!preg_match('/^[a-zA-Z0-9_]{8,32}$/', $username)) throw new Exception('Usuario inválido.');
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) throw new Exception('El usuario ya existe.');

        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        $payload = json_encode(['username' => $username, 'password_hash' => $passwordHash]);
        $code = generate_verification_code();

        $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'registration'")->execute([$email]);

        $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'registration', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $code, $payload]);

        unset($_SESSION['temp_register']['password']);
        $_SESSION['temp_register']['username'] = $username; 

        logger("Code registro: $code");
        $response = ['success' => true, 'message' => 'Código enviado'];

    // ==================================================================
    // REGISTRO - ETAPA 3
    // ==================================================================
    } elseif ($action === 'register_final') {
        $inputCode = strtoupper(trim($data['code'] ?? ''));
        $email = $_SESSION['temp_register']['email'] ?? '';

        if (empty($email)) throw new Exception('Sesión perdida.');

        $sql = "SELECT id, payload FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'registration' AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $inputCode]);
        $row = $stmt->fetch();

        if (!$row) throw new Exception('Código inválido o expirado.');

        $payloadData = json_decode($row['payload'], true);
        $finalUsername = $payloadData['username'];
        $finalPassHash = $payloadData['password_hash'];
        $uuid = generate_uuid();
        $selectedColor = get_random_color();
        
        $apiUrl = "https://ui-avatars.com/api/?name={$finalUsername}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";
        $fileName = $uuid . '.png';
        $destPath = __DIR__ . '/../assets/uploads/avatars/' . $fileName;
        $dbPath = 'assets/uploads/avatars/' . $fileName; 
        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) file_put_contents($destPath, $imageContent);
        else $dbPath = null;

        $insert = $pdo->prepare("INSERT INTO users (uuid, email, username, password, avatar, role) VALUES (?, ?, ?, ?, ?, 'user')");
        if ($insert->execute([$uuid, $email, $finalUsername, $finalPassHash, $dbPath])) {
            $newUserId = $pdo->lastInsertId();
            
            $newUser = [
                'id' => $newUserId,
                'uuid' => $uuid,
                'email' => $email,
                'avatar' => $dbPath,
                'role' => 'user'
            ];
            set_user_session($newUser);

            $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$row['id']]);
            unset($_SESSION['temp_register']);

            $response = ['success' => true, 'message' => 'Bienvenido'];
        } else {
            throw new Exception('Error crítico.');
        }

    // ==================================================================
    // LOGIN (CON SOPORTE 2FA INTEGRADO)
    // ==================================================================
    } elseif ($action === 'login') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        if (checkLockStatus($pdo, $email, 'login_fail')) {
            throw new Exception("Has excedido intentos. Espera " . LOCKOUT_TIME_MINUTES . " mins.");
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            if (isset($user['account_status']) && $user['account_status'] !== 'active') {
                throw new Exception('Tu cuenta no está activa o ha sido suspendida.');
            }

            // LÓGICA 2FA
            if (isset($user['is_2fa_enabled']) && $user['is_2fa_enabled'] == 1) {
                
                $code = generate_verification_code();
                
                $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'login_2fa'")->execute([$email]);
                
                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, expires_at) VALUES (?, 'login_2fa', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                $stmt->execute([$email, $code]);
                
                if (!isset($_SESSION['temp_login_2fa'])) $_SESSION['temp_login_2fa'] = [];
                $_SESSION['temp_login_2fa']['user_id'] = $user['id'];
                $_SESSION['temp_login_2fa']['email'] = $user['email'];
                
                logger("Code 2FA Login para $email: $code");
                
                // [NUEVO] Enviamos el email enmascarado al frontend
                $response = [
                    'success' => true, 
                    'require_2fa' => true, 
                    'message' => 'Verificación requerida',
                    'masked_email' => mask_email($email)
                ];
                
            } else {
                clearFailedAttempts($pdo, $email);
                set_user_session($user);
                $response = ['success' => true, 'message' => 'Login correcto'];
            }
            
        } else {
            logFailedAttempt($pdo, $email, 'login_fail');
            throw new Exception('Credenciales incorrectas.');
        }

    // ==================================================================
    // LOGIN 2FA VERIFICATION
    // ==================================================================
    } elseif ($action === 'login_2fa_verify') {
        $inputCode = strtoupper(trim($data['code'] ?? ''));
        
        if (empty($_SESSION['temp_login_2fa']['user_id'])) {
            throw new Exception("Sesión expirada. Vuelve a iniciar login.");
        }
        
        $userId = $_SESSION['temp_login_2fa']['user_id'];
        $email = $_SESSION['temp_login_2fa']['email'];
        
        if (checkLockStatus($pdo, $email, 'login_2fa_fail')) {
            throw new Exception("Muchos intentos fallidos. Espera unos minutos.");
        }

        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'login_2fa' AND expires_at > NOW()");
        $stmt->execute([$email, $inputCode]);
        
        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'login_2fa'")->execute([$email]);
            
            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();
            
            if (!$user) throw new Exception("Usuario no encontrado.");

            clearFailedAttempts($pdo, $email);
            set_user_session($user);
            unset($_SESSION['temp_login_2fa']);
            
            $response = ['success' => true, 'message' => 'Autenticación completada'];
        } else {
            logFailedAttempt($pdo, $email, 'login_2fa_fail');
            throw new Exception("Código incorrecto o expirado.");
        }

    // ==================================================================
    // RECUPERACIÓN Y LOGOUT
    // ==================================================================
    } elseif ($action === 'recovery_step_1') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() === 0) throw new Exception('Este correo no está registrado.');
        
        $code = generate_verification_code();
        $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'")->execute([$email]);
        $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, expires_at) VALUES (?, 'recovery', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
        $stmt->execute([$email, $code]);
        
        if (!isset($_SESSION['temp_recovery'])) $_SESSION['temp_recovery'] = [];
        $_SESSION['temp_recovery']['email'] = $email;
        $_SESSION['temp_recovery']['step'] = 2;
        
        logger("Code Recup: $code");
        $response = ['success' => true, 'message' => 'Código enviado'];

    } elseif ($action === 'recovery_step_2') {
        $email = $_SESSION['temp_recovery']['email'] ?? '';
        $inputCode = strtoupper(trim($data['code'] ?? ''));
        
        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'recovery' AND expires_at > NOW()");
        $stmt->execute([$email, $inputCode]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['temp_recovery']['verified'] = true; 
            $_SESSION['temp_recovery']['step'] = 3; 
            $response = ['success' => true, 'message' => 'Código correcto'];
        } else {
            throw new Exception('Código incorrecto.');
        }

    } elseif ($action === 'recovery_final') {
        $email = $_SESSION['temp_recovery']['email'] ?? '';
        $verified = $_SESSION['temp_recovery']['verified'] ?? false;
        $newPass = $data['password'] ?? '';

        if (empty($email) || !$verified) throw new Exception('No autorizado.');
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);

        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($upd->execute([$newHash, $email])) {
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'")->execute([$email]);
            unset($_SESSION['temp_recovery']);
            clearFailedAttempts($pdo, $email);
            $response = ['success' => true, 'message' => 'Contraseña actualizada.'];
        } else {
            throw new Exception('Error BD.');
        }

    } elseif ($action === 'logout') {
        session_destroy();
        $response = ['success' => true, 'message' => 'Bye'];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    if (strpos($e->getMessage(), 'espera') === false) {
        logger("Error: " . $e->getMessage());
    }
}

echo json_encode($response);
exit;
?>