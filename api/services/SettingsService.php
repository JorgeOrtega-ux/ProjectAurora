<?php
// api/services/SettingsService.php
namespace App\Api\Services;

use PDO;
use App\Core\Utils;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use RobThree\Auth\TwoFactorAuth;

class SettingsService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ==========================================
    // MÉTODO DE LOG EXTREMO EN ARCHIVO FÍSICO
    // ==========================================
    private function forceDebugLog($message) {
        // Esto creará un archivo llamado "debug_2fa_log.txt" en la carpeta "api/"
        $logFile = __DIR__ . '/../debug_2fa_log.txt'; 
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] " . print_r($message, true) . PHP_EOL;
        @file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    private function logChange($userId, $field, $oldValue, $newValue) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO user_changes_log (user_id, modified_field, old_value, new_value) VALUES (:user_id, :field, :old_val, :new_val)");
            $stmt->execute([
                ':user_id' => $userId,
                ':field'   => $field,
                ':old_val' => $oldValue,
                ':new_val' => $newValue
            ]);
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en logChange: " . $e->getMessage());
        }
    }

    private function countRecentChanges($userId, $field, $interval, $onlyUploads = false) {
        try {
            $query = "SELECT COUNT(*) FROM user_changes_log WHERE user_id = :uid AND modified_field = :field AND changed_at >= DATE_SUB(NOW(), INTERVAL $interval)";
            if ($onlyUploads) {
                $query .= " AND new_value LIKE '%uploaded/%'";
            }
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':uid' => $userId, ':field' => $field]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en countRecentChanges: " . $e->getMessage());
            return 0;
        }
    }

    // ==========================================
    // SISTEMA ANTI-SPAM (RATE LIMITING)
    // ==========================================

    private function getUserIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { return $_SERVER['HTTP_CLIENT_IP']; } 
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { return $_SERVER['HTTP_X_FORWARDED_FOR']; } 
        else { return $_SERVER['REMOTE_ADDR']; }
    }

    private function checkRateLimit($action) {
        try {
            $ip = $this->getUserIP();
            $query = "SELECT attempts, blocked_until, last_attempt, (blocked_until > NOW()) as is_blocked FROM rate_limits WHERE ip_address = :ip AND action = :action LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':ip' => $ip, ':action' => $action]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                if ($result['is_blocked']) { return false; }
                if ($result['blocked_until'] !== null || strtotime($result['last_attempt']) < strtotime('-5 minutes')) {
                    $this->resetAttempts($action);
                }
            }
            return true;
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en checkRateLimit: " . $e->getMessage());
            return true;
        }
    }

    private function recordActionAttempt($action, $maxAttempts = 15, $blockMinutes = 5) {
        try {
            $ip = $this->getUserIP();
            $query = "SELECT attempts FROM rate_limits WHERE ip_address = :ip AND action = :action LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':ip' => $ip, ':action' => $action]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $attempts = $result['attempts'] + 1;
                $blocked_until = ($attempts >= $maxAttempts) ? date('Y-m-d H:i:s', strtotime("+$blockMinutes minutes")) : null;
                $update = "UPDATE rate_limits SET attempts = :attempts, last_attempt = NOW(), blocked_until = :blocked_until WHERE ip_address = :ip AND action = :action";
                $updStmt = $this->conn->prepare($update);
                $updStmt->execute([':attempts' => $attempts, ':blocked_until' => $blocked_until, ':ip' => $ip, ':action' => $action]);
            } else {
                $insert = "INSERT INTO rate_limits (ip_address, action, attempts, last_attempt) VALUES (:ip, :action, 1, NOW())";
                $insStmt = $this->conn->prepare($insert);
                $insStmt->execute([':ip' => $ip, ':action' => $action]);
            }
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en recordActionAttempt: " . $e->getMessage());
        }
    }

    private function resetAttempts($action) {
        try {
            $ip = $this->getUserIP();
            $query = "DELETE FROM rate_limits WHERE ip_address = :ip AND action = :action";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':ip' => $ip, ':action' => $action]);
        } catch (\Throwable $e) {}
    }
    
    // ==========================================
    // SISTEMA DE CORREO SMTP
    // ==========================================
    private function sendEmail($to, $subject, $bodyHtml) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USER');
            $mail->Password   = getenv('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = getenv('SMTP_PORT') ?: 465;
            $mail->setFrom(getenv('SMTP_USER'), 'Project Aurora');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = mb_encode_mimeheader($subject, "UTF-8");
            $mail->Body    = $bodyHtml;
            $mail->CharSet = 'UTF-8';
            $mail->send();
            return true;
        } catch (Exception $e) {
            $this->forceDebugLog("Error enviando email SMTP: " . $e->getMessage());
            return false;
        }
    }

    // ==========================================
    // AVATAR
    // ==========================================

    public function uploadAvatar($userId, $file) {
        if ($this->countRecentChanges($userId, 'avatar', '1 DAY', true) >= 3) {
            return ['success' => false, 'message' => 'js.profile.err_limit_avatar'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) { return ['success' => false, 'message' => 'Error al subir la imagen. Código: ' . $file['error']]; }
        if ($file['size'] > 2 * 1024 * 1024) { return ['success' => false, 'message' => 'La imagen supera el peso máximo de 2MB.']; }

        $mime = mime_content_type($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes)) { return ['success' => false, 'message' => 'El formato de imagen no es válido. Solo JPG, PNG, WEBP o GIF.']; }

        $image = null;
        switch ($mime) {
            case 'image/jpeg': $image = @imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $image = @imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $image = @imagecreatefromwebp($file['tmp_name']); break;
            case 'image/gif':  $image = @imagecreatefromgif($file['tmp_name']); break;
        }
        if (!$image) { return ['success' => false, 'message' => 'El archivo de imagen está corrupto o tiene contenido malicioso.']; }

        $storageDir = __DIR__ . '/../../public/storage/profilePictures/uploaded/';
        if (!file_exists($storageDir)) { mkdir($storageDir, 0777, true); }

        $filename = Utils::generateUUID() . '_' . time() . '.jpg';
        $filepath = $storageDir . $filename;
        $webPath = 'storage/profilePictures/uploaded/' . $filename;

        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'])) {
            $width = imagesx($image);
            $height = imagesy($image);
            $bg = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopyresampled($bg, $image, 0, 0, 0, 0, $width, $height, $width, $height);
            imagedestroy($image);
            $image = $bg;
        }

        imagejpeg($image, $filepath, 85);
        imagedestroy($image);

        $stmt = $this->conn->prepare("SELECT avatar_path FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $oldAvatar = $stmt->fetchColumn();

        if ($oldAvatar && strpos($oldAvatar, 'uploaded/') !== false) {
            $oldFile = __DIR__ . '/../../public/' . $oldAvatar;
            if (file_exists($oldFile)) { @unlink($oldFile); }
        }

        $updStmt = $this->conn->prepare("UPDATE users SET avatar_path = :path WHERE id = :id");
        if ($updStmt->execute([':path' => $webPath, ':id' => $userId])) {
            $this->logChange($userId, 'avatar', $oldAvatar, $webPath);
            $_SESSION['user_avatar'] = $webPath;
            return ['success' => true, 'message' => 'Foto de perfil actualizada correctamente.', 'avatar' => $webPath];
        }
        return ['success' => false, 'message' => 'Error interno al actualizar la base de datos.'];
    }

    public function deleteAvatar($userId) {
        $stmt = $this->conn->prepare("SELECT uuid, nombre, avatar_path FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) { return ['success' => false, 'message' => 'Usuario no encontrado.']; }

        $oldAvatar = $user['avatar_path'];
        if (strpos($oldAvatar, 'uploaded/') !== false) {
            $oldFile = __DIR__ . '/../../public/' . $oldAvatar;
            if (file_exists($oldFile)) { @unlink($oldFile); }
        }

        $storageDir = __DIR__ . '/../../public/storage/profilePictures/default/';
        $webDir = 'storage/profilePictures/default/';
        $newWebPath = Utils::generateAndSaveAvatar($user['nombre'], $user['uuid'], $storageDir, $webDir);

        if (!$newWebPath) { return ['success' => false, 'message' => 'No se pudo generar el avatar por defecto.']; }

        $updStmt = $this->conn->prepare("UPDATE users SET avatar_path = :path WHERE id = :id");
        if ($updStmt->execute([':path' => $newWebPath, ':id' => $userId])) {
            $this->logChange($userId, 'avatar', $oldAvatar, $newWebPath);
            $_SESSION['user_avatar'] = $newWebPath;
            return ['success' => true, 'message' => 'Foto de perfil eliminada.', 'avatar' => $newWebPath];
        }
        return ['success' => false, 'message' => 'Error al restaurar el avatar.'];
    }

    public function updateField($userId, $field, $newValue) {
        $allowedFields = ['nombre', 'correo'];
        if (!in_array($field, $allowedFields)) { return ['success' => false, 'message' => 'Campo no válido.']; }

        $newValue = trim($newValue);
        if (empty($newValue)) { return ['success' => false, 'message' => 'El valor no puede estar vacío.']; }

        if ($field === 'correo') {
            if (!filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Formato de correo inválido.'];
            }
            $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE correo = :correo AND id != :id");
            $checkStmt->execute([':correo' => $newValue, ':id' => $userId]);
            if ($checkStmt->rowCount() > 0) { return ['success' => false, 'message' => 'El correo ya está en uso.']; }
        }

        $stmt = $this->conn->prepare("SELECT $field FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $oldValue = $stmt->fetchColumn();

        if ($oldValue === $newValue) { return ['success' => true, 'message' => 'No hubo cambios.', 'newValue' => $newValue]; }

        if ($field === 'nombre') {
            if ($this->countRecentChanges($userId, 'nombre', '7 DAY') >= 1) { return ['success' => false, 'message' => 'js.profile.err_limit_username']; }
        } else if ($field === 'correo') {
            if ($this->countRecentChanges($userId, 'correo', '7 DAY') >= 1) { return ['success' => false, 'message' => 'js.profile.err_limit_email']; }
        }

        $updStmt = $this->conn->prepare("UPDATE users SET $field = :new_val WHERE id = :id");
        if ($updStmt->execute([':new_val' => $newValue, ':id' => $userId])) {
            $this->logChange($userId, $field, $oldValue, $newValue);
            if ($field === 'nombre') { $_SESSION['user_name'] = $newValue; }
            else if ($field === 'correo') { $_SESSION['user_email'] = $newValue; }
            return ['success' => true, 'message' => 'Actualizado correctamente.', 'newValue' => $newValue];
        }
        return ['success' => false, 'message' => 'Error al actualizar el campo.'];
    }

    public function requestEmailChange($userId, $newEmail) {
        if (!$this->checkRateLimit('request_email_change')) { return ['success' => false, 'message' => 'Demasiados intentos. Por favor, espera 5 minutos.']; }

        $newEmail = trim($newEmail);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->recordActionAttempt('request_email_change', 5, 5);
            return ['success' => false, 'message' => 'Formato de correo inválido.'];
        }

        $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE correo = :correo");
        $checkStmt->execute([':correo' => $newEmail]);
        if ($checkStmt->rowCount() > 0) {
            $this->recordActionAttempt('request_email_change', 5, 5);
            return ['success' => false, 'message' => 'El correo ya está en uso.'];
        }

        if ($this->countRecentChanges($userId, 'correo', '7 DAY') >= 1) { return ['success' => false, 'message' => 'js.profile.err_limit_email']; }

        $userStmt = $this->conn->prepare("SELECT correo, nombre FROM users WHERE id = :id");
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $currentEmail = $user['correo'];
        $userName = $user['nombre'];

        if ($currentEmail === $newEmail) { return ['success' => false, 'message' => 'El nuevo correo no puede ser el actual.']; }

        $code = sprintf("%06d", mt_rand(1, 999999));
        $payload = json_encode(['new_email' => $newEmail]);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $delQuery = "DELETE FROM verification_codes WHERE identifier = :identifier AND code_type = 'email_change'";
        $delStmt = $this->conn->prepare($delQuery);
        $delStmt->execute([':identifier' => $userId]);

        $query = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (:identifier, 'email_change', :code, :payload, :expires_at)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':identifier' => $userId, ':code' => $code, ':payload' => $payload, ':expires_at' => $expires_at]);

        $html = "<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>Cambio de Correo Electrónico</h2><p>Hola {$userName},</p>
                    <p>Se ha solicitado un cambio en tu cuenta para actualizar el correo a: <b>{$newEmail}</b>.</p>
                    <p>Para autorizar el cambio, ingresa el siguiente código de seguridad:</p>
                    <h1 style='letter-spacing: 4px; background: #f5f5fa; padding: 12px; text-align: center; border-radius: 8px;'>{$code}</h1>
                    <p>Este código expirará en 15 minutos.</p></div>";

        if ($this->sendEmail($currentEmail, 'Código de Verificación - Cambio de Correo', $html)) {
            $this->resetAttempts('request_email_change');
            return ['success' => true, 'message' => 'Código de verificación enviado a tu correo actual.'];
        }
        return ['success' => false, 'message' => 'Error de servidor al intentar enviar el correo.'];
    }

    public function confirmEmailChange($userId, $code) {
        $query = "SELECT id, payload FROM verification_codes WHERE identifier = :identifier AND code = :code AND code_type = 'email_change' AND expires_at > NOW() LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':identifier' => $userId, ':code' => $code]);

        if ($stmt->rowCount() === 0) { return ['success' => false, 'message' => 'El código de verificación es inválido o expiró.']; }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $payload = json_decode($row['payload'], true);
        $newEmail = $payload['new_email'];
        $codeId = $row['id'];

        $userStmt = $this->conn->prepare("SELECT correo FROM users WHERE id = :id");
        $userStmt->execute([':id' => $userId]);
        $oldEmail = $userStmt->fetchColumn();

        if ($this->countRecentChanges($userId, 'correo', '7 DAY') >= 1) { return ['success' => false, 'message' => 'js.profile.err_limit_email']; }

        $updStmt = $this->conn->prepare("UPDATE users SET correo = :new_val WHERE id = :id");
        if ($updStmt->execute([':new_val' => $newEmail, ':id' => $userId])) {
            $this->logChange($userId, 'correo', $oldEmail, $newEmail);
            $_SESSION['user_email'] = $newEmail;
            
            $delStmt = $this->conn->prepare("DELETE FROM verification_codes WHERE id = :id");
            $delStmt->execute([':id' => $codeId]);

            return ['success' => true, 'message' => 'Correo actualizado correctamente.', 'newValue' => $newEmail];
        }
        return ['success' => false, 'message' => 'Error crítico al actualizar el correo.'];
    }

    public function getPreferences($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT language, open_links_new_tab, theme, extended_alerts FROM user_preferences WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prefs) { return ['language' => 'en-us', 'open_links_new_tab' => 1, 'theme' => 'system', 'extended_alerts' => 0]; }
            return $prefs;
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en getPreferences: " . $e->getMessage());
            return ['language' => 'en-us', 'open_links_new_tab' => 1, 'theme' => 'system', 'extended_alerts' => 0];
        }
    }

    public function verifyPassword($userId, $password) {
        if (!$this->checkRateLimit('verify_password')) { return ['success' => false, 'message' => 'Demasiados intentos. Por favor, espera 5 minutos.']; }
        $stmt = $this->conn->prepare("SELECT contrasena FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if (password_verify($password, $hash)) {
            $this->resetAttempts('verify_password');
            return ['success' => true];
        }
        $this->recordActionAttempt('verify_password', 5, 5);
        return ['success' => false, 'message' => 'La contraseña actual es incorrecta.'];
    }

    public function updatePassword($userId, $currentPassword, $newPassword) {
        if (!$this->checkRateLimit('update_password')) { return ['success' => false, 'message' => 'Demasiados intentos. Por favor, espera 5 minutos.']; }

        $stmt = $this->conn->prepare("SELECT contrasena FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            $this->recordActionAttempt('update_password', 5, 5);
            return ['success' => false, 'message' => 'La contraseña actual es incorrecta.'];
        }

        global $APP_CONFIG;
        $min = (int)($APP_CONFIG['min_password_length'] ?? 12);
        $max = (int)($APP_CONFIG['max_password_length'] ?? 64);
        
        if (strlen($newPassword) < $min || strlen($newPassword) > $max) { return ['success' => false, 'message' => 'js.auth.err_pass_length']; }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $updStmt = $this->conn->prepare("UPDATE users SET contrasena = :new_hash WHERE id = :id");
        if ($updStmt->execute([':new_hash' => $newHash, ':id' => $userId])) {
            $this->logChange($userId, 'contrasena', $hash, $newHash);
            $this->resetAttempts('update_password');
            return ['success' => true, 'message' => 'Contraseña actualizada correctamente.'];
        }
        return ['success' => false, 'message' => 'Error al actualizar la contraseña en la base de datos.'];
    }

    public function updatePreference($userId, $field, $value) {
        try {
            if (!$this->checkRateLimit('update_preference')) { return ['success' => false, 'message' => 'js.profile.err_limit_prefs']; }
            $this->recordActionAttempt('update_preference', 15, 5);

            $allowedFields = ['language', 'open_links_new_tab', 'theme', 'extended_alerts'];
            if (!in_array($field, $allowedFields)) { return ['success' => false, 'message' => 'Campo no válido.']; }

            if ($field === 'language') {
                $allowedLangs = ['en-us', 'en-gb', 'fr-fr', 'de-de', 'it-it', 'es-latam', 'es-mx', 'es-es', 'pt-br', 'pt-pt'];
                if (!in_array($value, $allowedLangs)) { $value = 'en-us'; }
                setcookie('aurora_lang', $value, time() + 31536000, '/');
            }

            if ($field === 'open_links_new_tab') { $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0; }
            if ($field === 'theme') {
                $allowedThemes = ['system', 'light', 'dark'];
                if (!in_array($value, $allowedThemes)) { $value = 'system'; }
            }
            if ($field === 'extended_alerts') { $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0; }

            $stmt = $this->conn->prepare("INSERT INTO user_preferences (user_id, $field) VALUES (:id, :val) ON DUPLICATE KEY UPDATE $field = :val2");
            if ($stmt->execute([':id' => $userId, ':val' => $value, ':val2' => $value])) { return ['success' => true, 'message' => 'Guardado.']; }
            return ['success' => false, 'message' => 'Error de base de datos.'];
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en updatePreference: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno al actualizar preferencia.'];
        }
    }

    // ==========================================
    // MÉTODOS DE 2FA (CON DEBUGGING Y BASE 64 PARA CSP)
    // ==========================================

    public function init2FA($userId) {
        // Vaciamos el log si ya existía para tener una lectura limpia de este intento
        @unlink(__DIR__ . '/../debug_2fa_log.txt');
        $this->forceDebugLog("INICIANDO PROCESO DE 2FA PARA USUARIO ID: " . $userId);

        try {
            $this->forceDebugLog("Paso 1: Buscando usuario en la base de datos...");
            $stmt = $this->conn->prepare("SELECT correo, two_factor_enabled, two_factor_secret, two_factor_recovery_codes FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->forceDebugLog("Paso 1 FALSO: Usuario no encontrado.");
                return ['success' => false, 'message' => 'Usuario no encontrado.'];
            }
            $this->forceDebugLog("Paso 1 EXITOSO: Usuario encontrado: " . $user['correo']);

            if ($user['two_factor_enabled']) {
                $this->forceDebugLog("Aviso: El usuario ya tiene 2FA habilitado. Retornando códigos de recuperación.");
                $codes = json_decode($user['two_factor_recovery_codes'], true) ?? [];
                return ['success' => true, 'enabled' => true, 'codes_count' => count($codes)];
            }

            $this->forceDebugLog("Paso 2: Comprobando existencia de clase RobThree\\Auth\\TwoFactorAuth...");
            if (!class_exists('\RobThree\Auth\TwoFactorAuth')) {
                $this->forceDebugLog("¡ERROR CRÍTICO! La clase RobThree\Auth\TwoFactorAuth no existe. Composer no está configurado correctamente.");
                throw new \Exception("Clase RobThree no encontrada.");
            }
            $this->forceDebugLog("Paso 2 EXITOSO: Clase encontrada.");

            $this->forceDebugLog("Paso 3: Instanciando TwoFactorAuth...");
            $tfa = new \RobThree\Auth\TwoFactorAuth('Project Aurora');
            $secret = $user['two_factor_secret'];
            
            if (empty($secret)) {
                $this->forceDebugLog("Paso 3.1: Generando nuevo secreto porque estaba vacío...");
                $secret = $tfa->createSecret();
                $upd = $this->conn->prepare("UPDATE users SET two_factor_secret = :sec WHERE id = :id");
                $upd->execute([':sec' => $secret, ':id' => $userId]);
                $this->forceDebugLog("Paso 3.1 EXITOSO: Secreto generado y guardado.");
            } else {
                $this->forceDebugLog("Paso 3 EXITOSO: Secreto ya existía.");
            }

            $this->forceDebugLog("Paso 4: Generando QR usando PHP directamente en DataURI (Base64) para evadir el Content Security Policy...");
            $qrUrl = $tfa->getQRCodeImageAsDataUri('Project Aurora (' . $user['correo'] . ')', $secret);
            $this->forceDebugLog("Paso 4 EXITOSO: QR generado en formato Base64.");

            $this->forceDebugLog("PROCESO TERMINADO CON ÉXITO. Retornando JSON al cliente.");
            return [
                'success' => true,
                'enabled' => false,
                'secret' => $secret,
                'qr' => $qrUrl
            ];

        } catch (\Throwable $e) {
            $this->forceDebugLog("!!! ERROR CATASTRÓFICO DETECTADO !!!");
            $this->forceDebugLog("Mensaje: " . $e->getMessage());
            $this->forceDebugLog("Archivo: " . $e->getFile());
            $this->forceDebugLog("Línea: " . $e->getLine());
            $this->forceDebugLog("Stack Trace: " . $e->getTraceAsString());
            
            return ['success' => false, 'message' => 'Error interno grave. Revisa el log de debug en el servidor.'];
        }
    }
    
    public function enable2FA($userId, $code) {
        try {
            $stmt = $this->conn->prepare("SELECT two_factor_secret FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $secret = $stmt->fetchColumn();

            $tfa = new \RobThree\Auth\TwoFactorAuth('Project Aurora');
            if ($tfa->verifyCode($secret, $code)) {
                $recoveryCodes = [];
                for ($i = 0; $i < 10; $i++) { $recoveryCodes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)); }
                $upd = $this->conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_recovery_codes = :codes WHERE id = :id");
                $upd->execute([':codes' => json_encode($recoveryCodes), ':id' => $userId]);
                return ['success' => true, 'message' => '2FA activado correctamente', 'recovery_codes' => $recoveryCodes];
            }
            return ['success' => false, 'message' => 'Código de verificación incorrecto.'];
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en enable2FA: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno.'];
        }
    }

    public function disable2FA($userId, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT contrasena FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($password, $hash)) { return ['success' => false, 'message' => 'Contraseña incorrecta.']; }
            
            $upd = $this->conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_recovery_codes = NULL WHERE id = :id");
            $upd->execute([':id' => $userId]);
            return ['success' => true, 'message' => '2FA desactivado.'];
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en disable2FA: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno.'];
        }
    }

    public function regenerate2FACodes($userId, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT contrasena FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($password, $hash)) { return ['success' => false, 'message' => 'Contraseña incorrecta.']; }

            $recoveryCodes = [];
            for ($i = 0; $i < 10; $i++) { $recoveryCodes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)); }
            
            $upd = $this->conn->prepare("UPDATE users SET two_factor_recovery_codes = :codes WHERE id = :id");
            $upd->execute([':codes' => json_encode($recoveryCodes), ':id' => $userId]);
            return ['success' => true, 'message' => 'Códigos generados correctamente.', 'recovery_codes' => $recoveryCodes];
        } catch (\Throwable $e) {
            $this->forceDebugLog("Error en regenerate2FACodes: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno.'];
        }
    }
}
?>