<?php
// api/services/auth_service.php

/**
 * Servicio de Autenticación
 * Maneja Login, Registro, Recuperación, Verificación y Logout.
 */

function handle_login($pdo, $email, $password) {
    global $basePath;

    checkRateLimit($pdo, $email, 'login_fail', 5, 15);

    // Agregamos account_status a la consulta
    $stmt = $pdo->prepare("SELECT id, username, password, uuid, role, two_factor_enabled, account_status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        
        // --- CHECK DE STATUS MODIFICADO ---
        if ($user['account_status'] === 'deleted') {
            logSecurityEvent($pdo, $email, 'login_deleted_attempt');
            // MODIFICADO: Devolver redirección en lugar de error simple
            return [
                'status' => 'error', 
                'redirect' => $basePath . 'account-status?type=deleted'
            ];
        }
        if ($user['account_status'] === 'suspended') {
            // MODIFICADO: Devolver redirección
            return [
                'status' => 'error', 
                'redirect' => $basePath . 'account-status?type=suspended'
            ];
        }

        session_regenerate_id(true);

        if ($user['two_factor_enabled'] == 1) {
            $_SESSION['temp_2fa_user_id'] = $user['id']; 
            return ['status' => 'success', 'message' => '2FA Required', 'redirect' => $basePath . '2fa-challenge'];
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['uuid'] = $user['uuid'];
            $_SESSION['role'] = $user['role'];
            
            logUserAccess($pdo, $user['id']);
            registerActiveSession($pdo, $user['id']); 

            return ['status' => 'success', 'message' => __('api.success.welcome'), 'redirect' => $basePath];
        }
    } else {
        logSecurityEvent($pdo, $email, 'login_fail');
        return ['status' => 'error', 'message' => __('api.error.credentials')];
    }
}

function handle_register_step_1($pdo, $email, $password) {
    global $basePath;

    if ($email && $password) {
        $val = validateEmailRequirements($email);
        if ($val !== true) return ['status' => 'error', 'message' => $val];
        
        if (strlen($password) < 8) return ['status' => 'error', 'message' => __('api.error.password_short')];
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            return ['status' => 'error', 'message' => __('api.error.email_exists')];
        } else {
            $_SESSION['temp_register'] = ['email' => $email, 'password' => $password];
            return ['status' => 'success', 'message' => __('api.success.valid_data'), 'redirect' => $basePath . "register/aditional-data"];
        }
    } else {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }
}

function handle_register_step_2($pdo, $username) {
    global $basePath;

    if (!isset($_SESSION['temp_register'])) {
        return ['status' => 'error', 'message' => __('api.error.session_expired'), 'redirect' => $basePath . "register"];
    }

    $email = $_SESSION['temp_register']['email'];
    $password = $_SESSION['temp_register']['password'];

    if ($username) {
        if (strlen($username) < 6) return ['status' => 'error', 'message' => __('api.error.username_short')];
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            return ['status' => 'error', 'message' => __('api.error.username_exists')];
        } else {
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'")->execute([$email]);
            $code = generate_verification_code();
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $payload = json_encode(['username' => $username, 'email' => $email, 'password' => $passwordHash]);
            
            $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            if ($stmt->execute([$email, 'account_activation', $code, $payload])) {
                $_SESSION['pending_verification_email'] = $email;
                unset($_SESSION['temp_register']);
                return ['status' => 'success', 'message' => __('api.success.code_sent'), 'redirect' => $basePath . "register/verify"];
            } else {
                return ['status' => 'error', 'message' => __('api.error.db_error')];
            }
        }
    } else {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }
}

function handle_resend_verification_code($pdo) {
    global $basePath;

    if (!isset($_SESSION['pending_verification_email'])) {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }
    $email = $_SESSION['pending_verification_email'];
    
    $checkStmt = $pdo->prepare("SELECT created_at FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' AND created_at > (NOW() - INTERVAL 60 SECOND) ORDER BY id DESC LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->rowCount() > 0) {
        return ['status' => 'error', 'message' => __('api.error.wait_resend')];
    }
    
    $newCode = generate_verification_code();
    $stmtLast = $pdo->prepare("SELECT payload FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$email]);
    $lastRow = $stmtLast->fetch();
    
    if ($lastRow) {
        $payload = $lastRow['payload'];
        $stmtInsert = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
        if ($stmtInsert->execute([$email, 'account_activation', $newCode, $payload])) {
            return ['status' => 'success', 'message' => __('api.success.code_resent') . $newCode];
        } else {
            return ['status' => 'error', 'message' => __('api.error.db_error')];
        }
    } else {
        return ['status' => 'error', 'message' => __('api.error.session_expired'), 'redirect' => $basePath . "register"];
    }
}

function handle_verify_code_create_account($pdo, $code) {
    global $basePath;

    $emailIdentifier = $_SESSION['pending_verification_email'] ?? null;
    if ($code && $emailIdentifier) {
        checkRateLimit($pdo, $emailIdentifier, 'verify_fail', 5, 15);
        
        $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'account_activation' AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([$emailIdentifier, $code]);
        $row = $stmt->fetch();
        
        if ($row) {
            $payload = json_decode($row['payload'], true);
            $uuid = generate_uuid();
            // Se crea como active por defecto
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

                return ['status' => 'success', 'message' => __('api.success.account_created'), 'redirect' => $basePath];
            } else {
                return ['status' => 'error', 'message' => __('api.error.db_error')];
            }
        } else {
            logSecurityEvent($pdo, $emailIdentifier, 'verify_fail');
            return ['status' => 'error', 'message' => __('api.error.invalid_code')];
        }
    } else {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }
}

function handle_verify_2fa_login($pdo, $code) {
    global $basePath;
    
    if (!isset($_SESSION['temp_2fa_user_id'])) {
         return ['status' => 'error', 'message' => __('api.error.session_expired'), 'redirect' => $basePath . 'login'];
    }

    $userId = $_SESSION['temp_2fa_user_id'];
    checkRateLimit($pdo, "uid_".$userId, '2fa_verify_fail', 5, 15);

    $stmt = $pdo->prepare("SELECT id, username, uuid, role, two_factor_secret, two_factor_recovery_codes FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) return ['status' => 'error', 'message' => __('api.error.credentials')];

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

        return ['status' => 'success', 'message' => __('api.success.welcome'), 'redirect' => $basePath];
    } else {
        logSecurityEvent($pdo, "uid_".$userId, '2fa_verify_fail');
        return ['status' => 'error', 'message' => __('api.error.invalid_code')];
    }
}

function handle_request_password_reset($pdo, $email) {
    global $basePath;

    checkRateLimit($pdo, $email, 'recovery_request', 3, 60, true);
    logSecurityEvent($pdo, $email, 'recovery_request');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['status' => 'error', 'message' => __('api.error.email_format')];
    
    // No permitir recuperar si está deleted
    $stmt = $pdo->prepare("SELECT id, account_status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        $u = $stmt->fetch();
        if ($u['account_status'] === 'deleted') {
            return ['status' => 'error', 'message' => __('api.error.account_deleted_permanent')];
        }

        $checkLimit = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
        $checkLimit->execute([$email]);
        if ($checkLimit->rowCount() > 0) {
            return ['status' => 'error', 'message' => __('api.error.wait_resend')];
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        if ($ins->execute([$email, $token])) {
            return ['status' => 'success', 'message' => __('api.success.link_generated')];
        }
    } else {
        // Seguridad por oscuridad
        return ['status' => 'success', 'message' => __('api.success.link_generated')]; 
    }
}

function handle_reset_password($pdo, $token, $newPass) {
    global $basePath;

    // Limitamos intentos por IP
    checkRateLimit($pdo, null, 'reset_token_try_ip', 10, 60, true);

    if (strlen($newPass) < 8) return ['status' => 'error', 'message' => __('api.error.password_short')];
    
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $req = $stmt->fetch();
    
    if ($req) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $req['email']]);
        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
        return ['status' => 'success', 'message' => __('api.success.password_updated'), 'redirect' => $basePath . "login"];
    } else {
        logSecurityEvent($pdo, 'system', 'reset_token_try_ip');
        return ['status' => 'error', 'message' => __('api.error.invalid_code')];
    }
}

function handle_logout($pdo) {
    global $basePath;
    
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
    return ['status' => 'success', 'message' => __('api.success.logout'), 'redirect' => $basePath . "login"];
}
?>