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
        
        // 1. VALIDACIONES DE FORMATO
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'Email inválido.'];
        }
        if ($field === 'username' && (strlen($value) < 6 || strlen($value) > 32)) {
            return ['status' => false, 'message' => 'Usuario inválido (6-32 chars).'];
        }

        // 2. VALIDAR QUE NO SEA EL MISMO VALOR ACTUAL
        $currentUser = $this->getUserProfile($userId);
        $oldValue = $currentUser[$field] ?? '';
        if ($oldValue === $value) {
            return ['status' => true, 'message' => 'No hubo cambios.'];
        }

        // 3. VALIDAR LÍMITES DE CAMBIOS (Rate Limiting)
        if ($field === 'username') {
            // Límite: 3 cambios por semana (7 días)
            if (!$this->checkRateLimit($userId, 'update_username', 3, '7 days')) {
                return ['status' => false, 'message' => 'Límite alcanzado: Solo puedes cambiar tu nombre 3 veces por semana.'];
            }
        }
        if ($field === 'email') {
            // Límite: 1 cambio cada 12 días
            if (!$this->checkRateLimit($userId, 'update_email', 1, '12 days')) {
                return ['status' => false, 'message' => 'Límite alcanzado: Solo puedes cambiar tu correo 1 vez cada 12 días.'];
            }
        }

        // 4. VERIFICAR DUPLICADOS
        $check = $this->db->prepare("SELECT id FROM users WHERE $field = :val AND id != :id LIMIT 1");
        $check->execute([':val' => $value, ':id' => $userId]);
        if ($check->rowCount() > 0) {
            return ['status' => false, 'message' => "Este $field ya está en uso."];
        }

        // 5. REALIZAR UPDATE
        $upd = $this->db->prepare("UPDATE users SET $field = :val WHERE id = :id");
        if ($upd->execute([':val' => $value, ':id' => $userId])) {
            
            if ($field === 'username') $_SESSION['username'] = $value;

            // Registrar en historial
            $this->logChange($userId, "update_$field", $oldValue, $value);

            return ['status' => true, 'message' => 'Actualizado correctamente.'];
        }
        return ['status' => false, 'message' => 'Error BD.'];
    }

    // --- SUBIR FOTO (CON GD + RATE LIMIT) ---
    public function updateAvatar($userId, $file) {
        // 1. VALIDAR LÍMITE: 3 cambios al día
        // Agrupamos 'update_avatar' y 'delete_avatar' en el mismo contador
        if (!$this->checkRateLimit($userId, ['update_avatar', 'delete_avatar'], 3, '24 hours')) {
            return ['status' => false, 'message' => 'Límite alcanzado: Solo puedes cambiar tu foto 3 veces al día.'];
        }

        // Verificar GD
        if (!extension_loaded('gd')) return ['status' => false, 'message' => 'Error servidor: GD no instalado.'];

        $targetDir = $this->baseDir . 'uploads/';
        $targetWeb = $this->webPath . 'uploads/';

        // Validaciones de archivo
        if ($file['error'] !== UPLOAD_ERR_OK) return ['status' => false, 'message' => 'Error en la subida.'];
        if ($file['size'] > 5 * 1024 * 1024) return ['status' => false, 'message' => 'Máximo 5MB.'];

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) return ['status' => false, 'message' => 'Archivo no es una imagen.'];

        $validMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($imageInfo['mime'], $validMimes)) return ['status' => false, 'message' => 'Formato no permitido.'];

        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        // Datos anteriores para log
        $currentUser = $this->getUserProfile($userId);
        $oldUrl = $currentUser['profile_picture_url'] ?? '';

        // Nombre archivo
        $filename = $userId . '_' . time() . '.png';
        $destinationPath = $targetDir . $filename;

        // Procesar con GD (Limpieza de código malicioso)
        if ($this->processImageWithGD($file['tmp_name'], $destinationPath, $imageInfo['mime'])) {
            $this->removeOldAvatar($userId, 'uploads');

            $newUrl = $targetWeb . $filename;
            $this->updateDbAvatar($userId, $newUrl);

            // Log
            $this->logChange($userId, 'update_avatar', $oldUrl, $newUrl);
            
            return ['status' => true, 'message' => 'Foto actualizada.', 'url' => $newUrl];
        } else {
            return ['status' => false, 'message' => 'Error al procesar la imagen.'];
        }
    }

    // --- ELIMINAR FOTO (CON RATE LIMIT) ---
    public function deleteAvatar($userId) {
        // 1. VALIDAR LÍMITE (Compartido con update_avatar)
        if (!$this->checkRateLimit($userId, ['update_avatar', 'delete_avatar'], 3, '24 hours')) {
            return ['status' => false, 'message' => 'Límite alcanzado: Solo puedes cambiar tu foto 3 veces al día.'];
        }

        $currentUser = $this->getUserProfile($userId);
        $oldUrl = $currentUser['profile_picture_url'] ?? '';
        
        $this->removeOldAvatar($userId, 'uploads');

        // Generar Default
        $targetDir = $this->baseDir . 'defaults/';
        $targetWeb = $this->webPath . 'defaults/';
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        $filename = 'default_' . $userId . '_' . time() . '.png';
        $apiUrl = "https://ui-avatars.com/api/?name=" . urlencode($currentUser['username']) . "&background=random&color=fff&size=128";
        
        $content = @file_get_contents($apiUrl);
        if ($content) {
            file_put_contents($targetDir . $filename, $content);
            $newUrl = $targetWeb . $filename;
        } else {
            $newUrl = $this->webPath . 'default-user.png'; 
        }

        $this->updateDbAvatar($userId, $newUrl);

        // Log
        $this->logChange($userId, 'delete_avatar', $oldUrl, $newUrl);

        return ['status' => true, 'message' => 'Foto eliminada.', 'url' => $newUrl];
    }

    // =========================================================
    // HELPER: RATE LIMITING
    // =========================================================
    /**
     * Verifica si el usuario ha superado el número de cambios permitidos en un periodo.
     * @param int $userId ID del usuario.
     * @param string|array $changeTypes Tipo(s) de cambio a verificar en la tabla profile_changes.
     * @param int $maxChanges Número máximo permitido.
     * @param string $timeString Cadena de tiempo para strtotime (ej: "24 hours", "7 days").
     * @return bool True si puede realizar el cambio, False si está bloqueado.
     */
    private function checkRateLimit($userId, $changeTypes, $maxChanges, $timeString) {
        if (!is_array($changeTypes)) $changeTypes = [$changeTypes];

        // Calculamos la fecha de corte restando el tiempo actual
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$timeString"));

        // Preparamos los placeholders para el IN (?,?,?)
        $inQuery = implode(',', array_fill(0, count($changeTypes), '?'));

        $sql = "SELECT COUNT(*) FROM profile_changes 
                WHERE user_id = ? 
                AND change_type IN ($inQuery) 
                AND created_at > ?";
        
        // Parámetros: ID usuario + Tipos de cambio + Fecha de corte
        $params = array_merge([$userId], $changeTypes, [$cutoffDate]);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();

        // Si el conteo es MENOR al máximo, permitimos (True). Si es igual o mayor, bloqueamos (False).
        return $count < $maxChanges;
    }

    // =========================================================
    // HELPER: GD SECURITY
    // =========================================================
    private function processImageWithGD($sourcePath, $targetPath, $mimeType) {
        $srcImg = null;
        switch ($mimeType) {
            case 'image/jpeg': $srcImg = imagecreatefromjpeg($sourcePath); break;
            case 'image/png':  $srcImg = imagecreatefrompng($sourcePath); break;
            case 'image/gif':  $srcImg = imagecreatefromgif($sourcePath); break;
            case 'image/webp': $srcImg = imagecreatefromwebp($sourcePath); break;
        }
        if (!$srcImg) return false;

        $width = imagesx($srcImg);
        $height = imagesy($srcImg);
        $newImg = imagecreatetruecolor($width, $height);

        // Transparencia
        imagealphablending($newImg, false);
        imagesavealpha($newImg, true);
        $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
        imagefilledrectangle($newImg, 0, 0, $width, $height, $transparent);

        imagecopyresampled($newImg, $srcImg, 0, 0, 0, 0, $width, $height, $width, $height);
        
        // Guardar limpio
        $result = imagepng($newImg, $targetPath, 9);
        
        imagedestroy($srcImg);
        imagedestroy($newImg);
        return $result;
    }

    // =========================================================
    // OTROS HELPERS
    // =========================================================
    private function logChange($userId, $changeType, $oldValue, $newValue) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
            $sql = "INSERT INTO profile_changes (user_id, change_type, old_value, new_value, ip_address, user_agent, created_at) 
                    VALUES (:uid, :type, :old, :new, :ip, :ua, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':uid'=>$userId, ':type'=>$changeType, ':old'=>$oldValue, ':new'=>$newValue, ':ip'=>$ip, ':ua'=>$ua]);
        } catch (Exception $e) {}
    }

    private function removeOldAvatar($userId, $folderName) {
        $user = $this->getUserProfile($userId);
        $currentUrl = $user['profile_picture_url'];
        if (strpos($currentUrl, "/$folderName/") !== false) {
            $basename = basename($currentUrl);
            $path = $this->baseDir . $folderName . '/' . $basename;
            if (file_exists($path) && realpath($path) === $path) unlink($path);
            elseif (file_exists($path)) unlink($path);
        }
    }

    private function updateDbAvatar($userId, $url) {
        $stmt = $this->db->prepare("UPDATE users SET profile_picture_url = :url WHERE id = :id");
        $stmt->execute([':url' => $url, ':id' => $userId]);
        $_SESSION['user_pic'] = $url;
    }
}
?>