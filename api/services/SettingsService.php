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

    // Método privado para registrar en el Log de Auditoría usando nombres de columnas en inglés
    private function logChange($userId, $field, $oldValue, $newValue) {
        $stmt = $this->conn->prepare("INSERT INTO user_changes_log (user_id, modified_field, old_value, new_value) VALUES (:user_id, :field, :old_val, :new_val)");
        $stmt->execute([
            ':user_id' => $userId,
            ':field'   => $field,
            ':old_val' => $oldValue,
            ':new_val' => $newValue
        ]);
    }

    public function uploadAvatar($userId, $file) {
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
            
            // Guardar el registro en el log
            $this->logChange($userId, 'avatar', $oldAvatar, $webPath);
            
            $_SESSION['user_avatar'] = $webPath;

            return [
                'success' => true,
                'message' => 'Foto de perfil actualizada correctamente.',
                'avatar' => $webPath 
            ];
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
            
            // Guardar el registro en el log
            $this->logChange($userId, 'avatar', $oldAvatar, $newWebPath);
            
            $_SESSION['user_avatar'] = $newWebPath;

            return [
                'success' => true,
                'message' => 'Foto de perfil eliminada. Se restauró a predeterminada.',
                'avatar' => $newWebPath
            ];
        }

        return ['success' => false, 'message' => 'Error al restaurar el avatar.'];
    }

    public function updateField($userId, $field, $newValue) {
        $allowedFields = ['nombre', 'correo'];
        
        if (!in_array($field, $allowedFields)) {
            return ['success' => false, 'message' => 'Campo no válido.'];
        }

        $newValue = trim($newValue);

        if (empty($newValue)) {
            return ['success' => false, 'message' => 'El valor no puede estar vacío.'];
        }

        // Si es correo, validar formato y que no esté en uso
        if ($field === 'correo') {
            if (!filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Formato de correo inválido.'];
            }
            $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE correo = :correo AND id != :id");
            $checkStmt->execute([':correo' => $newValue, ':id' => $userId]);
            if ($checkStmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'El correo ya está en uso por otra cuenta.'];
            }
        }

        // Obtener el valor actual para compararlo y guardarlo en el log
        $stmt = $this->conn->prepare("SELECT $field FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $oldValue = $stmt->fetchColumn();

        if ($oldValue === $newValue) {
            return ['success' => true, 'message' => 'No hubo cambios.', 'newValue' => $newValue];
        }

        // Actualizar en base de datos
        $updStmt = $this->conn->prepare("UPDATE users SET $field = :new_val WHERE id = :id");
        
        if ($updStmt->execute([':new_val' => $newValue, ':id' => $userId])) {
            
            // GUARDAR REGISTRO DE AUDITORÍA
            $this->logChange($userId, $field, $oldValue, $newValue);

            // Actualizar variables de sesión activas
            if ($field === 'nombre') {
                $_SESSION['user_name'] = $newValue;
            } else if ($field === 'correo') {
                $_SESSION['user_email'] = $newValue;
            }

            return ['success' => true, 'message' => 'Actualizado correctamente.', 'newValue' => $newValue];
        }

        return ['success' => false, 'message' => 'Error al actualizar el campo en la base de datos.'];
    }
}
?>