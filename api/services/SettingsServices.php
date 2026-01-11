<?php
// api/services/SettingsServices.php

require_once __DIR__ . '/../../config/database/db.php';

class SettingsServices {
    private $db;
    // Definimos las rutas base
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

    public function updateField($userId, $field, $value) {
        // ... (Misma lógica anterior para updateField) ...
        $allowed = ['username', 'email'];
        if (!in_array($field, $allowed)) return ['status' => false, 'message' => 'Campo no permitido.'];
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) return ['status' => false, 'message' => 'Email inválido.'];
        if ($field === 'username' && (strlen($value) < 6 || strlen($value) > 32)) return ['status' => false, 'message' => 'Usuario inválido (6-32 chars).'];

        $check = $this->db->prepare("SELECT id FROM users WHERE $field = :val AND id != :id LIMIT 1");
        $check->execute([':val' => $value, ':id' => $userId]);
        if ($check->rowCount() > 0) return ['status' => false, 'message' => "Este $field ya está en uso."];

        $upd = $this->db->prepare("UPDATE users SET $field = :val WHERE id = :id");
        if ($upd->execute([':val' => $value, ':id' => $userId])) {
            if ($field === 'username') $_SESSION['username'] = $value;
            return ['status' => true, 'message' => 'Actualizado correctamente.'];
        }
        return ['status' => false, 'message' => 'Error BD.'];
    }

    // --- SUBIR FOTO (Carpeta: uploads) ---
    public function updateAvatar($userId, $file) {
        $targetDir = $this->baseDir . 'uploads/'; // <--- NUEVA CARPETA
        $targetWeb = $this->webPath . 'uploads/';

        // Validaciones
        $types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $types)) return ['status' => false, 'message' => 'Formato no soportado.'];
        if ($file['size'] > 2 * 1024 * 1024) return ['status' => false, 'message' => 'Máximo 2MB.'];

        // Crear carpeta si no existe
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        // Nombre único
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $userId . '_' . time() . '.' . $ext;

        if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
            // Borrar foto anterior (SOLO si estaba en uploads)
            $this->removeOldAvatar($userId, 'uploads');

            // Guardar en BD
            $newUrl = $targetWeb . $filename;
            $this->updateDbAvatar($userId, $newUrl);
            
            return ['status' => true, 'message' => 'Foto actualizada.', 'url' => $newUrl];
        }
        return ['status' => false, 'message' => 'Error al guardar archivo.'];
    }

    // --- ELIMINAR FOTO (Volver a Default en carpeta: defaults) ---
    public function deleteAvatar($userId) {
        $user = $this->getUserProfile($userId);
        
        // 1. Borrar foto actual si es custom (está en uploads)
        $this->removeOldAvatar($userId, 'uploads');

        // 2. Generar y Guardar nueva imagen default
        $targetDir = $this->baseDir . 'defaults/'; // <--- NUEVA CARPETA
        $targetWeb = $this->webPath . 'defaults/';
        
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        // Usamos UI Avatars y guardamos el archivo localmente
        $filename = 'default_' . $userId . '_' . time() . '.png';
        $apiUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=random&color=fff&size=128";
        
        $content = @file_get_contents($apiUrl);
        if ($content) {
            file_put_contents($targetDir . $filename, $content);
            $newUrl = $targetWeb . $filename;
        } else {
            // Fallback si falla la API: usar una imagen estática local genérica
            $newUrl = $this->webPath . 'default-user.png'; 
        }

        $this->updateDbAvatar($userId, $newUrl);
        return ['status' => true, 'message' => 'Foto eliminada.', 'url' => $newUrl];
    }

    // --- Helpers Privados ---

    private function removeOldAvatar($userId, $folderName) {
        $user = $this->getUserProfile($userId);
        $currentUrl = $user['profile_picture_url'];

        // Solo borramos si la URL contiene la carpeta indicada (ej: '/uploads/')
        if (strpos($currentUrl, "/$folderName/") !== false) {
            // Extraer nombre de archivo
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