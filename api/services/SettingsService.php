<?php
// api/services/SettingsService.php
namespace App\Api\Services;

use PDO;
use App\Core\Utils;
use App\Core\Logger;
use RobThree\Auth\TwoFactorAuth;

class SettingsService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
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
            Logger::database("Error al registrar cambio en user_changes_log (Field: $field)", Logger::LEVEL_ERROR, $e);
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
            Logger::database("Error al contar cambios recientes (Field: $field)", Logger::LEVEL_ERROR, $e);
            return 0;
        }
    }

    // ==========================================
    // AVATAR
    // ==========================================

    public function uploadAvatar($userId, $file) {
        if ($this->countRecentChanges($userId, 'avatar', '1 DAY', true) >= 3) {
            return ['success' => false, 'message' => 'js.profile.err_limit_avatar'];
        }
        
        $storageDir = __DIR__ . '/../../public/storage/profilePictures/uploaded/';
        $webDir = 'storage/profilePictures/uploaded/';

        // Delegamos todo el procesamiento (validación, GD, guardado) a Utils
        $processResult = Utils::processAndSaveImage($file, $storageDir, $webDir);

        if (!$processResult['success']) {
            return $processResult;
        }

        $webPath = $processResult['webPath'];

        try {
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
                Logger::system("Avatar actualizado para el usuario ID: $userId", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Foto de perfil actualizada correctamente.', 'avatar' => $webPath];
            }
        } catch (\Throwable $e) {
            Logger::database("Error al actualizar la base de datos durante la subida de avatar", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al actualizar la base de datos.'];
        }
        return ['success' => false, 'message' => 'Error interno desconocido.'];
    }

    public function deleteAvatar($userId) {
        try {
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

            if (!$newWebPath) { 
                Logger::system("No se pudo generar el avatar por defecto desde UI-Avatars para el usuario ID: $userId", Logger::LEVEL_WARNING);
                return ['success' => false, 'message' => 'No se pudo generar el avatar por defecto.']; 
            }

            $updStmt = $this->conn->prepare("UPDATE users SET avatar_path = :path WHERE id = :id");
            if ($updStmt->execute([':path' => $newWebPath, ':id' => $userId])) {
                $this->logChange($userId, 'avatar', $oldAvatar, $newWebPath);
                $_SESSION['user_avatar'] = $newWebPath;
                Logger::system("Avatar restaurado por defecto para usuario ID: $userId", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Foto de perfil eliminada.', 'avatar' => $newWebPath];
            }
            return ['success' => false, 'message' => 'Error al restaurar el avatar.'];
        } catch (\Throwable $e) {
            Logger::database("Fallo al eliminar/restaurar avatar", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al procesar la solicitud.'];
        }
    }

    public function updateField($userId, $field, $newValue) {
        $allowedFields = ['nombre', 'correo'];
        if (!in_array($field, $allowedFields)) { return ['success' => false, 'message' => 'Campo no válido.']; }

        $newValue = trim($newValue);
        if (empty($newValue)) { return ['success' => false, 'message' => 'El valor no puede estar vacío.']; }

        try {
            if ($field === 'correo') {
                if (!Utils::validateEmail($newValue)) {
                    return ['success' => false, 'message' => 'Formato de correo inválido o dominio no permitido.'];
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
                if (!Utils::validateUsername($newValue)) { return ['success' => false, 'message' => 'js.auth.err_user_length']; }
            } else if ($field === 'correo') {
                if ($this->countRecentChanges($userId, 'correo', '7 DAY') >= 1) { return ['success' => false, 'message' => 'js.profile.err_limit_email']; }
            }

            $updStmt = $this->conn->prepare("UPDATE users SET $field = :new_val WHERE id = :id");
            if ($updStmt->execute([':new_val' => $newValue, ':id' => $userId])) {
                $this->logChange($userId, $field, $oldValue, $newValue);
                if ($field === 'nombre') { $_SESSION['user_name'] = $newValue; }
                else if ($field === 'correo') { $_SESSION['user_email'] = $newValue; }
                
                Logger::system("Usuario ID: $userId actualizó exitosamente el campo '$field'", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Actualizado correctamente.', 'newValue' => $newValue];
            }
            return ['success' => false, 'message' => 'Error al actualizar el campo.'];
        } catch (\Throwable $e) {
            Logger::database("Error al intentar actualizar campo $field", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error en la base de datos al actualizar el campo.'];
        }
    }

    public function requestEmailChange($userId, $newEmail) {
        if (!Utils::checkRateLimit($this->conn, 'request_email_change', 5)) { 
            return ['success' => false, 'message' => 'Demasiados intentos. Por favor, espera 5 minutos.']; 
        }

        $newEmail = trim($newEmail);
        if (!Utils::validateEmail($newEmail)) {
            Utils::recordActionAttempt($this->conn, 'request_email_change', 5, 5);
            return ['success' => false, 'message' => 'Formato de correo inválido o dominio no permitido.'];
        }

        try {
            $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE correo = :correo");
            $checkStmt->execute([':correo' => $newEmail]);
            if ($checkStmt->rowCount() > 0) {
                Utils::recordActionAttempt($this->conn, 'request_email_change', 5, 5);
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

            if (Utils::sendEmail($currentEmail, 'Código de Verificación - Cambio de Correo', $html)) {
                Utils::resetAttempts($this->conn, 'request_email_change');
                Logger::system("Código de cambio de correo enviado al usuario ID: $userId", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Código de verificación enviado a tu correo actual.'];
            }
            return ['success' => false, 'message' => 'Error de servidor al intentar enviar el correo.'];
        } catch (\Throwable $e) {
            Logger::database("Error en solicitud de cambio de email", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Fallo interno al procesar la solicitud.'];
        }
    }

    public function confirmEmailChange($userId, $code) {
        try {
            $query = "SELECT id, payload FROM verification_codes WHERE identifier = :identifier AND code = :code AND code_type = 'email_change' AND expires_at > NOW() LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':identifier' => $userId, ':code' => $code]);

            if ($stmt->rowCount() === 0) { 
                Logger::system("Intento fallido de confirmación de correo (Código inválido/expirado) para User ID: $userId", Logger::LEVEL_WARNING);
                return ['success' => false, 'message' => 'El código de verificación es inválido o expiró.']; 
            }

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

                Logger::system("El usuario ID: $userId actualizó su correo exitosamente a $newEmail", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Correo actualizado correctamente.', 'newValue' => $newEmail];
            }
            return ['success' => false, 'message' => 'Error crítico al actualizar el correo.'];
        } catch (\Throwable $e) {
            Logger::database("Fallo al confirmar y cambiar correo electrónico", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al procesar el cambio.'];
        }
    }

    public function getPreferences($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT language, open_links_new_tab, theme, extended_alerts FROM user_preferences WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prefs) { return ['language' => 'en-us', 'open_links_new_tab' => 1, 'theme' => 'system', 'extended_alerts' => 0]; }
            return $prefs;
        } catch (\Throwable $e) {
            Logger::database("Error al obtener preferencias para el usuario $userId", Logger::LEVEL_ERROR, $e);
            return ['language' => 'en-us', 'open_links_new_tab' => 1, 'theme' => 'system', 'extended_alerts' => 0];
        }
    }

    public function verifyPassword($userId, $password) {
        if (!Utils::checkRateLimit($this->conn, 'verify_password', 5)) { return ['success' => false, 'message' => 'Demasiados intentos. Por favor, espera 5 minutos.']; }
        try {
            $stmt = $this->conn->prepare("SELECT contrasena FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $hash = $stmt->fetchColumn();

            if (password_verify($password, $hash)) {
                Utils::resetAttempts($this->conn, 'verify_password');
                return ['success' => true];
            }
            Utils::recordActionAttempt($this->conn, 'verify_password', 5, 5);
            return ['success' => false, 'message' => 'La contraseña actual es incorrecta.'];
        } catch (\Throwable $e) {
            Logger::database("Fallo al intentar verificar contraseña del usuario $userId", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error del servidor.'];
        }
    }

    public function updatePassword($userId, $currentPassword, $newPassword) {
        if (!Utils::checkRateLimit($this->conn, 'update_password', 5)) { return ['success' => false, 'message' => 'Demasiados intentos. Por favor, espera 5 minutos.']; }

        try {
            $stmt = $this->conn->prepare("SELECT contrasena FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPassword, $hash)) {
                Utils::recordActionAttempt($this->conn, 'update_password', 5, 5);
                return ['success' => false, 'message' => 'La contraseña actual es incorrecta.'];
            }

            if (!Utils::validatePassword($newPassword)) { return ['success' => false, 'message' => 'js.auth.err_pass_length']; }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $updStmt = $this->conn->prepare("UPDATE users SET contrasena = :new_hash WHERE id = :id");
            if ($updStmt->execute([':new_hash' => $newHash, ':id' => $userId])) {
                $this->logChange($userId, 'contrasena', $hash, $newHash);
                Utils::resetAttempts($this->conn, 'update_password');
                Logger::system("Usuario ID: $userId actualizó su contraseña con éxito", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Contraseña actualizada correctamente.'];
            }
            return ['success' => false, 'message' => 'Error al actualizar la contraseña en la base de datos.'];
        } catch (\Throwable $e) {
            Logger::database("Error crítico al actualizar contraseña", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al procesar.'];
        }
    }

    public function updatePreference($userId, $field, $value) {
        try {
            if (!Utils::checkRateLimit($this->conn, 'update_preference', 5, 15)) { return ['success' => false, 'message' => 'js.profile.err_limit_prefs']; }
            Utils::recordActionAttempt($this->conn, 'update_preference', 15, 5);

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
            if ($stmt->execute([':id' => $userId, ':val' => $value, ':val2' => $value])) { 
                return ['success' => true, 'message' => 'Guardado.']; 
            }
            return ['success' => false, 'message' => 'Error de base de datos.'];
        } catch (\Throwable $e) {
            Logger::database("Fallo al actualizar preferencia: $field para ID $userId", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al actualizar preferencia.'];
        }
    }

    // ==========================================
    // MÉTODOS DE 2FA
    // ==========================================

    public function init2FA($userId) {
        Logger::system("Iniciando proceso 2FA para usuario ID: $userId", Logger::LEVEL_DEBUG);

        try {
            $stmt = $this->conn->prepare("SELECT correo, two_factor_enabled, two_factor_secret, two_factor_recovery_codes FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Logger::system("Proceso 2FA falló: Usuario $userId no encontrado en BD.", Logger::LEVEL_WARNING);
                return ['success' => false, 'message' => 'Usuario no encontrado.'];
            }

            if ($user['two_factor_enabled']) {
                Logger::system("Proceso 2FA init para usuario $userId: Ya está activado. Retornando conteo de códigos de respaldo.", Logger::LEVEL_DEBUG);
                $codes = json_decode($user['two_factor_recovery_codes'], true) ?? [];
                return ['success' => true, 'enabled' => true, 'codes_count' => count($codes)];
            }

            if (!class_exists('\RobThree\Auth\TwoFactorAuth')) {
                throw new \Exception("Clase RobThree no encontrada. Error crítico de dependencias.");
            }

            $tfa = new \RobThree\Auth\TwoFactorAuth('Project Aurora');
            $secret = $user['two_factor_secret'];
            
            if (empty($secret)) {
                Logger::system("Generando nuevo secreto 2FA para el usuario $userId.", Logger::LEVEL_DEBUG);
                $secret = $tfa->createSecret();
                $upd = $this->conn->prepare("UPDATE users SET two_factor_secret = :sec WHERE id = :id");
                $upd->execute([':sec' => $secret, ':id' => $userId]);
            }

            $qrUrl = $tfa->getQRCodeImageAsDataUri('Project Aurora (' . $user['correo'] . ')', $secret);

            return [
                'success' => true,
                'enabled' => false,
                'secret' => $secret,
                'qr' => $qrUrl
            ];

        } catch (\Throwable $e) {
            Logger::system("Error catastrófico en la inicialización de 2FA", Logger::LEVEL_CRITICAL, $e);
            return ['success' => false, 'message' => 'Error interno grave. Contacte soporte.'];
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
                
                Logger::system("El usuario ID: $userId habilitó exitosamente el 2FA", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => '2FA activado correctamente', 'recovery_codes' => $recoveryCodes];
            }
            
            Logger::system("Intento fallido de activar 2FA (Código incorrecto) para el usuario ID: $userId", Logger::LEVEL_WARNING);
            return ['success' => false, 'message' => 'Código de verificación incorrecto.'];
        } catch (\Throwable $e) {
            Logger::database("Error en el proceso enable2FA", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno.'];
        }
    }

    public function disable2FA($userId, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT contrasena FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($password, $hash)) { 
                return ['success' => false, 'message' => 'Contraseña incorrecta.']; 
            }
            
            $upd = $this->conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_recovery_codes = NULL WHERE id = :id");
            $upd->execute([':id' => $userId]);
            
            Logger::system("El usuario ID: $userId ha desactivado el 2FA", Logger::LEVEL_INFO);
            return ['success' => true, 'message' => '2FA desactivado.'];
        } catch (\Throwable $e) {
            Logger::database("Fallo al desactivar 2FA", Logger::LEVEL_ERROR, $e);
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
            
            Logger::system("El usuario ID: $userId regeneró sus códigos de recuperación 2FA", Logger::LEVEL_INFO);
            return ['success' => true, 'message' => 'Códigos generados correctamente.', 'recovery_codes' => $recoveryCodes];
        } catch (\Throwable $e) {
            Logger::database("Error al regenerar códigos de 2FA", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno.'];
        }
    }
}
?>