<?php
// api/services/SettingsService.php

use Google\Authenticator\GoogleAuthenticator;

require_once __DIR__ . '/../../includes/libs/MailService.php';

class SettingsService {
    private $pdo;
    private $i18n;
    private $userId;
    private $redis;

    public function __construct($pdo, $i18n, $userId, $redis) {
        $this->pdo = $pdo;
        $this->i18n = $i18n;
        $this->userId = $userId;
        $this->redis = $redis;
    }

    public function uploadAvatar($files) {
        if (!isset($files['avatar']) || $files['avatar']['error'] !== UPLOAD_ERR_OK) { 
            return ['success' => false, 'message' => $this->i18n->t('api.no_image')]; 
        }
        $file = $files['avatar'];
        
        $maxSize = (int)Utils::getServerConfig($this->pdo, 'upload_avatar_max_size', '2097152');
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1048576, 1);
            return ['success' => false, 'message' => $this->i18n->t('api.avatar_size_limit', [$maxSizeMB])]; 
        }

        $maxDim = (int)Utils::getServerConfig($this->pdo, 'upload_avatar_max_dim', '4096');
        list($width, $height) = getimagesize($file['tmp_name']);
        if ($width > $maxDim || $height > $maxDim) { 
            return ['success' => false, 'message' => $this->i18n->t('api.avatar_dimensions_limit', [$maxDim])]; 
        }

        if ($this->checkRateLimitExceeded('avatar_update', 24, 3)) { 
            return ['success' => false, 'message' => $this->i18n->t('api.avatar_rate_limit')]; 
        }

        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png'  => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!array_key_exists($mime, $allowedTypes)) { 
            return ['success' => false, 'message' => $this->i18n->t('api.image_format')]; 
        }

        $stmt = $this->pdo->prepare("SELECT avatar_path, uuid FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $currentUser = $stmt->fetch();
        $oldPath = $currentUser['avatar_path'];
        $extension = $allowedTypes[$mime];
        $newFileName = $currentUser['uuid'] . '-' . time() . '.' . $extension;
        $baseDir = __DIR__ . '/../../storage/profilePicture/custom/';
        if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);
        $targetPath = $baseDir . $newFileName;
        $dbPath = 'storage/profilePicture/custom/' . $newFileName;
        $imageSaved = false;
        if (extension_loaded('gd')) {
            switch ($mime) {
                case 'image/jpeg': $img = @imagecreatefromjpeg($file['tmp_name']); if ($img) { $imageSaved = imagejpeg($img, $targetPath, 90); imagedestroy($img); } break;
                case 'image/png': $img = @imagecreatefrompng($file['tmp_name']); if ($img) { imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true); $imageSaved = imagepng($img, $targetPath, 9); imagedestroy($img); } break;
                case 'image/webp': $img = @imagecreatefromwebp($file['tmp_name']); if ($img) { $imageSaved = imagewebp($img, $targetPath, 90); imagedestroy($img); } break;
            }
        } else { return ['success' => false, 'message' => $this->i18n->t('api.server_config_error')]; }
        
        if ($imageSaved) {
            $update = $this->pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            if ($update->execute([$dbPath, $this->userId])) {
                $this->logProfileChange('avatar_update', $oldPath, $dbPath);
                $this->deleteOldAvatar($oldPath);
                $_SESSION['avatar'] = $dbPath;
                
                $newContent = file_get_contents($targetPath);
                $base64 = 'data:' . $mime . ';base64,' . base64_encode($newContent);

                return ['success' => true, 'message' => $this->i18n->t('api.pic_updated'), 'new_src' => $base64, 'type' => 'custom'];
            } else { @unlink($targetPath); return ['success' => false, 'message' => $this->i18n->t('api.pic_db_error')]; }
        }
        return ['success' => false, 'message' => $this->i18n->t('api.pic_move_error')];
    }

    public function deleteAvatar() {
        $stmt = $this->pdo->prepare("SELECT avatar_path, username, uuid FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $currentUser = $stmt->fetch();
        $oldPath = $currentUser['avatar_path'];
        $firstLetter = substr($currentUser['username'], 0, 1);
        $bgColors = ['40a060', 'a73d3d', '3d3da7', '3d9da7', '9d3da7'];
        $randomBg = $bgColors[array_rand($bgColors)];
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=" . $randomBg . "&color=fff&size=512&format=png&bold=true";
        $baseDir = __DIR__ . '/../../storage/profilePicture/default/';
        if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);
        $newFileName = $currentUser['uuid'] . '-' . time() . '.png';
        $targetPath = $baseDir . $newFileName;
        $dbPath = 'storage/profilePicture/default/' . $newFileName;
        $imageContent = @file_get_contents($avatarUrl);
        if ($imageContent !== false && file_put_contents($targetPath, $imageContent)) {
            $update = $this->pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            if ($update->execute([$dbPath, $this->userId])) {
                $this->logProfileChange('avatar_delete', $oldPath, $dbPath . ' (Default)');
                $this->deleteOldAvatar($oldPath);
                $_SESSION['avatar'] = $dbPath;
                $base64Image = 'data:image/png;base64,' . base64_encode($imageContent);
                return ['success' => true, 'message' => $this->i18n->t('api.pic_deleted'), 'type' => 'default', 'new_src' => $base64Image];
            }
            return ['success' => false, 'message' => $this->i18n->t('api.pic_db_error')];
        }
        return ['success' => false, 'message' => $this->i18n->t('api.pic_gen_error')];
    }

    public function getEmailEditStatus() {
        if (isset($_SESSION['email_change_auth']) && $_SESSION['email_change_auth'] > time()) {
            return ['success' => true, 'status' => 'authorized'];
        }

        $stmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $email = $stmt->fetchColumn();

        // Definir ambas claves
        $mainKey = "verify:email_update:{$email}";         // Dura 15 min (la que contiene el código)
        $cooldownKey = "verify:cooldown:email_update:{$email}"; // Dura 60 seg (el bloqueo)

        // [CORRECCIÓN] Verificar primero si existe el proceso activo (Main Key)
        if ($this->redis->exists($mainKey)) {
            // Si existe el proceso, verificamos cuánto le queda al cooldown
            $ttl = $this->redis->ttl($cooldownKey);

            return [
                'success' => true, 
                'status' => 'pending_code', // Decimos que YA hay un código pendiente
                'cooldown' => ($ttl > 0) ? $ttl : 0
            ];
        }

        // Si no existe la Main Key, entonces realmente no hay nada activo
        return ['success' => true, 'status' => 'none'];
    }

    public function requestEmailChangeVerification($forceResend = false) {
        if ($this->checkRateLimitExceeded('email', 288, 1)) {
            return ['success' => false, 'message' => $this->i18n->t('api.identity_rate_limit')];
        }

        $stmt = $this->pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        if (!$user) return ['success' => false, 'message' => $this->i18n->t('api.internal_error')];

        $currentEmail = $user['email'];
        $username = $user['username'];

        // [MODIFICADO] Verificar clave principal en Redis
        $mainKey = "verify:email_update:{$currentEmail}";
        
        if (!$forceResend) {
            if ($this->redis->exists($mainKey)) {
                return ['success' => true, 'message' => 'Código ya enviado previamente.'];
            }
        }

        $limit = (int)Utils::getServerConfig($this->pdo, 'security_general_rate_limit', '10');
        
        if ($this->checkSecurityLimit('email_change_req', 3, 10)) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_rate_limit')];
        }

        // [MODIFICADO] Verificar Cooldown en Redis
        $cooldownKey = "verify:cooldown:email_update:{$currentEmail}";
        if ($this->redis->exists($cooldownKey)) {
            return ['success' => false, 'message' => $this->i18n->t('api.wait_resend')];
        }

        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiryMinutes = (int)Utils::getServerConfig($this->pdo, 'auth_verification_code_expiry', '15');
        
        // Payload simple (solo código, pues el email es la clave)
        $payload = json_encode(['code' => $code]);

        try {
            // Guardar en Redis (15 min de validez)
            $this->redis->setex($mainKey, $expiryMinutes * 60, $payload);
            
            // Establecer Cooldown (60s)
            $this->redis->setex($cooldownKey, 60, '1');
            
            $this->logSecurityAction('email_change_req', 10);

            $subject = "Verifica tu identidad - Project Aurora";
            $body = "<p>Hola $username,</p><p>Has solicitado cambiar tu correo electrónico. Para continuar, utiliza el siguiente código de seguridad:</p>
                     <h2 style='letter-spacing: 4px;'>$code</h2>
                     <p>Si no fuiste tú, cambia tu contraseña inmediatamente.</p>";
            
            MailService::send($currentEmail, $subject, $body);

            return ['success' => true, 'message' => $this->i18n->t('api.code_sent')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.internal_error')];
        }
    }

    public function verifyEmailChangeCode($code) {
        if (empty($code)) return ['success' => false, 'message' => $this->i18n->t('api.missing_data')];

        $stmtEmail = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmtEmail->execute([$this->userId]);
        $currentEmail = $stmtEmail->fetchColumn();

        // [MODIFICADO] Validar contra Redis
        $mainKey = "verify:email_update:{$currentEmail}";
        $dataJson = $this->redis->get($mainKey);

        if ($dataJson) {
            $data = json_decode($dataJson, true);
            if ($data['code'] === $code) {
                $_SESSION['email_change_auth'] = time() + 300;
                
                // Borrar clave tras éxito
                $this->redis->del($mainKey);
                $this->redis->del("verify:cooldown:email_update:{$currentEmail}");
                
                return ['success' => true, 'message' => 'Código verificado.'];
            }
        }
        
        return ['success' => false, 'message' => $this->i18n->t('api.code_invalid')];
    }

    public function updateProfile($field, $value) {
        if (!in_array($field, ['username', 'email'])) return ['success' => false, 'message' => $this->i18n->t('api.field_invalid')];
        if (empty($value)) return ['success' => false, 'message' => $this->i18n->t('api.field_empty')];
        
        if ($field === 'username') {
            $minUserLen = (int)Utils::getServerConfig($this->pdo, 'username_min_length', '4');
            $maxUserLen = (int)Utils::getServerConfig($this->pdo, 'username_max_length', '20');
            if (strlen($value) < $minUserLen || strlen($value) > $maxUserLen) {
                return ['success' => false, 'message' => $this->i18n->t('api.username_bounds', [$minUserLen, $maxUserLen])];
            }
        }
        
        if ($field === 'email') { 
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => $this->i18n->t('api.email_invalid')];
            
            $allowedDomainsStr = Utils::getServerConfig($this->pdo, 'email_allowed_domains', '*');
            if ($allowedDomainsStr !== '*' && trim($allowedDomainsStr) !== '') {
                $allowedDomains = array_map('trim', explode(',', strtolower($allowedDomainsStr)));
                $domain = substr(strrchr($value, "@"), 1);
                if (!in_array(strtolower($domain), $allowedDomains)) {
                    return ['success' => false, 'message' => $this->i18n->t('api.email_domain_not_allowed', [$domain])];
                }
            }

            if (!isset($_SESSION['email_change_auth']) || $_SESSION['email_change_auth'] < time()) {
                return ['success' => false, 'message' => $this->i18n->t('api.auth_required')];
            }
        }

        if ($this->checkRateLimitExceeded($field, 288, 1)) {
            return ['success' => false, 'message' => $this->i18n->t('api.identity_rate_limit')];
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE $field = ? AND id != ?");
        $stmt->execute([$value, $this->userId]);
        if ($stmt->fetch()) return ['success' => false, 'message' => $this->i18n->t('api.field_in_use')];

        $stmtOld = $this->pdo->prepare("SELECT $field FROM users WHERE id = ?");
        $stmtOld->execute([$this->userId]);
        $oldValue = $stmtOld->fetchColumn();

        if ($oldValue === $value) {
             return ['success' => true, 'message' => $this->i18n->t('api.field_updated')];
        }

        try {
            $update = $this->pdo->prepare("UPDATE users SET $field = ? WHERE id = ?");
            $update->execute([$value, $this->userId]);
            $this->logProfileChange($field, $oldValue, $value);
            $_SESSION[$field] = $value;
            
            if ($field === 'email') {
                unset($_SESSION['email_change_auth']);
            }

            return ['success' => true, 'message' => $this->i18n->t('api.field_updated')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.update_error') . ': ' . $e->getMessage()];
        }
    }

    public function validateCurrentPassword($currentPass) {
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');
        
        if ($this->checkSecurityLimit('password_verify_fail', $limit, $duration)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration])];
        }
        if (empty($currentPass)) return ['success' => false, 'message' => $this->i18n->t('api.pass_current_req')];
        
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($currentPass, $user['password'])) {
            return ['success' => true, 'message' => $this->i18n->t('api.pass_correct')];
        }
        
        $this->logSecurityAction('password_verify_fail', $duration);
        return ['success' => false, 'message' => $this->i18n->t('api.pass_incorrect')];
    }

    public function changePassword($currentPass, $newPass) {
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');
        
        if ($this->checkSecurityLimit('password_verify_fail', $limit, $duration)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration])];
        }
        if (empty($currentPass) || empty($newPass)) return ['success' => false, 'message' => $this->i18n->t('api.missing_data')];
        
        $minPassLen = (int)Utils::getServerConfig($this->pdo, 'password_min_length', '6');
        if (strlen($newPass) < $minPassLen) {
             return ['success' => false, 'message' => $this->i18n->t('api.pass_short', [$minPassLen])];
        }
        
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPass, $user['password'])) {
            $this->logSecurityAction('password_verify_fail', $duration);
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
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_general_rate_limit', '10');
        
        if ($this->checkSecurityLimit('pref_update', $limit, 1)) {
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

            $this->logSecurityAction('pref_update', 1); 

            return ['success' => true, 'message' => $this->i18n->t('api.pref_saved')];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_save_error') . ': ' . $e->getMessage()];
        }
    }

    public function init2fa() {
        if ($this->checkSecurityLimit('2fa_init_attempt', 10, 60)) {
            return ['success' => false, 'message' => $this->i18n->t('api.pref_rate_limit')];
        }

        $g = new GoogleAuthenticator();
        $secret = $g->generateSecret();
        
        $_SESSION['temp_2fa_secret'] = $secret;
        $username = $_SESSION['username'] ?? 'User';
        
        $issuer = 'ProjectAurora';
        $encodedIssuer = rawurlencode($issuer);
        $encodedUser = rawurlencode($username);

        $otpauthUrl = "otpauth://totp/{$encodedIssuer}:{$encodedUser}?secret={$secret}&issuer={$encodedIssuer}";

        $this->logSecurityAction('2fa_init_attempt', 60);
        
        return ['success' => true, 'message' => $this->i18n->t('api.qr_scan'), 'otpauth_url' => $otpauthUrl, 'secret' => $secret];
    }

    public function enable2fa($code) {
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');

        if ($this->checkSecurityLimit('2fa_verify_attempt', $limit, $duration)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration])];
        }
        if (!isset($_SESSION['temp_2fa_secret'])) return ['success' => false, 'message' => $this->i18n->t('api.session_config_expired')];
        $secret = $_SESSION['temp_2fa_secret'];

        $g = new GoogleAuthenticator();
        if ($g->checkCode($secret, $code)) {
            $recoveryCodes = []; 
            $hashedCodes = [];   
            for($i=0; $i<10; $i++) {
                $plainCode = bin2hex(random_bytes(4)); 
                $recoveryCodes[] = $plainCode;
                $hashedCodes[] = password_hash($plainCode, PASSWORD_DEFAULT);
            }
            $jsonCodes = json_encode($hashedCodes);
            $stmt = $this->pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_recovery_codes = ? WHERE id = ?");
            if ($stmt->execute([$secret, $jsonCodes, $this->userId])) {
                unset($_SESSION['temp_2fa_secret']);
                $_SESSION['two_factor_enabled'] = 1;
                $this->logProfileChange('2fa', 'disabled', 'enabled');
                return ['success' => true, 'message' => $this->i18n->t('api.2fa_enabled'), 'recovery_codes' => $recoveryCodes];
            }
            return ['success' => false, 'message' => $this->i18n->t('api.pic_db_error')];
        }
        
        $this->logSecurityAction('2fa_verify_attempt', $duration);
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

    public function getRecoveryStatus() {
        $stmt = $this->pdo->prepare("SELECT two_factor_recovery_codes FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $codesJson = $stmt->fetchColumn();
        $count = 0;
        if ($codesJson) {
            $codes = json_decode($codesJson, true);
            if (is_array($codes)) {
                $count = count($codes);
            }
        }
        return ['success' => true, 'message' => $this->i18n->t('api.recovery_status'), 'count' => $count];
    }

    public function regenerateRecoveryCodes($password) {
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');

        if ($this->checkSecurityLimit('2fa_regen_codes', 3, $duration)) {
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration])];
        }
        if (empty($password)) return ['success' => false, 'message' => $this->i18n->t('api.pass_req_confirm')];
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            $this->logSecurityAction('2fa_regen_codes_fail', $duration);
            return ['success' => false, 'message' => $this->i18n->t('api.pass_incorrect')];
        }
        $recoveryCodes = []; 
        $hashedCodes = []; 
        for($i=0; $i<10; $i++) {
            $plainCode = bin2hex(random_bytes(4)); 
            $recoveryCodes[] = $plainCode;
            $hashedCodes[] = password_hash($plainCode, PASSWORD_DEFAULT);
        }
        $jsonCodes = json_encode($hashedCodes);
        $update = $this->pdo->prepare("UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?");
        if ($update->execute([$jsonCodes, $this->userId])) {
            $this->logSecurityAction('2fa_regen_codes', $duration);
            return ['success' => true, 'message' => $this->i18n->t('api.recovery_regenerated'), 'recovery_codes' => $recoveryCodes];
        } else {
             return ['success' => false, 'message' => $this->i18n->t('api.db_error')];
        }
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
        
        if ($stmt->rowCount() > 0) {
            $this->redis->publish('aurora_ws_control', json_encode([
                'cmd' => 'KICK_SESSION',
                'user_id' => $this->userId,
                'session_id' => $tokenId
            ]));
            
            return ['success' => true, 'message' => $this->i18n->t('api.session_revoked')];
        }
        return ['success' => false, 'message' => $this->i18n->t('api.session_revoke_error')];
    }

    public function revokeAllSessions() {
        $stmt = $this->pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        
        $this->redis->publish('aurora_ws_control', json_encode([
            'cmd' => 'KICK_ALL',
            'user_id' => $this->userId
        ]));

        return ['success' => true, 'message' => $this->i18n->t('api.all_sessions_revoked'), 'logout' => true];
    }

    public function deleteAccount($password) {
        $limit = (int)Utils::getServerConfig($this->pdo, 'security_login_max_attempts', '5');
        $duration = (int)Utils::getServerConfig($this->pdo, 'security_block_duration', '15');

        if ($this->checkSecurityLimit('account_delete_attempt', $limit, $duration * 2)) { 
            return ['success' => false, 'message' => $this->i18n->t('api.login_block', [$duration * 2])];
        }
        if (empty($password)) return ['success' => false, 'message' => $this->i18n->t('api.pass_req_confirm')];
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            $this->logSecurityAction('account_delete_attempt', $duration * 2);
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
            
            $this->redis->publish('aurora_ws_control', json_encode([
                'cmd' => 'KICK_ALL',
                'user_id' => $this->userId
            ]));

            setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
            session_destroy();
            return ['success' => true, 'message' => $this->i18n->t('api.account_deleted_success')];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $this->i18n->t('api.internal_error')];
        }
    }

    private function checkRateLimitExceeded($changeType, $hours, $limit) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM profile_changes WHERE user_id = ? AND change_type = ? AND created_at > (NOW() - INTERVAL $hours HOUR)");
        $stmt->execute([$this->userId, $changeType]);
        return ($stmt->fetchColumn() >= $limit);
    }
    
    private function incrementRedisCounter($key, $minutes) {
        if (!$this->redis) return;
        try {
            $current = $this->redis->incr($key);
            if ($current === 1) {
                $this->redis->expire($key, $minutes * 60);
            }
        } catch (Exception $e) {
            error_log("Redis RateLimit Error (INCR): " . $e->getMessage());
        }
    }

    public function checkSecurityLimit($actionType, $limit, $minutes) {
        if ($this->redis) {
            $ip = $this->getClientIp();
            $ipKey = "rate_limit:{$actionType}:ip:{$ip}";
            $userKey = "rate_limit:{$actionType}:user:{$this->userId}";

            try {
                $ipCount = $this->redis->get($ipKey);
                if ($ipCount && (int)$ipCount >= $limit) return true;

                $userCount = $this->redis->get($userKey);
                if ($userCount && (int)$userCount >= $limit) return true;
                
                return false;
            } catch (Exception $e) {
            }
        }

        $ip = $this->getClientIp();
        $sql = "SELECT COUNT(*) FROM security_logs WHERE (user_identifier = ? OR ip_address = ?) AND action_type = ? AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId, $ip, $actionType]); 
        return ($stmt->fetchColumn() >= $limit);
    }
    
    private function logSecurityAction($actionType, $minutes = 15) {
        $ip = $this->getClientIp();
        
        try {
            $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->userId, $actionType, $ip]);
        } catch (Exception $e) { /* Log error */ }

        $ipKey = "rate_limit:{$actionType}:ip:{$ip}";
        $userKey = "rate_limit:{$actionType}:user:{$this->userId}";

        $this->incrementRedisCounter($ipKey, $minutes);
        $this->incrementRedisCounter($userKey, $minutes);
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
        try { $stmt->execute([$this->userId, $changeType, $oldValue, $newValue, $ip, $ua]); } catch (Exception $e) { error_log("Error logging profile change: " . $e->getMessage()); }
    }
    
    private function getClientIp() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
?>