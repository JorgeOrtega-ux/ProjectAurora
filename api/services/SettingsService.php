<?php
// api/services/SettingsService.php

require_once __DIR__ . '/../../includes/libs/GoogleAuthenticator.php';

class SettingsService {
    private $pdo;
    private $i18n;
    private $userId;

    public function __construct($pdo, $i18n, $userId) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->userId = $userId;
    }

    public function uploadAvatar($files) {
        if (!isset($files['avatar']) || $files['avatar']['error'] !== UPLOAD_ERR_OK) { 
            return ['success' => false, 'message' => $this->i18n->t('api.no_image')]; 
        }
        
        $file = $files['avatar'];

        // [SEGURIDAD] Validación de Tamaño (Max 2MB)
        if ($file['size'] > 2097152) {
            return ['success' => false, 'message' => $this->i18n->t('api.avatar_size_limit')];
        }

        // [SEGURIDAD] Límite de frecuencia (3 veces en 24 horas)
        if ($this->checkRateLimitExceeded('avatar_update', 24, 3)) {
            return ['success' => false, 'message' => $this->i18n->t('api.avatar_rate_limit')];
        }

        // [SEGURIDAD] Mapeo estricto de MIME a Extensión
        // Esto evita que se usen extensiones maliciosas (ej: .php) aunque el archivo sea una imagen válida.
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        // Validamos usando las llaves del array (MIME types)
        if (!array_key_exists($mime, $allowedTypes)) { 
            return ['success' => false, 'message' => $this->i18n->t('api.image_format')]; 
        }

        $stmt = $this->pdo->prepare("SELECT avatar_path, uuid FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $currentUser = $stmt->fetch();
        
        $oldPath = $currentUser['avatar_path'];
        
        // [SEGURIDAD] Asignamos la extensión basada en el MIME detectado, IGNORANDO $file['name']
        $extension = $allowedTypes[$mime];
        
        $newFileName = $currentUser['uuid'] . '-' . time() . '.' . $extension;
        
        $baseDir = __DIR__ . '/../../storage/profilePicture/custom/';
        if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);
        
        $targetPath = $baseDir . $newFileName;
        $dbPath = 'storage/profilePicture/custom/' . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $update = $this->pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            if ($update->execute([$dbPath, $this->userId])) {
                
                // [LOG] Registramos como 'avatar_update' para el conteo
                $this->logProfileChange('avatar_update', $oldPath, $dbPath);

                $this->deleteOldAvatar($oldPath);
                $_SESSION['avatar'] = $dbPath;
                return ['success' => true, 'message' => $this->i18n->t('api.pic_updated'), 'new_src' => $dbPath, 'type' => 'custom'];
            } else {
                @unlink($targetPath);
                return ['success' => false, 'message' => $this->i18n->t('api.pic_db_error')];
            }
        }
        return ['success' => false, 'message' => $this->i18n->t('api.pic_move_error')];
    }

    public function deleteAvatar() {
        $stmt = $this->pdo->prepare("SELECT avatar_path, username, uuid FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $currentUser = $stmt->fetch();
        
        $oldPath = $currentUser['avatar_path'];
        
        // NOTA: No verificamos límite aquí para permitir volver al default siempre.
        
        $firstLetter = substr($currentUser['username'], 0, 1);
        $bgColors = ['40a060', 'a73d3d', '3d3da7', '3d9da7', '9d3da7'];
        $randomBg = $bgColors[array_rand($bgColors)];
        
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=" . $randomBg . "&color=fff&size=512&format=png&bold=true";
        
        $baseDir = __DIR__ . '/../../storage/profilePicture/default/';
        if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

        $newFileName = $currentUser['uuid'] . '-' . time() . '.png';
        $targetPath = $baseDir . $newFileName;
        $dbPath = 'storage/profilePicture/default/' . $newFileName;
        
        $imageContent = @file_get_contents($avatarUrl);
        if ($imageContent !== false && file_put_contents($targetPath, $imageContent)) {
            $update = $this->pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            if ($update->execute([$dbPath, $this->userId])) {
                
                // [LOG] Registramos como 'avatar_delete' (no afecta al rate limit de update)
                $this->logProfileChange('avatar_delete', $oldPath, $dbPath . ' (Default)');

                $this->deleteOldAvatar($oldPath);
                $_SESSION['avatar'] = $dbPath;
                return ['success' => true, 'message' => $this->i18n->t('api.pic_deleted'), 'type' => 'default'];
            }
            return ['success' => false, 'message' => $this->i18n->t('api.pic_db_error')];
        }
        return ['success' => false, 'message' => $this->i18n->t('api.pic_gen_error')];
    }

    public function updateProfile($field, $value) {
        if (!in_array($field, ['username', 'email'])) return ['success' => false, 'message' => $this->i18n->t('api.field_invalid')];
        if (empty($value)) return ['success' => false, 'message' => $this->i18n->t('api.field_empty')];
        
        if ($field === 'email') { 
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => $this->i18n->t('api.email_invalid')];
        }

        // [SEGURIDAD] Límite de identidad: 1 vez cada 12 días (288 horas)
        if ($this->checkRateLimitExceeded($field, 288, 1)) {
            return ['success' => false, 'message' => $this->i18n->t('api.identity_rate_limit')];
        }

        // Verificar duplicados
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE $field = ? AND id != ?");
        $stmt->execute([$value, $this->userId]);
        if ($stmt->fetch()) return ['success' => false, 'message' => $this->i18n->t('api.field_in_use')];

        // Obtener valor antiguo
        $stmtOld = $this->pdo->prepare("SELECT $field FROM users WHERE id = ?");
        $stmtOld->execute([$this->userId]);
        $oldValue = $stmtOld->fetchColumn();

        if ($oldValue === $value) {
             return ['success' => true, 'message' => $this->i18n->t('api.field_updated')];
        }

        try {
            $update = $this->pdo->prepare("UPDATE users SET $field = ? WHERE id = ?");
            $update->execute([$value, $this->userId]);
            
            // [LOG] Registramos el cambio para activar el cooldown de 12 días
            $this->logProfileChange($field, $oldValue, $value);

            $_SESSION[$field] = $value;
            return ['success' => true, 'message' => $this->i18n->t('api.field_updated')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error') . ': ' . $e->getMessage()];
        }
    }

    public function validateCurrentPassword($currentPass) {
        if (empty($currentPass)) return ['success' => false, 'message' => $this->i18n->t('api.pass_current_req')];
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        if ($user && password_verify($currentPass, $user['password'])) {
            return ['success' => true, 'message' => $this->i18n->t('api.pass_correct')];
        }
        return ['success' => false, 'message' => $this->i18n->t('api.pass_incorrect')];
    }

    public function changePassword($currentPass, $newPass) {
        if (empty($currentPass) || empty($newPass)) return ['success' => false, 'message' => $this->i18n->t('api.missing_data')];
        if (strlen($newPass) < 6) return ['success' => false, 'message' => $this->i18n->t('api.pass_short')];
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($currentPass, $user['password'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.pass_incorrect')];
        }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        try {
            $update = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$newHash, $this->userId]);
            
            $this->logProfileChange('password', '***', '***');
            return ['success' => true, 'message' => $this->i18n->t('api.pass_updated')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error')];
        }
    }

    public function updatePreference($key, $value) {
        // [SEGURIDAD] Límite Anti-Spam (10 cambios por minuto)
        if ($this->checkPreferenceRateLimit()) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_rate_limit')];
        }

        $allowedKeys = ['language', 'open_links_new_tab', 'theme', 'extended_toast'];
        if (!in_array($key, $allowedKeys)) return ['success' => false, 'message' => $this->i18n->t('api.pref_invalid')];

        $dbValue = $value;
        if ($key === 'language') {
            $allowedLangs = ['es-latam', 'es-mx', 'en-us', 'en-gb', 'fr-fr'];
            if (!in_array($value, $allowedLangs)) return ['success' => false, 'message' => $this->i18n->t('api.lang_invalid')];
        } elseif ($key === 'open_links_new_tab' || $key === 'extended_toast') {
            $dbValue = ($value === 'true' || $value === '1') ? 1 : 0;
        } elseif ($key === 'theme') {
            $allowedThemes = ['sync', 'light', 'dark'];
            if (!in_array($value, $allowedThemes)) return ['success' => false, 'message' => $this->i18n->t('api.theme_invalid')];
        }

        try {
            // Check if needs update
            $stmtCheck = $this->pdo->prepare("SELECT $key FROM user_preferences WHERE user_id = ?");
            $stmtCheck->execute([$this->userId]);
            $currentDbValue = $stmtCheck->fetchColumn();
            
            if ($currentDbValue !== false) {
                $val1 = $currentDbValue; 
                $val2 = $dbValue;
                if ($key === 'open_links_new_tab' || $key === 'extended_toast') {
                    $val1 = (int)$val1; $val2 = (int)$val2;
                }
                if ($val1 === $val2) return ['success' => true, 'message' => $this->i18n->t('api.pref_saved_no_change')];
            }

            $sql = "INSERT INTO user_preferences (user_id, $key) VALUES (?, ?) ON DUPLICATE KEY UPDATE $key = VALUES($key)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->userId, $dbValue]);

            if (!isset($_SESSION['preferences'])) $_SESSION['preferences'] = [];
            $_SESSION['preferences'][$key] = ($key === 'open_links_new_tab' || $key === 'extended_toast') ? (bool)$dbValue : $dbValue;

            // [LOG] Registramos en security_logs para el anti-spam
            $this->logPreferenceUpdate();

            return ['success' => true, 'message' => $this->i18n->t('api.pref_saved')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_save_error') . ': ' . $e->getMessage()];
        }
    }

    public function init2fa() {
        $secret = GoogleAuthenticator::createSecret();
        $_SESSION['temp_2fa_secret'] = $secret;
        $username = $_SESSION['username'] ?? 'User';
        $otpauthUrl = GoogleAuthenticator::getQrUrl($username, $secret, 'ProjectAurora');
        return ['success' => true, 'message' => $this->i18n->t('api.qr_scan'), 'otpauth_url' => $otpauthUrl, 'secret' => $secret];
    }

    public function enable2fa($code) {
        if (!isset($_SESSION['temp_2fa_secret'])) return ['success' => false, 'message' => $this->i18n->t('api.session_config_expired')];
        $secret = $_SESSION['temp_2fa_secret'];
        if (GoogleAuthenticator::verifyCode($secret, $code)) {
            $recoveryCodes = [];
            for($i=0; $i<8; $i++) $recoveryCodes[] = bin2hex(random_bytes(4));
            $jsonCodes = json_encode($recoveryCodes);
            $stmt = $this->pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_recovery_codes = ? WHERE id = ?");
            if ($stmt->execute([$secret, $jsonCodes, $this->userId])) {
                unset($_SESSION['temp_2fa_secret']);
                $_SESSION['two_factor_enabled'] = 1;
                $this->logProfileChange('2fa', 'disabled', 'enabled');
                return ['success' => true, 'message' => $this->i18n->t('api.2fa_enabled'), 'recovery_codes' => $recoveryCodes];
            }
            return ['success' => false, 'message' => $this->i18n->t('api.pic_db_error')];
        }
        return ['success' => false, 'message' => $this->i18n->t('api.code_invalid')];
    }

    public function disable2fa() {
        $stmt = $this->pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_recovery_codes = NULL WHERE id = ?");
        if ($stmt->execute([$this->userId])) {
            $_SESSION['two_factor_enabled'] = 0;
            $this->logProfileChange('2fa', 'enabled', 'disabled');
            return ['success' => true, 'message' => $this->i18n->t('api.2fa_disabled')];
        }
        return ['success' => false, 'message' => $this->i18n->t('api.update_error')];
    }

    public function getSessions() {
        $stmt = $this->pdo->prepare("SELECT id, selector, ip_address, user_agent, created_at FROM user_auth_tokens WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$this->userId]);
        $sessions = $stmt->fetchAll();
        $currentSelector = '';
        if (isset($_COOKIE['auth_persistence_token'])) {
            $parts = explode(':', $_COOKIE['auth_persistence_token']);
            if (count($parts) === 2) $currentSelector = $parts[0];
        }
        $formatted = [];
        foreach($sessions as $s) {
            $info = $this->parseUserAgent($s['user_agent'] ?? '');
            $isCurrent = ($s['selector'] === $currentSelector);
            $formatted[] = [
                'id' => $s['id'],
                'ip' => $s['ip_address'] ?? 'Desconocida',
                'platform' => $info['platform'],
                'browser' => $info['browser'],
                'created_at' => $s['created_at'],
                'is_current' => $isCurrent
            ];
        }
        return ['success' => true, 'message' => $this->i18n->t('api.sessions_list'), 'sessions' => $formatted];
    }

    public function revokeSession($tokenId) {
        if(!$tokenId) return ['success' => false, 'message' => $this->i18n->t('api.id_invalid')];
        $stmt = $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE id = ? AND user_id = ?");
        $stmt->execute([$tokenId, $this->userId]);
        if ($stmt->rowCount() > 0) return ['success' => true, 'message' => $this->i18n->t('api.session_revoked')];
        return ['success' => false, 'message' => $this->i18n->t('api.session_revoke_error')];
    }

    public function revokeAllSessions() {
        $stmt = $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        return ['success' => true, 'message' => $this->i18n->t('api.all_sessions_revoked'), 'logout' => true];
    }

    public function deleteAccount($password) {
        if (empty($password)) return ['success' => false, 'message' => $this->i18n->t('api.pass_req_confirm')];
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => $this->i18n->t('api.pass_incorrect')];
        }
        try {
            $this->pdo->beginTransaction();
            $update = $this->pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE id = ?");
            $update->execute([$this->userId]);
            $deleteTokens = $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?");
            $deleteTokens->execute([$this->userId]);
            $this->logProfileChange('account_status', 'active', 'deleted');
            $this->pdo->commit();
            setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
            session_destroy();
            return ['success' => true, 'message' => $this->i18n->t('api.account_deleted_success')];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->i18n->t('api.internal_error')];
        }
    }

    // =========================================================================
    //  MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Verifica si se ha excedido el límite de cambios en profile_changes.
     * @param string $changeType 'avatar_update', 'username', 'email'
     * @param int $hours Horas a verificar hacia atrás
     * @param int $limit Límite de registros permitidos
     * @return bool
     */
    private function checkRateLimitExceeded($changeType, $hours, $limit) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM profile_changes 
            WHERE user_id = ? 
            AND change_type = ? 
            AND created_at > (NOW() - INTERVAL $hours HOUR)
        ");
        $stmt->execute([$this->userId, $changeType]);
        $count = $stmt->fetchColumn();

        return ($count >= $limit);
    }

    /**
     * Verifica si el usuario está haciendo spam de cambios de preferencias (security_logs).
     * @return bool
     */
    private function checkPreferenceRateLimit() {
        $limit = 10; // 10 cambios
        $minutes = 1; // en 1 minuto
        
        $sql = "SELECT COUNT(*) FROM security_logs 
                WHERE user_identifier = ? 
                AND action_type = 'pref_update' 
                AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId]); 
        $count = $stmt->fetchColumn();

        return ($count >= $limit);
    }

    private function logPreferenceUpdate() {
        $ip = $this->getClientIp();
        $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, 'pref_update', ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId, $ip]);
    }

    private function deleteOldAvatar($currentPath) {
        if ($currentPath && file_exists(__DIR__ . '/../../' . $currentPath)) {
            @unlink(__DIR__ . '/../../' . $currentPath);
        }
    }

    private function parseUserAgent($ua) {
        $platform = 'Desconocido'; $browser = 'Desconocido';
        if (preg_match('/windows|win32/i', $ua)) $platform = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'Mac OS';
        elseif (preg_match('/linux/i', $ua)) $platform = 'Linux';
        elseif (preg_match('/android/i', $ua)) $platform = 'Android';
        elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $platform = 'iOS';
        if (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';
        elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
        elseif (preg_match('/Opera|OPR/i', $ua)) $browser = 'Opera';
        elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';
        return ['platform' => $platform, 'browser' => $browser];
    }
    
    private function logProfileChange($changeType, $oldValue, $newValue) {
        $ip = $this->getClientIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $sql = "INSERT INTO profile_changes (user_id, change_type, old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([$this->userId, $changeType, $oldValue, $newValue, $ip, $ua]);
        } catch (Exception $e) {
            error_log("Error logging profile change: " . $e->getMessage());
        }
    }

    private function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
        else return $_SERVER['REMOTE_ADDR'];
    }
}
?>