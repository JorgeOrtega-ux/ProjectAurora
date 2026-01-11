<?php
// api/services/SettingsServices.php

require_once __DIR__ . '/../../config/database/db.php';

class SettingsServices {
    private $db;
    private $baseDir;
    private $webPath;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $database = new Database();
        $this->db = $database->getConnection();

        // Rutas Base
        $this->baseDir = __DIR__ . '/../../public/assets/img/avatars/';
        $this->webPath = '/ProjectAurora/public/assets/img/avatars/';
    }

    public function getUserProfile($userId) {
        $stmt = $this->db->prepare("SELECT id, username, email, profile_picture_url, account_role FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- ACTUALIZAR DATOS DE TEXTO (Username / Email) ---
    public function updateField($userId, $field, $value) {
        $allowed = ['username', 'email'];
        if (!in_array($field, $allowed)) return ['status' => false, 'message' => 'Campo no permitido.'];
        
        // Validaciones básicas
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'Email inválido.'];
        }
        if ($field === 'username' && (strlen($value) < 6 || strlen($value) > 32)) {
            return ['status' => false, 'message' => 'Usuario inválido (6-32 chars).'];
        }

        // Verificar duplicados (excluyendo al propio usuario)
        $check = $this->db->prepare("SELECT id FROM users WHERE $field = :val AND id != :id LIMIT 1");
        $check->execute([':val' => $value, ':id' => $userId]);
        if ($check->rowCount() > 0) {
            return ['status' => false, 'message' => "Este $field ya está en uso."];
        }

        // 1. Obtener valor anterior para el LOG
        $currentUser = $this->getUserProfile($userId);
        $oldValue = $currentUser[$field] ?? '';

        // Si el valor es igual, no hacemos nada
        if ($oldValue === $value) {
            return ['status' => true, 'message' => 'No hubo cambios.'];
        }

        // 2. Realizar Update
        $upd = $this->db->prepare("UPDATE users SET $field = :val WHERE id = :id");
        if ($upd->execute([':val' => $value, ':id' => $userId])) {
            
            // Actualizar sesión si es username
            if ($field === 'username') $_SESSION['username'] = $value;

            // 3. REGISTRAR CAMBIO EN HISTORIAL
            $this->logChange($userId, "update_$field", $oldValue, $value);

            return ['status' => true, 'message' => 'Actualizado correctamente.'];
        }
        return ['status' => false, 'message' => 'Error BD.'];
    }

    // --- SUBIR FOTO (Carpeta: uploads) ---
    public function updateAvatar($userId, $file) {
        $targetDir = $this->baseDir . 'uploads/';
        $targetWeb = $this->webPath . 'uploads/';

        // Validaciones
        $types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $types)) return ['status' => false, 'message' => 'Formato no soportado.'];
        if ($file['size'] > 2 * 1024 * 1024) return ['status' => false, 'message' => 'Máximo 2MB.'];

        // Crear carpeta si no existe
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        // 1. Obtener URL anterior para el LOG
        $currentUser = $this->getUserProfile($userId);
        $oldUrl = $currentUser['profile_picture_url'] ?? '';

        // Nombre único
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $userId . '_' . time() . '.' . $ext;

        if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
            // Borrar foto anterior (SOLO si estaba en uploads)
            $this->removeOldAvatar($userId, 'uploads');

            // Guardar en BD
            $newUrl = $targetWeb . $filename;
            $this->updateDbAvatar($userId, $newUrl);

            // 2. REGISTRAR CAMBIO EN HISTORIAL
            $this->logChange($userId, 'update_avatar', $oldUrl, $newUrl);
            
            return ['status' => true, 'message' => 'Foto actualizada.', 'url' => $newUrl];
        }
        return ['status' => false, 'message' => 'Error al guardar archivo.'];
    }

    // --- ELIMINAR FOTO (Volver a Default) ---
    public function deleteAvatar($userId) {
        // 1. Obtener URL anterior para el LOG
        $currentUser = $this->getUserProfile($userId);
        $oldUrl = $currentUser['profile_picture_url'] ?? '';
        
        // Borrar foto actual si es custom (está en uploads)
        $this->removeOldAvatar($userId, 'uploads');

        // Generar y Guardar nueva imagen default
        $targetDir = $this->baseDir . 'defaults/';
        $targetWeb = $this->webPath . 'defaults/';
        
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        // Usamos UI Avatars y guardamos el archivo localmente
        $filename = 'default_' . $userId . '_' . time() . '.png';
        $apiUrl = "https://ui-avatars.com/api/?name=" . urlencode($currentUser['username']) . "&background=random&color=fff&size=128";
        
        $content = @file_get_contents($apiUrl);
        if ($content) {
            file_put_contents($targetDir . $filename, $content);
            $newUrl = $targetWeb . $filename;
        } else {
            // Fallback
            $newUrl = $this->webPath . 'default-user.png'; 
        }

        $this->updateDbAvatar($userId, $newUrl);

        // 2. REGISTRAR CAMBIO EN HISTORIAL
        $this->logChange($userId, 'delete_avatar', $oldUrl, $newUrl);

        return ['status' => true, 'message' => 'Foto eliminada.', 'url' => $newUrl];
    }

    // --- HELPER PRIVADO: REGISTRAR CAMBIOS ---
    private function logChange($userId, $changeType, $oldValue, $newValue) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

            $sql = "INSERT INTO profile_changes (user_id, change_type, old_value, new_value, ip_address, user_agent, created_at) 
                    VALUES (:uid, :type, :old, :new, :ip, :ua, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':uid'  => $userId,
                ':type' => $changeType,
                ':old'  => $oldValue,
                ':new'  => $newValue,
                ':ip'   => $ip,
                ':ua'   => $ua
            ]);
        } catch (Exception $e) {
            // Silencioso: Si falla el log, no detenemos la app, pero podríamos guardar en error_log
            error_log("Error guardando log de perfil: " . $e->getMessage());
        }
    }

    // --- Helpers Privados Existentes ---

    private function removeOldAvatar($userId, $folderName) {
        $user = $this->getUserProfile($userId);
        $currentUrl = $user['profile_picture_url'];

        if (strpos($currentUrl, "/$folderName/") !== false) {
            $basename = basename($currentUrl);
            $path = $this->baseDir . $folderName . '/' . $basename;
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function updateDbAvatar($userId, $url) {
        $stmt = $this->db->prepare("UPDATE users SET profile_picture_url = :url WHERE id = :id");
        $stmt->execute([':url' => $url, ':id' => $userId]);
        $_SESSION['user_pic'] = $url;
    }
}
?>