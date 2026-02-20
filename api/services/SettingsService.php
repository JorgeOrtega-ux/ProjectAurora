<?php
// api/services/SettingsService.php
namespace App\Api\Services;

use PDO;
use App\Core\Utils;

class SettingsService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function logChange($userId, $field, $oldValue, $newValue) {
        $stmt = $this->conn->prepare("INSERT INTO user_changes_log (user_id, modified_field, old_value, new_value) VALUES (:user_id, :field, :old_val, :new_val)");
        $stmt->execute([
            ':user_id' => $userId,
            ':field'   => $field,
            ':old_val' => $oldValue,
            ':new_val' => $newValue
        ]);
    }

    private function countRecentChanges($userId, $field, $interval, $onlyUploads = false) {
        $query = "SELECT COUNT(*) FROM user_changes_log WHERE user_id = :uid AND modified_field = :field AND changed_at >= DATE_SUB(NOW(), INTERVAL $interval)";
        if ($onlyUploads) {
            $query .= " AND new_value LIKE '%uploaded/%'";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':uid' => $userId, ':field' => $field]);
        return (int) $stmt->fetchColumn();
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
        $ip = $this->getUserIP();
        $query = "SELECT attempts, blocked_until, last_attempt, (blocked_until > NOW()) as is_blocked FROM rate_limits WHERE ip_address = :ip AND action = :action LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':ip' => $ip, ':action' => $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            if ($result['is_blocked']) {
                return false; 
            }
            // Si ya no está bloqueado pero tenía un bloqueo previo, o si pasaron 5 min desde el último intento, reseteamos el contador
            if ($result['blocked_until'] !== null || strtotime($result['last_attempt']) < strtotime('-5 minutes')) {
                $this->resetAttempts($action);
            }
        }
        return true;
    }

    private function recordActionAttempt($action, $maxAttempts = 15, $blockMinutes = 5) {
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
            $updStmt->execute([
                ':attempts' => $attempts, 
                ':blocked_until' => $blocked_until, 
                ':ip' => $ip, 
                ':action' => $action
            ]);
        } else {
            $insert = "INSERT INTO rate_limits (ip_address, action, attempts, last_attempt) VALUES (:ip, :action, 1, NOW())";
            $insStmt = $this->conn->prepare($insert);
            $insStmt->execute([':ip' => $ip, ':action' => $action]);
        }
    }

    private function resetAttempts($action) {
        $ip = $this->getUserIP();
        $query = "DELETE FROM rate_limits WHERE ip_address = :ip AND action = :action";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':ip' => $ip, ':action' => $action]);
    }

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
            if ($checkStmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'El correo ya está en uso.'];
            }
        }

        $stmt = $this->conn->prepare("SELECT $field FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $oldValue = $stmt->fetchColumn();

        if ($oldValue === $newValue) {
            return ['success' => true, 'message' => 'No hubo cambios.', 'newValue' => $newValue];
        }

        if ($field === 'nombre') {
            if ($this->countRecentChanges($userId, 'nombre', '7 DAY') >= 1) {
                return ['success' => false, 'message' => 'js.profile.err_limit_username'];
            }
        } else if ($field === 'correo') {
            if ($this->countRecentChanges($userId, 'correo', '7 DAY') >= 1) {
                return ['success' => false, 'message' => 'js.profile.err_limit_email'];
            }
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

    // ==========================================
    // SISTEMA DE PREFERENCIAS
    // ==========================================

    public function getPreferences($userId) {
        $stmt = $this->conn->prepare("SELECT language, open_links_new_tab FROM user_preferences WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prefs) {
            return [
                'language' => 'en-us',
                'open_links_new_tab' => 1
            ];
        }
        return $prefs;
    }

    public function updatePreference($userId, $field, $value) {
        // --- VALIDAR SPAM DE PREFERENCIAS ---
        if (!$this->checkRateLimit('update_preference')) {
            return ['success' => false, 'message' => 'js.profile.err_limit_prefs'];
        }
        // Registramos el intento (Max 15 cambios, bloquea 5 minutos)
        $this->recordActionAttempt('update_preference', 15, 5);
        // ------------------------------------

        $allowedFields = ['language', 'open_links_new_tab'];
        if (!in_array($field, $allowedFields)) {
            return ['success' => false, 'message' => 'Campo de preferencia no válido.'];
        }

        if ($field === 'language') {
            $allowedLangs = ['en-us', 'en-gb', 'fr-fr', 'de-de', 'it-it', 'es-latam', 'es-mx', 'es-es', 'pt-br', 'pt-pt'];
            if (!in_array($value, $allowedLangs)) {
                $value = 'en-us'; 
            }
            setcookie('aurora_lang', $value, time() + 31536000, '/');
        }

        if ($field === 'open_links_new_tab') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO user_preferences (user_id, $field) 
            VALUES (:id, :val) 
            ON DUPLICATE KEY UPDATE $field = :val2
        ");

        if ($stmt->execute([':id' => $userId, ':val' => $value, ':val2' => $value])) {
            return ['success' => true, 'message' => 'Preferencia guardada exitosamente.'];
        }

        return ['success' => false, 'message' => 'Error de base de datos al guardar preferencia.'];
    }
}
?>