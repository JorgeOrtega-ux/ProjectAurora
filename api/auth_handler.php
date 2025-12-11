<?php
// api/auth_handler.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/../GoogleAuthenticator.php'; 

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? 0;

$lang = null;
if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $lang = $stmt->fetchColumn();
    } catch(Exception $e){}
}
if (!$lang) {
    $lang = detect_browser_language(); 
}
load_translations($lang);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $input = $json;
    }

    $incomingToken = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($incomingToken) || empty($sessionToken) || !hash_equals($sessionToken, $incomingToken)) {
        sendJsonResponse('error', __('api.error.csrf'));
    }

    $action = $input['action'] ?? '';

    // --- 1. REGISTRO PASO 1 ---
    if ($action === 'register_step_1') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        if ($email && $password) {
            $val = validateEmailRequirements($email);
            if ($val !== true) sendJsonResponse('error', $val);
            if (strlen($password) < 8) sendJsonResponse('error', __('api.error.password_short'));
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', __('api.error.email_exists'));
            } else {
                $_SESSION['temp_register'] = ['email' => $email, 'password' => $password];
                sendJsonResponse('success', __('api.success.valid_data'), $basePath . "register/aditional-data");
            }
        } else {
            sendJsonResponse('error', __('api.error.missing_data'));
        }
    }
    // --- 2. REGISTRO PASO 2 ---
    if ($action === 'register_step_2') {
         $username = trim($input['username'] ?? '');
        if (!isset($_SESSION['temp_register'])) {
            sendJsonResponse('error', __('api.error.session_expired'), $basePath . "register");
        }
        $email = $_SESSION['temp_register']['email'];
        $password = $_SESSION['temp_register']['password'];
        if ($username) {
            if (strlen($username) < 6) sendJsonResponse('error', __('api.error.username_short'));
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', __('api.error.username_exists'));
            } else {
                $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'")->execute([$email]);
                $code = generate_verification_code();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $payload = json_encode(['username' => $username, 'email' => $email, 'password' => $passwordHash]);
                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
                if ($stmt->execute([$email, 'account_activation', $code, $payload])) {
                    $_SESSION['pending_verification_email'] = $email;
                    unset($_SESSION['temp_register']);
                    sendJsonResponse('success', __('api.success.code_sent'), $basePath . "register/verify");
                } else {
                    sendJsonResponse('error', __('api.error.db_error'));
                }
            }
        } else {
            sendJsonResponse('error', __('api.error.missing_data'));
        }
    }
    // --- 3. REENVIAR CÓDIGO ---
    if ($action === 'resend_verification_code') {
        if (!isset($_SESSION['pending_verification_email'])) {
            sendJsonResponse('error', __('api.error.missing_data'));
        }
        $email = $_SESSION['pending_verification_email'];
        $checkStmt = $pdo->prepare("SELECT created_at FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' AND created_at > (NOW() - INTERVAL 60 SECOND) ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->rowCount() > 0) {
            sendJsonResponse('error', __('api.error.wait_resend'));
        }
        $newCode = generate_verification_code();
        $stmtLast = $pdo->prepare("SELECT payload FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$email]);
        $lastRow = $stmtLast->fetch();
        if ($lastRow) {
            $payload = $lastRow['payload'];
            $stmtInsert = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            if ($stmtInsert->execute([$email, 'account_activation', $newCode, $payload])) {
                sendJsonResponse('success', __('api.success.code_resent') . $newCode);
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } else {
            sendJsonResponse('error', __('api.error.session_expired'), $basePath . "register");
        }
    }
    // --- 4. VERIFICAR CÓDIGO Y CREAR CUENTA ---
    if ($action === 'verify_code') {
        $code = trim($input['code'] ?? '');
        $emailIdentifier = $_SESSION['pending_verification_email'] ?? null;
        if ($code && $emailIdentifier) {
            checkRateLimit($pdo, $emailIdentifier, 'verify_fail', 5, 15);
            $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'account_activation' AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
            $stmt->execute([$emailIdentifier, $code]);
            $row = $stmt->fetch();
            if ($row) {
                $payload = json_decode($row['payload'], true);
                $uuid = generate_uuid();
                // NOTA: Se crea como active por defecto en la BD
                $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, uuid) VALUES (?, ?, ?, ?)");
                if ($insertUser->execute([$payload['username'], $payload['email'], $payload['password'], $uuid])) {
                    $newId = $pdo->lastInsertId();
                    $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ?")->execute([$emailIdentifier]);
                    ensureDefaultAvatarExists($uuid, $payload['username']);
                    $detectedLang = detect_browser_language(); 
                    $stmtPref = $pdo->prepare("INSERT INTO user_preferences (user_id, language, open_links_new_tab) VALUES (?, ?, 1)");
                    $stmtPref->execute([$newId, $detectedLang]);
                    $_SESSION['user_id'] = $newId;
                    $_SESSION['username'] = $payload['username'];
                    $_SESSION['uuid'] = $uuid;
                    $_SESSION['role'] = 'user';
                    unset($_SESSION['pending_verification_email']);
                    
                    logUserAccess($pdo, $newId);
                    registerActiveSession($pdo, $newId); 

                    sendJsonResponse('success', __('api.success.account_created'), $basePath);
                } else {
                    sendJsonResponse('error', __('api.error.db_error'));
                }
            } else {
                logSecurityEvent($pdo, $emailIdentifier, 'verify_fail');
                sendJsonResponse('error', __('api.error.invalid_code'));
            }
        } else {
            sendJsonResponse('error', __('api.error.missing_data'));
        }
    }

    // --- 5. LOGIN MODIFICADO PARA STATUS DE CUENTA ---
    if ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        checkRateLimit($pdo, $email, 'login_fail', 5, 15);

        // Agregamos account_status a la consulta
        $stmt = $pdo->prepare("SELECT id, username, password, uuid, role, two_factor_enabled, account_status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            // --- NUEVO: CHECK DE STATUS ---
            if ($user['account_status'] === 'deleted') {
                logSecurityEvent($pdo, $email, 'login_deleted_attempt');
                sendJsonResponse('error', "Esta cuenta ha sido eliminada permanentemente.");
            }
            if ($user['account_status'] === 'suspended') {
                sendJsonResponse('error', "Cuenta suspendida. Contacta a soporte.");
            }

            session_regenerate_id(true);

            if ($user['two_factor_enabled'] == 1) {
                $_SESSION['temp_2fa_user_id'] = $user['id']; 
                sendJsonResponse('success', '2FA Required', $basePath . '2fa-challenge');
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['uuid'] = $user['uuid'];
                $_SESSION['role'] = $user['role'];
                
                logUserAccess($pdo, $user['id']);
                registerActiveSession($pdo, $user['id']); 

                sendJsonResponse('success', __('api.success.welcome'), $basePath);
            }
        } else {
            logSecurityEvent($pdo, $email, 'login_fail');
            sendJsonResponse('error', __('api.error.credentials'));
        }
    }

    // --- 5.1 VERIFICAR 2FA LOGIN ---
    if ($action === 'verify_2fa_login') {
        $code = trim($input['code'] ?? '');
        
        if (!isset($_SESSION['temp_2fa_user_id'])) {
             sendJsonResponse('error', __('api.error.session_expired'), $basePath . 'login');
        }

        $userId = $_SESSION['temp_2fa_user_id'];
        checkRateLimit($pdo, "uid_".$userId, '2fa_verify_fail', 5, 15);

        $stmt = $pdo->prepare("SELECT id, username, uuid, role, two_factor_secret, two_factor_recovery_codes FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) sendJsonResponse('error', __('api.error.credentials'));

        $ga = new PHPGangsta_GoogleAuthenticator();
        $isValid = $ga->verifyCode($user['two_factor_secret'], $code, 1);

        $usedBackupCode = false;
        if (!$isValid && !empty($user['two_factor_recovery_codes'])) {
            $backupCodes = json_decode($user['two_factor_recovery_codes'], true);
            if (in_array($code, $backupCodes)) {
                $isValid = true;
                $usedBackupCode = true;
                $backupCodes = array_diff($backupCodes, [$code]);
                $stmtUpdate = $pdo->prepare("UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?");
                $stmtUpdate->execute([json_encode(array_values($backupCodes)), $userId]);
            }
        }

        if ($isValid) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['uuid'] = $user['uuid'];
            $_SESSION['role'] = $user['role'];
            unset($_SESSION['temp_2fa_user_id']);

            logUserAccess($pdo, $user['id']);
            registerActiveSession($pdo, $user['id']); 

            sendJsonResponse('success', __('api.success.welcome'), $basePath);
        } else {
            logSecurityEvent($pdo, "uid_".$userId, '2fa_verify_fail');
            sendJsonResponse('error', __('api.error.invalid_code'));
        }
    }

    // --- 6. RECUPERAR CONTRASEÑA ---
    if ($action === 'request_password_reset') {
        $email = trim($input['email'] ?? '');
        checkRateLimit($pdo, $email, 'recovery_request', 3, 60, true);
        logSecurityEvent($pdo, $email, 'recovery_request');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendJsonResponse('error', __('api.error.email_format'));
        
        // MODIFICADO: No permitir recuperar si está deleted
        $stmt = $pdo->prepare("SELECT id, account_status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $u = $stmt->fetch();
            if ($u['account_status'] === 'deleted') {
                // Por seguridad, actuamos como si se envió (o damos error explicito si prefieres)
                // Aquí damos error explícito para ser consistentes con el login
                sendJsonResponse('error', "Esta cuenta ha sido eliminada permanentemente.");
            }

            $checkLimit = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
            $checkLimit->execute([$email]);
            if ($checkLimit->rowCount() > 0) {
                sendJsonResponse('error', __('api.error.wait_resend'));
            }
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            if ($ins->execute([$email, $token])) {
                sendJsonResponse('success', __('api.success.link_generated'));
            }
        } else {
            // Seguridad por oscuridad: decimos que se envió aunque no exista
            sendJsonResponse('error', __('api.success.link_generated')); 
        }
    }
    // --- 7. RESETEAR CONTRASEÑA ---
    if ($action === 'reset_password') {
        // [SEGURIDAD] Limitamos intentos por IP para evitar fuerza bruta de tokens
        checkRateLimit($pdo, null, 'reset_token_try_ip', 10, 60, true);

        $token = $input['token'] ?? '';
        $newPass = $input['password'] ?? '';
        if (strlen($newPass) < 8) sendJsonResponse('error', __('api.error.password_short'));
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $req = $stmt->fetch();
        if ($req) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $req['email']]);
            $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            sendJsonResponse('success', __('api.success.password_updated'), $basePath . "login");
        } else {
            // [CORRECCIÓN] Registramos el fallo para que checkRateLimit funcione
            logSecurityEvent($pdo, 'system', 'reset_token_try_ip');
            sendJsonResponse('error', __('api.error.invalid_code'));
        }
    }
    // --- 8. LOGOUT ---
    if ($action === 'logout') {
        if (isset($_SESSION['user_id'])) {
             $sid = session_id();
             $pdo->prepare("DELETE FROM active_sessions WHERE session_id = ?")->execute([$sid]);
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        sendJsonResponse('success', __('api.success.logout'), $basePath . "login");
    }

    sendJsonResponse('error', "Action invalid (Auth Handler)");
}
?>