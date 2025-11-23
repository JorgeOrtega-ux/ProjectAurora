<?php
// api/auth_handler.php

// --- CONFIGURACIÓN DE LOGS ---
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/php_error.log';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE);
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

function logger($message)
{
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] [CUSTOM] $message" . PHP_EOL, FILE_APPEND);
}

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 30;
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_httponly', 1);
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/database.php';
require_once '../config/utilities.php';
require_once '../includes/logic/GoogleAuthenticator.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

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
    $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
    $pdo->exec("SET time_zone='$offset';");
} catch (Exception $e) {
    logger("Warning: No se pudo sincronizar time_zone SQL: " . $e->getMessage());
}

function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_random_color() {
    $colors = ['C84F4F', '4F7AC8', '8C4FC8', 'C87A4F', '4FC8C8'];
    return $colors[array_rand($colors)];
}

function generate_verification_code() {
    return strtoupper(bin2hex(random_bytes(6)));
}

function is_allowed_domain($email) {
    if (!preg_match('/@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i', $email)) {
        return false;
    }
    return true; 
}

function set_user_session($pdo, $user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_uuid'] = $user['uuid'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['user_role'] = $user['role'];

    $sessionId = session_id();
    $ip = get_client_ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?")->execute([$sessionId]);

    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $sessionId, $ip, $userAgent]);
}

function mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) == 2) {
        return substr($parts[0], 0, 3) . '***@' . $parts[1];
    }
    return $email;
}

$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    if ($action === 'register_step_1') {
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
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
        $codeHash = hash('sha256', $code);
        $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'registration'")->execute([$email]);
        $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'registration', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $codeHash, $payload]);
        unset($_SESSION['temp_register']['password']);
        $_SESSION['temp_register']['username'] = $username;
        logger("[PRUEBA - ELIMINAR EN PROD] Registro para $email | Code: $code | Hash: $codeHash");
        $response = ['success' => true, 'message' => 'Código enviado'];

    } elseif ($action === 'register_final') {
        $inputCode = strtoupper(trim($data['code'] ?? ''));
        $email = $_SESSION['temp_register']['email'] ?? '';
        if (empty($email)) throw new Exception('Sesión perdida.');
        $inputHash = hash('sha256', $inputCode);
        $sql = "SELECT id, payload FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'registration' AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $inputHash]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception('Código inválido o expirado.');
        $payloadData = json_decode($row['payload'], true);
        $finalUsername = $payloadData['username'];
        $finalPassHash = $payloadData['password_hash'];
        $uuid = generate_uuid();
        $selectedColor = get_random_color();
        $apiUrl = "https://ui-avatars.com/api/?name={$finalUsername}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";
        $fileName = $uuid . '.png';
        $uploadDir = __DIR__ . '/../public/assets/uploads/avatars/default/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $destPath = $uploadDir . $fileName;
        $dbPath = 'assets/uploads/avatars/default/' . $fileName;
        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) file_put_contents($destPath, $imageContent);
        else $dbPath = null;
        $insert = $pdo->prepare("INSERT INTO users (uuid, email, username, password, avatar, role) VALUES (?, ?, ?, ?, ?, 'user')");
        if ($insert->execute([$uuid, $email, $finalUsername, $finalPassHash, $dbPath])) {
            $newUserId = $pdo->lastInsertId();
            $detectedLang = detect_browser_language();
            $prefsSql = "INSERT INTO user_preferences (user_id, usage_intent, language) VALUES (?, 'personal', ?)";
            $prefsStmt = $pdo->prepare($prefsSql);
            $prefsStmt->execute([$newUserId, $detectedLang]);
            $newUser = ['id' => $newUserId, 'uuid' => $uuid, 'email' => $email, 'avatar' => $dbPath, 'role' => 'user'];
            
            session_regenerate_id(true);
            set_user_session($pdo, $newUser); 

            $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$row['id']]);
            unset($_SESSION['temp_register']);
            $response = ['success' => true, 'message' => 'Bienvenido'];
        } else {
            throw new Exception('Error crítico.');
        }

    } elseif ($action === 'login') {
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $data['password'] ?? '';
        
        if (checkLockStatus($pdo, $email, 'login_fail')) throw new Exception("Has excedido intentos. Espera " . LOCKOUT_TIME_MINUTES . " mins.");
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            
            // [MODIFICADO] Verificación Detallada de Estado
            if (isset($user['account_status']) && $user['account_status'] !== 'active') {
                
                $responseData = [
                    'success' => false, 
                    'is_account_issue' => true, 
                    'status_type' => $user['account_status']
                ];

                // Si está suspendido, agregamos los detalles para mostrarlos en status-page
                if ($user['account_status'] === 'suspended') {
                    $responseData['reason'] = $user['suspension_reason'];
                    
                    if ($user['suspension_end_date']) {
                        $end = new DateTime($user['suspension_end_date']);
                        $responseData['until'] = $end->format('d/m/Y');
                    }
                }

                echo json_encode($responseData);
                exit;
            }

            if (isset($user['is_2fa_enabled']) && $user['is_2fa_enabled'] == 1) {
                if (!isset($_SESSION['temp_login_2fa'])) $_SESSION['temp_login_2fa'] = [];
                $_SESSION['temp_login_2fa']['user_id'] = $user['id'];
                $_SESSION['temp_login_2fa']['email'] = $user['email'];
                $response = ['success' => true, 'require_2fa' => true, 'message' => 'Verificación de App requerida', 'masked_email' => 'tu aplicación de autenticación'];
            } else {
                clearFailedAttempts($pdo, $email);
                session_regenerate_id(true);
                set_user_session($pdo, $user); 
                $response = ['success' => true, 'message' => 'Login correcto'];
            }
        } else {
            logFailedAttempt($pdo, $email, 'login_fail');
            throw new Exception('Credenciales incorrectas.');
        }

    } elseif ($action === 'login_2fa_verify') {
        $inputCode = trim($data['code'] ?? '');
        $cleanCode = str_replace(['-', ' '], '', $inputCode);
        if (empty($_SESSION['temp_login_2fa']['user_id'])) throw new Exception("Sesión expirada. Vuelve a iniciar login.");
        $userId = $_SESSION['temp_login_2fa']['user_id'];
        $email = $_SESSION['temp_login_2fa']['email'];
        if (checkLockStatus($pdo, $email, 'login_2fa_fail')) throw new Exception("Muchos intentos fallidos. Espera unos minutos.");
        $stmt = $pdo->prepare("SELECT id, uuid, username, email, avatar, role, two_factor_secret, backup_codes FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) throw new Exception("Usuario no encontrado.");
        $secret = $user['two_factor_secret'];
        $backupCodes = json_decode($user['backup_codes'] ?? '[]', true);
        if (!is_array($backupCodes)) $backupCodes = [];
        $isAuthenticated = false;
        $usedBackupCode = false;
        if (strlen($cleanCode) === 6) {
            $ga = new PHPGangsta_GoogleAuthenticator();
            if ($ga->verifyCode($secret, $cleanCode, 1)) $isAuthenticated = true;
        }
        if (!$isAuthenticated) {
            $index = array_search($inputCode, $backupCodes); 
            if ($index !== false) {
                $isAuthenticated = true;
                $usedBackupCode = true;
                unset($backupCodes[$index]);
                $backupCodes = array_values($backupCodes);
            }
        }
        if ($isAuthenticated) {
            if ($usedBackupCode) {
                $newBackups = json_encode($backupCodes);
                $pdo->prepare("UPDATE users SET backup_codes = ? WHERE id = ?")->execute([$newBackups, $userId]);
            }
            clearFailedAttempts($pdo, $email);
            session_regenerate_id(true);
            unset($user['password']);
            unset($user['two_factor_secret']);
            unset($user['backup_codes']);
            set_user_session($pdo, $user); 
            unset($_SESSION['temp_login_2fa']);
            $response = ['success' => true, 'message' => 'Autenticación completada'];
        } else {
            logFailedAttempt($pdo, $email, 'login_2fa_fail');
            throw new Exception("Código incorrecto.");
        }

    } elseif ($action === 'logout') {
        $sessionId = session_id();
        $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?")->execute([$sessionId]);

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        $response = ['success' => true, 'message' => 'Sesión cerrada correctamente'];
    
    } elseif ($action === 'recovery_step_1') {
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        if (empty($email) || !is_allowed_domain($email)) throw new Exception('Correo con formato inválido o dominio no permitido.');
        if (checkLockStatus($pdo, $email, 'recovery_fail')) throw new Exception("Demasiados intentos. Por favor espera " . LOCKOUT_TIME_MINUTES . " minutos.");
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $token = bin2hex(random_bytes(32)); 
            $tokenHash = hash('sha256', $token);
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'")->execute([$email]);
            $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, expires_at) VALUES (?, 'recovery', ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
            $stmt->execute([$email, $tokenHash]);
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST']; 
            $base = '/ProjectAurora/'; 
            $link = $protocol . $host . $base . 'reset-password?token=' . $token;
            logger("[PRUEBA - ELIMINAR EN PROD] Recuperación para $email | Link: $link | Hash guardado: $tokenHash");
            $response = ['success' => true, 'message' => 'Se ha enviado un enlace de recuperación a tu correo.'];
        } else {
            logFailedAttempt($pdo, $email, 'recovery_fail');
            throw new Exception('Este correo no se encuentra registrado en nuestra base de datos.');
        }

    } elseif ($action === 'recovery_final') {
        $token = trim($data['token'] ?? '');
        $newPass = $data['password'] ?? '';
        $confirmPass = $data['password_confirm'] ?? '';
        if (empty($token) || empty($newPass)) throw new Exception('Datos incompletos.');
        if ($newPass !== $confirmPass) throw new Exception('Las contraseñas no coinciden.');
        if (strlen($newPass) < 8) throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        $tokenHash = hash('sha256', $token);
        $sql = "SELECT identifier FROM verification_codes WHERE code = ? AND code_type = 'recovery' AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception('El enlace es inválido o ha expirado.');
        $email = $row['identifier'];
        $stmtUser = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmtUser->execute([$email]);
        $userData = $stmtUser->fetch();
        $oldHash = $userData['password'] ?? 'UNKNOWN';
        $userIdForLog = $userData['id'] ?? null;
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($upd->execute([$newHash, $email])) {
            if ($userIdForLog) {
                $ip = get_client_ip();
                $stmtLog = $pdo->prepare("INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip, changed_at) VALUES (?, 'password', ?, ?, ?, NOW())");
                $stmtLog->execute([$userIdForLog, $oldHash, $newHash, $ip]);
            }
            $pdo->prepare("DELETE FROM verification_codes WHERE code = ?")->execute([$tokenHash]);
            clearFailedAttempts($pdo, $email);
            $response = ['success' => true, 'message' => 'Contraseña actualizada. Inicia sesión.'];
        } else {
            throw new Exception('Error actualizando base de datos.');
        }
    }

} catch (Exception $e) {
    $realErrorMessage = $e->getMessage();
    if (strpos($realErrorMessage, 'espera') === false) {
        $prefix = (strpos($realErrorMessage, 'SQLSTATE') !== false) ? '[DB ERROR] ' : '[LOGIC] ';
        logger($prefix . $realErrorMessage);
    }
    if (strpos($realErrorMessage, 'SQLSTATE') !== false) {
        if (strpos($realErrorMessage, 'Duplicate entry') !== false) {
            $response['message'] = 'El nombre de usuario o correo ya está registrado.';
        } else {
            $response['message'] = 'Ocurrió un error interno en el servidor.';
        }
    } else {
        $response['message'] = $realErrorMessage;
    }
}

echo json_encode($response);
exit;
?>