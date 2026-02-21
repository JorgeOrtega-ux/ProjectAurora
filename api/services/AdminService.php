<?php
// api/services/AdminService.php
namespace App\Api\Services;

use PDO;
use App\Core\Utils;
use App\Core\Logger;

class AdminService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Helper interno para registrar cambios (Se registra en el log del usuario afectado)
    private function logChange($userId, $field, $oldValue, $newValue, $adminId) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO user_changes_log (user_id, modified_field, old_value, new_value) VALUES (:user_id, :field, :old_val, :new_val)");
            $stmt->execute([
                ':user_id' => $userId,
                ':field'   => $field . ' (by Admin ID: ' . $adminId . ')',
                ':old_val' => $oldValue,
                ':new_val' => $newValue
            ]);
        } catch (\Throwable $e) {
            Logger::database("Error al registrar cambio administrativo en user_changes_log", Logger::LEVEL_ERROR, $e);
        }
    }

    // Obtener los datos actuales del usuario objetivo por su UUID
    private function getTargetUser($targetUuid) {
        $stmt = $this->conn->prepare("SELECT id, uuid, username, email, avatar_path FROM users WHERE uuid = :uuid LIMIT 1");
        $stmt->execute([':uuid' => $targetUuid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAvatar($targetUuid, $file, $adminId) {
        $user = $this->getTargetUser($targetUuid);
        if (!$user) return ['success' => false, 'message' => 'Usuario no encontrado.'];
        
        $userId = $user['id'];
        $oldAvatar = $user['avatar_path'];

        $storageDir = __DIR__ . '/../../public/storage/profilePictures/uploaded/';
        $webDir = 'storage/profilePictures/uploaded/';

        $processResult = Utils::processAndSaveImage($file, $storageDir, $webDir);
        if (!$processResult['success']) return $processResult;

        $webPath = $processResult['webPath'];

        try {
            // Eliminar imagen anterior si era subida
            if ($oldAvatar && strpos($oldAvatar, 'uploaded/') !== false) {
                $oldFile = __DIR__ . '/../../public/' . $oldAvatar;
                if (file_exists($oldFile)) { @unlink($oldFile); }
            }

            $updStmt = $this->conn->prepare("UPDATE users SET avatar_path = :path WHERE id = :id");
            if ($updStmt->execute([':path' => $webPath, ':id' => $userId])) {
                $this->logChange($userId, 'avatar', $oldAvatar, $webPath, $adminId);
                Logger::system("Admin ID: $adminId actualizó el avatar del Usuario ID: $userId", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Avatar actualizado por el administrador.', 'avatar' => $webPath];
            }
        } catch (\Throwable $e) {
            Logger::database("Error DB en AdminService::updateAvatar", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno de base de datos.'];
        }
        return ['success' => false, 'message' => 'Error desconocido.'];
    }

    public function deleteAvatar($targetUuid, $adminId) {
        $user = $this->getTargetUser($targetUuid);
        if (!$user) return ['success' => false, 'message' => 'Usuario no encontrado.'];

        $userId = $user['id'];
        $oldAvatar = $user['avatar_path'];

        try {
            if (strpos($oldAvatar, 'uploaded/') !== false) {
                $oldFile = __DIR__ . '/../../public/' . $oldAvatar;
                if (file_exists($oldFile)) { @unlink($oldFile); }
            }

            $storageDir = __DIR__ . '/../../public/storage/profilePictures/default/';
            $webDir = 'storage/profilePictures/default/';
            $newWebPath = Utils::generateAndSaveAvatar($user['username'], $user['uuid'], $storageDir, $webDir);

            if (!$newWebPath) return ['success' => false, 'message' => 'No se pudo generar el avatar por defecto.'];

            $updStmt = $this->conn->prepare("UPDATE users SET avatar_path = :path WHERE id = :id");
            if ($updStmt->execute([':path' => $newWebPath, ':id' => $userId])) {
                $this->logChange($userId, 'avatar', $oldAvatar, $newWebPath, $adminId);
                Logger::system("Admin ID: $adminId eliminó el avatar del Usuario ID: $userId", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Avatar restaurado forzosamente.', 'avatar' => $newWebPath];
            }
            return ['success' => false, 'message' => 'Error al actualizar base de datos.'];
        } catch (\Throwable $e) {
            Logger::database("Error DB en AdminService::deleteAvatar", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno.'];
        }
    }

    public function updateField($targetUuid, $field, $newValue, $adminId) {
        $allowedFields = ['username', 'email'];
        if (!in_array($field, $allowedFields)) return ['success' => false, 'message' => 'Campo no válido.'];

        $user = $this->getTargetUser($targetUuid);
        if (!$user) return ['success' => false, 'message' => 'Usuario no encontrado.'];

        $userId = $user['id'];
        $oldValue = $user[$field];
        $newValue = trim($newValue);

        if (empty($newValue)) return ['success' => false, 'message' => 'El valor no puede estar vacío.'];
        if ($oldValue === $newValue) return ['success' => true, 'message' => 'Sin cambios.', 'newValue' => $newValue];

        try {
            // Validaciones puras (sin Rate Limit)
            if ($field === 'username') {
                if (!Utils::validateUsername($newValue)) return ['success' => false, 'message' => 'Formato o longitud de usuario inválida.'];
            } else if ($field === 'email') {
                if (!Utils::validateEmail($newValue)) return ['success' => false, 'message' => 'Correo inválido o dominio no permitido.'];
                
                // Comprobar colisión
                $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $checkStmt->execute([':email' => $newValue, ':id' => $userId]);
                if ($checkStmt->rowCount() > 0) return ['success' => false, 'message' => 'El correo ya está en uso por otro usuario.'];
            }

            // Actualización directa
            $updStmt = $this->conn->prepare("UPDATE users SET $field = :new_val WHERE id = :id");
            if ($updStmt->execute([':new_val' => $newValue, ':id' => $userId])) {
                $this->logChange($userId, $field, $oldValue, $newValue, $adminId);
                Logger::system("Admin ID: $adminId forzó cambio de $field para Usuario ID: $userId. Valor: $newValue", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Campo actualizado por el administrador.', 'newValue' => $newValue];
            }
            return ['success' => false, 'message' => 'Error al guardar el cambio.'];
        } catch (\Throwable $e) {
            Logger::database("Error DB en AdminService::updateField", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Fallo interno de base de datos.'];
        }
    }
}
?>