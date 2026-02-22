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

    private function getTargetUser($targetUuid) {
        $stmt = $this->conn->prepare("SELECT id, uuid, username, email, avatar_path, role FROM users WHERE uuid = :uuid LIMIT 1");
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
        $allowedFields = ['username', 'email', 'role'];
        if (!in_array($field, $allowedFields)) return ['success' => false, 'message' => 'Campo no válido.'];

        $user = $this->getTargetUser($targetUuid);
        if (!$user) return ['success' => false, 'message' => 'Usuario no encontrado.'];

        $userId = $user['id'];
        $oldValue = $user[$field];
        $newValue = trim($newValue);

        if (empty($newValue)) return ['success' => false, 'message' => 'El valor no puede estar vacío.'];
        if ($oldValue === $newValue) return ['success' => true, 'message' => 'Sin cambios.', 'newValue' => $newValue];

        try {
            if ($field === 'username') {
                if (!Utils::validateUsername($newValue)) return ['success' => false, 'message' => 'Formato o longitud de usuario inválida.'];
            } else if ($field === 'email') {
                if (!Utils::validateEmail($newValue)) return ['success' => false, 'message' => 'Correo inválido o dominio no permitido.'];
                $checkStmt = $this->conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $checkStmt->execute([':email' => $newValue, ':id' => $userId]);
                if ($checkStmt->rowCount() > 0) return ['success' => false, 'message' => 'El correo ya está en uso por otro usuario.'];
            } else if ($field === 'role') {
                $allowedRoles = ['user', 'moderator', 'administrator', 'founder'];
                if (!in_array($newValue, $allowedRoles)) return ['success' => false, 'message' => 'Rol no válido.'];

                $stmtAdmin = $this->conn->prepare("SELECT role FROM users WHERE id = :id");
                $stmtAdmin->execute([':id' => $adminId]);
                $adminRole = $stmtAdmin->fetchColumn();

                if ($adminRole !== 'founder' && ($oldValue === 'founder' || $newValue === 'founder')) {
                    return ['success' => false, 'message' => 'Permisos insuficientes para asignar o modificar a un Fundador.'];
                }

                if ($userId === $adminId) {
                    return ['success' => false, 'message' => 'No puedes modificar tu propio rol.'];
                }
            }

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

    public function updatePreference($targetUuid, $field, $value, $adminId) {
        $allowedFields = ['language', 'open_links_new_tab', 'theme', 'extended_alerts'];
        if (!in_array($field, $allowedFields)) return ['success' => false, 'message' => 'Campo de preferencia no válido.'];

        $user = $this->getTargetUser($targetUuid);
        if (!$user) return ['success' => false, 'message' => 'Usuario no encontrado.'];
        
        $userId = $user['id'];

        if ($field === 'language') {
            $allowedLangs = ['en-us', 'en-gb', 'fr-fr', 'de-de', 'it-it', 'es-latam', 'es-mx', 'es-es', 'pt-br', 'pt-pt'];
            if (!in_array($value, $allowedLangs)) $value = 'en-us';
        } elseif ($field === 'theme') {
            $allowedThemes = ['system', 'light', 'dark'];
            if (!in_array($value, $allowedThemes)) $value = 'system';
        } elseif (in_array($field, ['open_links_new_tab', 'extended_alerts'])) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        try {
            $stmt = $this->conn->prepare("SELECT $field FROM user_preferences WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            $oldValue = $stmt->fetchColumn();

            if ($oldValue !== false && (string)$oldValue === (string)$value) {
                return ['success' => true, 'message' => 'No hubo cambios en la preferencia.'];
            }

            $updStmt = $this->conn->prepare("INSERT INTO user_preferences (user_id, $field) VALUES (:id, :val) ON DUPLICATE KEY UPDATE $field = :val2");
            if ($updStmt->execute([':id' => $userId, ':val' => $value, ':val2' => $value])) {
                $this->logChange($userId, 'pref_' . $field, $oldValue, $value, $adminId);
                Logger::system("Admin ID: $adminId actualizó la preferencia $field para Usuario ID: $userId. Valor: $value", Logger::LEVEL_INFO);
                return ['success' => true, 'message' => 'Preferencia actualizada por el administrador.'];
            }
            return ['success' => false, 'message' => 'Error de base de datos al guardar la preferencia.'];
        } catch (\Throwable $e) {
            Logger::database("Error DB en AdminService::updatePreference", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Fallo interno al actualizar preferencia.'];
        }
    }

    public function updateAccountStatus($targetUuid, $data, $adminId) {
        $user = $this->getTargetUser($targetUuid);
        if (!$user) return ['success' => false, 'message' => 'Usuario no encontrado.'];
        
        $userId = $user['id'];

        if ($userId === $adminId) {
            return ['success' => false, 'message' => 'No puedes modificar tu propio estado desde este panel.'];
        }

        if ($user['role'] === 'founder') {
             $stmtAdmin = $this->conn->prepare("SELECT role FROM users WHERE id = :id");
             $stmtAdmin->execute([':id' => $adminId]);
             $adminRole = $stmtAdmin->fetchColumn();
             if ($adminRole !== 'founder') {
                 return ['success' => false, 'message' => 'No tienes permisos para modificar el estado de un Fundador.'];
             }
        }
        
        $status = (isset($data->status) && $data->status === 'deleted') ? 'deleted' : 'active';
        $is_suspended = (isset($data->is_suspended) && $data->is_suspended == 1) ? 1 : 0;
        
        $suspension_type = null;
        $suspension_expires_at = null;
        $suspension_reason = null;
        
        $deletion_type = null;
        $deletion_reason = null;

        if ($status === 'active' && $is_suspended == 1) {
            $suspension_type = (isset($data->suspension_type) && in_array($data->suspension_type, ['temporal', 'permanent'])) ? $data->suspension_type : null;
            
            if (!$suspension_type) {
                return ['success' => false, 'message' => 'Debes especificar el tipo de suspensión (temporal o permanente).'];
            }

            if ($suspension_type === 'temporal') {
                if (empty($data->suspension_expires_at)) {
                    return ['success' => false, 'message' => 'Debes especificar la fecha y hora de expiración para la suspensión temporal.'];
                }
                
                $d = \DateTime::createFromFormat('Y-m-d\TH:i', $data->suspension_expires_at); 
                if (!$d) {
                    $d = \DateTime::createFromFormat('Y-m-d H:i:s', $data->suspension_expires_at);
                }
                if (!$d) {
                    return ['success' => false, 'message' => 'El formato de la fecha de expiración no es válido.'];
                }
                $suspension_expires_at = $d->format('Y-m-d H:i:s');
            }

            // Integración del Formateo de Razón predefinida + Nota Adicional
            $cat = isset($data->suspension_category) ? trim($data->suspension_category) : 'other';
            $catLabel = isset($data->suspension_category_label) ? trim($data->suspension_category_label) : '';
            $note = isset($data->suspension_note) ? trim($data->suspension_note) : '';

            if ($cat !== 'other' && !empty($catLabel)) {
                $suspension_reason = "[Infracción: " . $catLabel . "]";
                if ($note !== '') {
                    $suspension_reason .= " - Nota del admin: " . $note;
                }
            } else {
                $suspension_reason = $note !== '' ? $note : null;
            }
        }

        if ($status === 'deleted') {
            $deletion_type = !empty($data->deletion_type) ? trim($data->deletion_type) : 'admin_banned';
            $deletion_reason = !empty($data->deletion_reason) ? trim($data->deletion_reason) : null;
        }

        try {
            $stmtOld = $this->conn->prepare("SELECT status, is_suspended FROM users WHERE id = :id");
            $stmtOld->execute([':id' => $userId]);
            $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
            
            $oldSummary = "Status: {$oldData['status']}, Suspended: {$oldData['is_suspended']}";
            $newSummary = "Status: $status, Suspended: $is_suspended";

            $query = "UPDATE users SET 
                        status = :status,
                        is_suspended = :is_suspended,
                        suspension_type = :suspension_type,
                        suspension_expires_at = :suspension_expires_at,
                        suspension_reason = :suspension_reason,
                        deletion_type = :deletion_type,
                        deletion_reason = :deletion_reason
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':status' => $status,
                ':is_suspended' => $is_suspended,
                ':suspension_type' => $suspension_type,
                ':suspension_expires_at' => $suspension_expires_at,
                ':suspension_reason' => $suspension_reason,
                ':deletion_type' => $deletion_type,
                ':deletion_reason' => $deletion_reason,
                ':id' => $userId
            ]);

            $this->logChange($userId, 'account_status_and_access', $oldSummary, $newSummary, $adminId);

            if ($status === 'deleted' || $is_suspended == 1) {
                $delSessions = $this->conn->prepare("DELETE FROM user_sessions WHERE user_id = :id");
                $delSessions->execute([':id' => $userId]);
            }

            Logger::system("Admin ID: $adminId actualizó control de acceso para Usuario ID: $userId. $newSummary", Logger::LEVEL_INFO);
            
            return ['success' => true, 'message' => 'El estado y control de acceso de la cuenta se han actualizado correctamente.'];

        } catch (\Throwable $e) {
            Logger::database("Error DB en AdminService::updateAccountStatus", Logger::LEVEL_ERROR, $e);
            return ['success' => false, 'message' => 'Error interno al actualizar el estado de la cuenta.'];
        }
    }
}
?>