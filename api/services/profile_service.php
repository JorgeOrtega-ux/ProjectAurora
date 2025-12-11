<?php
// api/services/profile_service.php

/**
 * Servicio de Perfil
 * Maneja Actualización de Datos, Preferencias y Avatar.
 */

function update_profile_data($pdo, $userId, $newUsername, $newEmail) {
    if (empty($newUsername) || empty($newEmail)) return ['status' => 'error', 'message' => __('api.error.missing_data')];
    if (strlen($newUsername) < 6) return ['status' => 'error', 'message' => __('api.error.username_short')];
    
    $emailVal = validateEmailRequirements($newEmail);
    if ($emailVal !== true) return ['status' => 'error', 'message' => $emailVal];

    $stmtCurrent = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmtCurrent->execute([$userId]);
    $currentUserData = $stmtCurrent->fetch();
    
    if (!$currentUserData) return ['status' => 'error', 'message' => __('api.error.db_error')];

    $oldUsername = $currentUserData['username'];
    $oldEmail = $currentUserData['email'];
    
    $usernameChanged = ($oldUsername !== $newUsername);
    $emailChanged = ($oldEmail !== $newEmail);

    if (!$usernameChanged && !$emailChanged) {
        return ['status' => 'success', 'message' => __('api.success.profile_updated'), 'data' => ['username' => $newUsername, 'email' => $newEmail]];
    }

    if ($usernameChanged) {
        if (!checkProfileChangeLimit($pdo, $userId, 'username', 12, 1)) {
            return ['status' => 'error', 'message' => __('api.error.limit_username')];
        }
    }
    if ($emailChanged) {
         if (!checkProfileChangeLimit($pdo, $userId, 'email', 12, 1)) {
            return ['status' => 'error', 'message' => __('api.error.limit_email')];
        }
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmtCheck->execute([$newUsername, $newEmail, $userId]);
    if ($stmtCheck->rowCount() > 0) {
        return ['status' => 'error', 'message' => __('api.error.username_exists')];
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

        return ['status' => 'success', 'message' => __('api.success.profile_updated'), 'data' => ['username' => $newUsername, 'email' => $newEmail]];
    } else {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function update_preferences($pdo, $userId, $input) {
    $language = $input['language'] ?? null;
    $openLinks = isset($input['open_links_new_tab']) ? (int)$input['open_links_new_tab'] : null;
    $theme = $input['theme'] ?? null;
    $extendedAlerts = isset($input['extended_alerts']) ? (int)$input['extended_alerts'] : null;

    // --- ANTI-SPAM LOGIC START ---
    $spamIdentifier = "uid_" . $userId;

    // 1. Verificar si ya está bloqueado
    $stmtBlock = $pdo->prepare("SELECT id FROM security_logs WHERE user_identifier = ? AND action_type = 'pref_spam_blocked' AND created_at > (NOW() - INTERVAL 1 MINUTE) LIMIT 1");
    $stmtBlock->execute([$spamIdentifier]);
    if ($stmtBlock->fetch()) {
        http_response_code(429); // Too Many Requests
        return ['status' => 'error', 'message' => __('api.error.rate_limit')];
    }

    // 2. Contar actualizaciones legítimas en el último minuto
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE user_identifier = ? AND action_type = 'pref_update' AND created_at > (NOW() - INTERVAL 1 MINUTE)");
    $stmtCount->execute([$spamIdentifier]);
    $count = $stmtCount->fetchColumn();

    // 3. Decisión
    if ($count >= 5) {
        logSecurityEvent($pdo, $spamIdentifier, 'pref_spam_blocked');
        http_response_code(429);
        return ['status' => 'error', 'message' => __('api.error.rate_limit')];
    }
    // --- ANTI-SPAM LOGIC END ---

    $fields = [];
    $params = [];
    $allowedLangs = ['es-419', 'en-US', 'en-GB', 'fr-FR', 'pt-BR'];

    if ($language && in_array($language, $allowedLangs)) {
        $fields[] = "language = ?";
        $params[] = $language;
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
        return ['status' => 'success', 'message' => __('api.success.no_changes')]; 
    }
    
    $params[] = $userId;
    $sql = "UPDATE user_preferences SET " . implode(', ', $fields) . " WHERE user_id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            logSecurityEvent($pdo, $spamIdentifier, 'pref_update');
            
            // Retorno de traducciones si cambió el idioma
            $responseData = null;
            if ($language && in_array($language, $allowedLangs)) {
                load_translations($language); 
                $responseData = ['translations' => $GLOBALS['AURORA_TRANSLATIONS']];
            }

            return ['status' => 'success', 'message' => __('api.success.preferences_saved'), 'data' => $responseData];
        } else {
            return ['status' => 'error', 'message' => __('api.error.db_error')];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function handle_upload_avatar($pdo, $userId, $fileInput) {
    global $basePath;

    if (!checkProfileChangeLimit($pdo, $userId, 'avatar', 1, 3)) {
        return ['status' => 'error', 'message' => __('api.error.limit_avatar')];
    }

    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) {
         return ['status' => 'error', 'message' => __('error.load_content')];
    }
    
    $file = $fileInput;
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime, $allowedTypes)) {
        return ['status' => 'error', 'message' => __('api.error.upload_format')];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['status' => 'error', 'message' => __('api.error.upload_size')];
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
        return ['status' => 'success', 'message' => __('api.success.photo_updated'), 'data' => ['url' => $url]];
    } else {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function handle_delete_avatar($pdo, $userId) {
     global $basePath;
     
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
     return ['status' => 'success', 'message' => __('api.success.photo_deleted'), 'data' => ['url' => $defaultUrl]];
}
?>