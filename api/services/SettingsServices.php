<?php
// api/services/SettingsServices.php

require_once __DIR__ . '/../../config/database/db.php';

class SettingsServices {
    private $db;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // Obtener datos actuales del usuario
    public function getUserProfile($userId) {
        $stmt = $this->db->prepare("SELECT id, username, email, profile_picture_url, account_role FROM users WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar campos de texto (Username/Email)
    public function updateField($userId, $field, $value) {
        $allowed = ['username', 'email'];
        if (!in_array($field, $allowed)) return ['status' => false, 'message' => 'Campo no permitido.'];

        // 1. Validaciones básicas
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'Formato de correo inválido.'];
        }
        if ($field === 'username' && (strlen($value) < 6 || strlen($value) > 32)) {
            return ['status' => false, 'message' => 'El usuario debe tener entre 6 y 32 caracteres.'];
        }

        // 2. Verificar duplicados (excluyendo al propio usuario)
        $check = $this->db->prepare("SELECT id FROM users WHERE $field = :val AND id != :id LIMIT 1");
        $check->execute([':val' => $value, ':id' => $userId]);
        if ($check->rowCount() > 0) return ['status' => false, 'message' => "Este $field ya está en uso."];

        // 3. Actualizar
        $upd = $this->db->prepare("UPDATE users SET $field = :val WHERE id = :id");
        if ($upd->execute([':val' => $value, ':id' => $userId])) {
            // Actualizar sesión para reflejar cambios inmediatos
            if ($field === 'username') $_SESSION['username'] = $value;
            // Si cambias el email, aquí podrías marcar la cuenta como "no verificada" si tuvieras esa lógica.
            
            return ['status' => true, 'message' => 'Actualizado correctamente.'];
        }
        return ['status' => false, 'message' => 'Error en base de datos.'];
    }

    // Subir y actualizar Avatar
    public function updateAvatar($userId, $file) {
        $uploadDir = __DIR__ . '/../../public/assets/img/avatars/';
        $webPath = '/ProjectAurora/public/assets/img/avatars/'; // Ajusta si tu ruta base cambia

        // Validaciones
        $types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $types)) return ['status' => false, 'message' => 'Formato no soportado (JPG, PNG, GIF, WEBP).'];
        if ($file['size'] > 2 * 1024 * 1024) return ['status' => false, 'message' => 'La imagen pesa más de 2MB.'];

        // Preparar directorio
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

        // Generar nombre único (ID_Timestamp_Random.ext)
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $userId . '_' . time() . '_' . uniqid() . '.' . $ext;
        
        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            // Obtener avatar viejo para borrarlo (si no es default o externo)
            $oldData = $this->getUserProfile($userId);
            $oldPic = str_replace($webPath, '', $oldData['profile_picture_url']);
            
            if ($oldPic && file_exists($uploadDir . $oldPic) && !str_contains($oldData['profile_picture_url'], 'ui-avatars')) {
                unlink($uploadDir . $oldPic);
            }

            // Actualizar BD
            $newUrl = $webPath . $filename;
            $stmt = $this->db->prepare("UPDATE users SET profile_picture_url = :url WHERE id = :id");
            if ($stmt->execute([':url' => $newUrl, ':id' => $userId])) {
                $_SESSION['user_pic'] = $newUrl; // Actualizar sesión
                return ['status' => true, 'message' => 'Foto actualizada.', 'url' => $newUrl];
            }
        }
        return ['status' => false, 'message' => 'Error al guardar el archivo.'];
    }

    // Eliminar Avatar (Resetear a UI Avatars)
    public function deleteAvatar($userId) {
        $user = $this->getUserProfile($userId);
        
        // Generar avatar por defecto basado en nombre
        $newUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=random&color=fff&size=128";

        // Borrar archivo físico anterior si existe
        $uploadDir = __DIR__ . '/../../public/assets/img/avatars/';
        $webPath = '/ProjectAurora/public/assets/img/avatars/';
        $oldPic = str_replace($webPath, '', $user['profile_picture_url']);
        
        if ($oldPic && file_exists($uploadDir . $oldPic)) {
            unlink($uploadDir . $oldPic);
        }

        $stmt = $this->db->prepare("UPDATE users SET profile_picture_url = :url WHERE id = :id");
        if ($stmt->execute([':url' => $newUrl, ':id' => $userId])) {
            $_SESSION['user_pic'] = $newUrl;
            return ['status' => true, 'message' => 'Foto eliminada.', 'url' => $newUrl];
        }
        return ['status' => false, 'message' => 'Error al eliminar.'];
    }
}
?>