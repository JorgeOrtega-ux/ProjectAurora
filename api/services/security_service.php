<?php
// api/services/security_service.php

/**
 * Servicio de Seguridad
 * Maneja Contraseñas, Sesiones Activas, 2FA y Eliminación de Cuenta.
 */

function verify_current_password_check($pdo, $userId, $password) {
    // Rate Limit suave para evitar fuerza bruta en este endpoint
    checkRateLimit($pdo, "uid_".$userId, 'pass_verify_check', 10, 5);

    if (empty($password)) {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();

    if (password_verify($password, $storedHash)) {
        return ['status' => 'success', 'message' => 'Password OK']; // Mensaje interno, no requiere traducción
    } else {
        return ['status' => 'error', 'message' => __('api.error.current_password_invalid')];
    }
}

function delete_user_account($pdo, $userId, $password) {
    global $basePath;
    
    // Rate Limit preventivo: 5 intentos cada 30 min por usuario
    checkRateLimit($pdo, "uid_".$userId, 'delete_account_fail', 5, 30);

    if (empty($password)) {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }

    // 1. Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();

    if (!password_verify($password, $storedHash)) {
        logSecurityEvent($pdo, "uid_".$userId, 'delete_account_fail');
        return ['status' => 'error', 'message' => __('api.error.current_password_invalid')];
    }

    try {
        // 2. Marcar usuario como deleted (Soft Delete)
        $update = $pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE id = ?");
        if ($update->execute([$userId])) {
            
            // 3. Eliminar TODAS las sesiones activas de la BD
            $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ?")->execute([$userId]);

            // 4. Log del evento
            logSecurityEvent($pdo, "uid_".$userId, 'account_deleted');

            // 5. Destruir sesión PHP actual
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();

            return ['status' => 'success', 'message' => __('api.success.account_deleted'), 'redirect' => $basePath . 'login'];
        } else {
            return ['status' => 'error', 'message' => __('api.error.db_error')];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function get_active_sessions_list($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, session_id, ip_address, user_agent, last_activity, created_at FROM active_sessions WHERE user_id = ? ORDER BY last_activity DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    
    $currentSessionId = session_id();
    $data = [];
    
    foreach ($rows as $row) {
        $parsed = parseUserAgentSimple($row['user_agent']);
        $isCurrent = ($row['session_id'] === $currentSessionId);
        
        $data[] = [
            'id' => $row['id'], 
            'ip' => $row['ip_address'],
            'os' => $parsed['os'],
            'browser' => $parsed['browser'],
            'icon' => $parsed['icon'],
            'last_activity' => $row['last_activity'],
            'is_current' => $isCurrent
        ];
    }
    
    return ['status' => 'success', 'message' => 'OK', 'data' => $data];
}

function revoke_single_session($pdo, $userId, $sessionDbId) {
    $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$sessionDbId, $userId])) {
        return ['status' => 'success', 'message' => __('api.success.session_closed')];
    } else {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function revoke_all_sessions($pdo, $userId, $password) {
    $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmtPass->execute([$userId]);
    $storedHash = $stmtPass->fetchColumn();
    if (!password_verify($password, $storedHash)) {
        return ['status' => 'error', 'message' => __('api.error.current_password_invalid')];
    }

    $currentSessionId = session_id();
    $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ? AND session_id != ?");
    
    if ($stmt->execute([$userId, $currentSessionId])) {
        return ['status' => 'success', 'message' => __('api.success.all_sessions_closed')];
    } else {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function init_2fa_setup($pdo, $userId, $password) {
    // Rate Limit para evitar fuerza bruta de contraseña en este paso
    checkRateLimit($pdo, "uid_".$userId, '2fa_init_fail', 5, 15);

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();

    if (!password_verify($password, $storedHash)) {
         logSecurityEvent($pdo, "uid_".$userId, '2fa_init_fail');
         return ['status' => 'error', 'message' => __('api.error.current_password_invalid')];
    }

    $ga = new PHPGangsta_GoogleAuthenticator();
    $secret = $ga->createSecret();
    $_SESSION['temp_2fa_secret'] = $secret; 

    $username = $_SESSION['username'];
    // URI Raw
    $otpUri = "otpauth://totp/ProjectAurora:" . $username . "?secret=" . $secret . "&issuer=ProjectAurora";

    return ['status' => 'success', 'message' => 'OK', 'data' => [
        'otp_uri' => $otpUri,
        'secret' => $secret
    ]];
}

function enable_2fa_confirm($pdo, $userId, $code) {
    $secret = $_SESSION['temp_2fa_secret'] ?? null;
    
    // Rate Limit para evitar fuerza bruta en el setup
    checkRateLimit($pdo, "uid_".$userId, '2fa_setup_fail', 5, 10);

    if (!$secret || empty($code)) {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }

    $ga = new PHPGangsta_GoogleAuthenticator();
    $checkResult = $ga->verifyCode($secret, $code, 1);
    
    if ($checkResult) {
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4));
        }
        $recoveryCodesJson = json_encode($recoveryCodes);

        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_recovery_codes = ? WHERE id = ?");
        if ($stmt->execute([$secret, $recoveryCodesJson, $userId])) {
            unset($_SESSION['temp_2fa_secret']);
            logSecurityEvent($pdo, "uid_".$userId, '2fa_enabled');
            
            return ['status' => 'success', 'message' => __('api.success.2fa_enabled'), 'data' => [
                'recovery_codes' => $recoveryCodes
            ]];
        } else {
            return ['status' => 'error', 'message' => __('api.error.db_error')];
        }
    } else {
        logSecurityEvent($pdo, "uid_".$userId, '2fa_setup_fail');
        return ['status' => 'error', 'message' => __('api.error.invalid_code')];
    }
}

function disable_2fa($pdo, $userId, $password) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();

    if (!password_verify($password, $storedHash)) {
         return ['status' => 'error', 'message' => __('api.error.current_password_invalid')];
    }

    $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = NULL, two_factor_enabled = 0, two_factor_recovery_codes = NULL WHERE id = ?");
    if ($stmt->execute([$userId])) {
        logSecurityEvent($pdo, "uid_".$userId, '2fa_disabled');
        return ['status' => 'success', 'message' => __('api.success.2fa_disabled')];
    } else {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function update_user_password($pdo, $userId, $currentPass, $newPass) {
    // Rate Limit preventivo: 5 intentos cada 15 min
    checkRateLimit($pdo, "uid_".$userId, 'pass_change_fail', 5, 15);

    if (empty($currentPass) || empty($newPass)) {
        return ['status' => 'error', 'message' => __('api.error.missing_data')];
    }
    if (strlen($newPass) < 8) {
        return ['status' => 'error', 'message' => __('api.error.password_short')];
    }
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();
    if (!password_verify($currentPass, $storedHash)) {
        logSecurityEvent($pdo, "uid_".$userId, 'pass_change_fail');
        return ['status' => 'error', 'message' => __('api.error.current_password_invalid')];
    }
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($update->execute([$newHash, $userId])) {
        logSecurityEvent($pdo, "uid_".$userId, 'pass_change_success');
        return ['status' => 'success', 'message' => __('api.success.password_updated')];
    } else {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}
?>