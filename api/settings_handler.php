<?php
// api/settings_handler.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/../GoogleAuthenticator.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

$lang = null;
try {
    $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lang = $stmt->fetchColumn();
} catch(Exception $e){}

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
    $ga = new PHPGangsta_GoogleAuthenticator();

    // ==========================================
    // NUEVO: VERIFICAR CONTRASEÑA ACTUAL (SIN CAMBIAR)
    // ==========================================
    if ($action === 'verify_current_password') {
        $password = $input['password'] ?? '';
        
        // Rate Limit suave para evitar fuerza bruta en este endpoint
        checkRateLimit($pdo, "uid_".$userId, 'pass_verify_check', 10, 5);

        if (empty($password)) {
            sendJsonResponse('error', __('api.error.missing_data'));
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $storedHash = $stmt->fetchColumn();

        if (password_verify($password, $storedHash)) {
            sendJsonResponse('success', 'Password OK');
        } else {
            sendJsonResponse('error', __('api.error.current_password_invalid'));
        }
    }

    // ==========================================
    // ELIMINAR CUENTA
    // ==========================================
    if ($action === 'delete_account') {
        $password = $input['password'] ?? '';
        
        // [SEGURIDAD] Rate Limit preventivo: 5 intentos cada 30 min por usuario
        checkRateLimit($pdo, "uid_".$userId, 'delete_account_fail', 5, 30);

        if (empty($password)) {
            sendJsonResponse('error', __('api.error.missing_data'));
        }

        // 1. Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $storedHash = $stmt->fetchColumn();

        if (!password_verify($password, $storedHash)) {
            logSecurityEvent($pdo, "uid_".$userId, 'delete_account_fail');
            sendJsonResponse('error', __('api.error.current_password_invalid'));
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

                sendJsonResponse('success', 'Tu cuenta ha sido eliminada correctamente.', $basePath . 'login');
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // 1. Obtener sesiones activas
    if ($action === 'get_active_sessions') {
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
        
        sendJsonResponse('success', 'OK', null, $data);
    }

    // 2. Revocar sesión individual
    if ($action === 'revoke_session') {
        $sessionIdDb = $input['session_db_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$sessionIdDb, $userId])) {
            sendJsonResponse('success', 'Sesión cerrada exitosamente.');
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // 3. Revocar todas las sesiones
    if ($action === 'revoke_all_sessions') {
        $password = $input['password'] ?? '';
        $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmtPass->execute([$userId]);
        $storedHash = $stmtPass->fetchColumn();
        if (!password_verify($password, $storedHash)) {
            sendJsonResponse('error', __('api.error.current_password_invalid'));
        }

        $currentSessionId = session_id();
        $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ? AND session_id != ?");
        
        if ($stmt->execute([$userId, $currentSessionId])) {
            sendJsonResponse('success', 'Todas las demás sesiones han sido cerradas.');
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // 2FA - INICIALIZAR (Paso 1: Generar QR)
    if ($action === 'init_2fa') {
        $password = $input['current_password'] ?? '';
        
        // [SEGURIDAD] Rate Limit para evitar fuerza bruta de contraseña en este paso
        // 5 intentos cada 15 minutos.
        checkRateLimit($pdo, "uid_".$userId, '2fa_init_fail', 5, 15);

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $storedHash = $stmt->fetchColumn();

        if (!password_verify($password, $storedHash)) {
             logSecurityEvent($pdo, "uid_".$userId, '2fa_init_fail');
             sendJsonResponse('error', __('api.error.current_password_invalid'));
        }

        $secret = $ga->createSecret();
        $_SESSION['temp_2fa_secret'] = $secret; 

        $username = $_SESSION['username'];
        $qrCodeUrl = $ga->getQRCodeGoogleUrl('ProjectAurora (' . $username . ')', $secret);

        sendJsonResponse('success', 'OK', null, [
            'qr_url' => $qrCodeUrl,
            'secret' => $secret
        ]);
    }

    // 2FA - ACTIVAR (Paso 2: Verificar código)
    if ($action === 'enable_2fa') {
        $code = trim($input['code'] ?? '');
        $secret = $_SESSION['temp_2fa_secret'] ?? null;

        // [SEGURIDAD] Rate Limit para evitar fuerza bruta en el setup
        checkRateLimit($pdo, "uid_".$userId, '2fa_setup_fail', 5, 10);

        if (!$secret || empty($code)) {
            sendJsonResponse('error', __('api.error.missing_data'));
        }

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
                
                sendJsonResponse('success', '2FA Activado Correctamente', null, [
                    'recovery_codes' => $recoveryCodes
                ]);
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } else {
            // [SEGURIDAD] Registrar fallo
            logSecurityEvent($pdo, "uid_".$userId, '2fa_setup_fail');
            sendJsonResponse('error', __('api.error.invalid_code'));
        }
    }

    if ($action === 'disable_2fa') {
        $password = $input['current_password'] ?? '';
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $storedHash = $stmt->fetchColumn();

        if (!password_verify($password, $storedHash)) {
             sendJsonResponse('error', __('api.error.current_password_invalid'));
        }

        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = NULL, two_factor_enabled = 0, two_factor_recovery_codes = NULL WHERE id = ?");
        if ($stmt->execute([$userId])) {
            logSecurityEvent($pdo, "uid_".$userId, '2fa_disabled');
            sendJsonResponse('success', 'Autenticación en dos pasos desactivada.');
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // ACTUALIZAR PERFIL
    if ($action === 'update_profile') {
        $newUsername = trim($input['username'] ?? '');
        $newEmail = trim($input['email'] ?? '');
        
        if (empty($newUsername) || empty($newEmail)) sendJsonResponse('error', __('api.error.missing_data'));
        if (strlen($newUsername) < 6) sendJsonResponse('error', __('api.error.username_short'));
        
        $emailVal = validateEmailRequirements($newEmail);
        if ($emailVal !== true) sendJsonResponse('error', $emailVal);

        $stmtCurrent = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmtCurrent->execute([$userId]);
        $currentUserData = $stmtCurrent->fetch();
        
        if (!$currentUserData) sendJsonResponse('error', __('api.error.db_error'));

        $oldUsername = $currentUserData['username'];
        $oldEmail = $currentUserData['email'];
        
        $usernameChanged = ($oldUsername !== $newUsername);
        $emailChanged = ($oldEmail !== $newEmail);

        if (!$usernameChanged && !$emailChanged) {
            sendJsonResponse('success', __('api.success.profile_updated'), null, ['username' => $newUsername, 'email' => $newEmail]);
        }

        if ($usernameChanged) {
            if (!checkProfileChangeLimit($pdo, $userId, 'username', 12, 1)) {
                sendJsonResponse('error', __('api.error.limit_username'));
            }
        }
        if ($emailChanged) {
             if (!checkProfileChangeLimit($pdo, $userId, 'email', 12, 1)) {
                sendJsonResponse('error', __('api.error.limit_email'));
            }
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmtCheck->execute([$newUsername, $newEmail, $userId]);
        if ($stmtCheck->rowCount() > 0) {
            sendJsonResponse('error', __('api.error.username_exists'));
        }

        $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        if ($updateStmt->execute([$newUsername, $newEmail, $userId])) {
            if ($usernameChanged) {
                logProfileChange($pdo, $userId, 'username', $oldUsername, $newUsername);
                $_SESSION['username'] = $newUsername;
            }
            if ($emailChanged) {
                logProfileChange($pdo, $userId, 'email', $oldEmail, $newEmail);
            }

            sendJsonResponse('success', __('api.success.profile_updated'), null, ['username' => $newUsername, 'email' => $newEmail]);
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // PREFERENCIAS (MODIFICADO: ANTI-SPAM LOGIC)
    if ($action === 'update_preferences') {
        $language = $input['language'] ?? null;
        $openLinks = isset($input['open_links_new_tab']) ? (int)$input['open_links_new_tab'] : null;
        $theme = $input['theme'] ?? null;
        $extendedAlerts = isset($input['extended_alerts']) ? (int)$input['extended_alerts'] : null;

        // --- ANTI-SPAM LOGIC START ---
        $spamIdentifier = "uid_" . $userId;

        // 1. Verificar si ya está bloqueado (Silencio Táctico)
        $stmtBlock = $pdo->prepare("SELECT id FROM security_logs WHERE user_identifier = ? AND action_type = 'pref_spam_blocked' AND created_at > (NOW() - INTERVAL 1 MINUTE) LIMIT 1");
        $stmtBlock->execute([$spamIdentifier]);
        if ($stmtBlock->fetch()) {
            http_response_code(429); // Too Many Requests
            sendJsonResponse('error', __('api.error.rate_limit'));
        }

        // 2. Contar actualizaciones legítimas en el último minuto
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE user_identifier = ? AND action_type = 'pref_update' AND created_at > (NOW() - INTERVAL 1 MINUTE)");
        $stmtCount->execute([$spamIdentifier]);
        $count = $stmtCount->fetchColumn();

        // 3. Decisión
        if ($count >= 5) {
            // Caso B: Gatillo de Bloqueo
            logSecurityEvent($pdo, $spamIdentifier, 'pref_spam_blocked');
            http_response_code(429);
            sendJsonResponse('error', __('api.error.rate_limit'));
        }
        // --- ANTI-SPAM LOGIC END ---

        $fields = [];
        $params = [];

        if ($language) {
            $allowedLangs = ['es-419', 'en-US', 'en-GB', 'fr-FR', 'pt-BR'];
            if (in_array($language, $allowedLangs)) {
                $fields[] = "language = ?";
                $params[] = $language;
            }
        }
        if ($openLinks !== null) {
            $fields[] = "open_links_new_tab = ?";
            $params[] = $openLinks; 
        }
        if ($theme) {
            $allowedThemes = ['system', 'light', 'dark'];
            if (in_array($theme, $allowedThemes)) {
                $fields[] = "theme = ?";
                $params[] = $theme;
            }
        }
        if ($extendedAlerts !== null) {
            $fields[] = "extended_alerts = ?";
            $params[] = $extendedAlerts;
        }

        if (empty($fields)) {
            sendJsonResponse('success', "OK (Sin cambios)"); 
        }
        
        $params[] = $userId;
        $sql = "UPDATE user_preferences SET " . implode(', ', $fields) . " WHERE user_id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                // Caso A: Registro de Éxito
                logSecurityEvent($pdo, $spamIdentifier, 'pref_update');
                sendJsonResponse('success', __('api.success.preferences_saved'));
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // SUBIR FOTO
    if ($action === 'upload_profile_picture') {
        if (!checkProfileChangeLimit($pdo, $userId, 'avatar', 1, 3)) {
            sendJsonResponse('error', __('api.error.limit_avatar'));
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
             sendJsonResponse('error', __('error.load_content'));
        }
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedTypes)) {
            sendJsonResponse('error', __('api.error.upload_format'));
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            sendJsonResponse('error', __('api.error.upload_size'));
        }
        
        $uuid = $_SESSION['uuid'];
        $targetFile = DIR_CUSTOM . $uuid . '.png';
        $oldValue = file_exists($targetFile) ? 'Custom Avatar (Overwritten)' : 'Default Avatar';

        $src = null;
        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $src = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
        }
        $uploadSuccess = false;
        if ($src) {
            imagepng($src, $targetFile, 9);
            imagedestroy($src);
            $uploadSuccess = true;
        } else {
             if(move_uploaded_file($file['tmp_name'], $targetFile)) $uploadSuccess = true;
        }
        
        if ($uploadSuccess) {
            $defaultFile = DIR_DEFAULT . $uuid . '.png';
            if (file_exists($defaultFile)) unlink($defaultFile);
            
            $url = $basePath . URL_BASE_AVATARS . 'custom/' . $uuid . '.png?v=' . time();
            logProfileChange($pdo, $userId, 'avatar', $oldValue, 'New Custom Avatar');
            sendJsonResponse('success', __('api.success.photo_updated'), null, ['url' => $url]);
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // DELETE PHOTO
    if ($action === 'delete_profile_picture') {
         $uuid = $_SESSION['uuid'];
         $username = $_SESSION['username'];
         $customFile = DIR_CUSTOM . $uuid . '.png';
         
         $wasCustom = false;
         if (file_exists($customFile)) {
             unlink($customFile);
             $wasCustom = true;
         }
         
         $defaultFile = DIR_DEFAULT . $uuid . '.png';
         if (file_exists($defaultFile)) unlink($defaultFile);
         
         ensureDefaultAvatarExists($uuid, $username);
         
         if ($wasCustom) {
            logProfileChange($pdo, $userId, 'avatar', 'Custom Avatar', 'Default Avatar');
         }

         $defaultUrl = $basePath . URL_BASE_AVATARS . 'default/' . $uuid . '.png?v=' . time();
         sendJsonResponse('success', __('api.success.photo_deleted'), null, ['url' => $defaultUrl]);
    }

    // UPDATE PASSWORD
    if ($action === 'update_password') {
        $currentPass = $input['current_password'] ?? '';
        $newPass     = $input['new_password'] ?? '';
        
        // [SEGURIDAD] Rate Limit preventivo: 5 intentos cada 15 min
        checkRateLimit($pdo, "uid_".$userId, 'pass_change_fail', 5, 15);

        if (empty($currentPass) || empty($newPass)) {
            sendJsonResponse('error', __('api.error.missing_data'));
        }
        if (strlen($newPass) < 8) {
            sendJsonResponse('error', __('api.error.password_short'));
        }
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $storedHash = $stmt->fetchColumn();
        if (!password_verify($currentPass, $storedHash)) {
            // Solo logueamos, el rate limit ya fue verificado arriba
            logSecurityEvent($pdo, "uid_".$userId, 'pass_change_fail');
            sendJsonResponse('error', __('api.error.current_password_invalid'));
        }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($update->execute([$newHash, $userId])) {
            logSecurityEvent($pdo, "uid_".$userId, 'pass_change_success');
            sendJsonResponse('success', __('api.success.password_updated'));
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    sendJsonResponse('error', "Action invalid (Settings Handler)");
}
?>