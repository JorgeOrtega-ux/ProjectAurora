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

    public function uploadAvatar($userId, $file) {
        // 1. Verificación básica de errores de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error al subir la imagen. Código: ' . $file['error']];
        }

        // 2. Límite de 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'La imagen supera el peso máximo de 2MB.'];
        }

        // 3. Verificación de tipo MIME real
        $mime = mime_content_type($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        
        if (!in_array($mime, $allowedMimes)) {
            return ['success' => false, 'message' => 'El formato de imagen no es válido. Solo JPG, PNG, WEBP o GIF.'];
        }

        // 4. Limpieza de scripts usando GD Library (re-creando la imagen)
        $image = null;
        switch ($mime) {
            case 'image/jpeg': $image = @imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $image = @imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $image = @imagecreatefromwebp($file['tmp_name']); break;
            case 'image/gif':  $image = @imagecreatefromgif($file['tmp_name']); break;
        }

        if (!$image) {
            return ['success' => false, 'message' => 'El archivo de imagen está corrupto o tiene contenido malicioso.'];
        }

        // Configurar el directorio de subida segura
        $storageDir = __DIR__ . '/../../public/storage/profilePictures/uploaded/';
        if (!file_exists($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        // Nombre único seguro y estandarización a JPG (previene muchas vulnerabilidades de PNG/GIF)
        $filename = Utils::generateUUID() . '_' . time() . '.jpg';
        $filepath = $storageDir . $filename;
        $webPath = 'storage/profilePictures/uploaded/' . $filename;

        // Si la imagen original tiene transparencia, le ponemos fondo blanco
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

        // 5. Guardar la imagen limpia (Calidad 85 para optimización)
        imagejpeg($image, $filepath, 85);
        imagedestroy($image);

        // 6. Eliminar el avatar anterior del servidor si era de la carpeta 'uploaded'
        $stmt = $this->conn->prepare("SELECT avatar_path FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $oldAvatar = $stmt->fetchColumn();

        if ($oldAvatar && strpos($oldAvatar, 'uploaded/') !== false) {
            $oldFile = __DIR__ . '/../../public/' . $oldAvatar;
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        // 7. Actualizar base de datos y sesión
        $updStmt = $this->conn->prepare("UPDATE users SET avatar_path = :path WHERE id = :id");
        if ($updStmt->execute([':path' => $webPath, ':id' => $userId])) {
            $_SESSION['user_avatar'] = $webPath;

            return [
                'success' => true,
                'message' => 'Foto de perfil actualizada correctamente.',
                'avatar' => $webPath // <--- Se retorna relativo, sin /
            ];
        }

        return ['success' => false, 'message' => 'Error interno al actualizar la base de datos.'];
    }

    public function deleteAvatar($userId) {
        // 1. Obtener la info del usuario para recrear el avatar por defecto
        $stmt = $this->conn->prepare("SELECT uuid, nombre, avatar_path FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Usuario no encontrado.'];
        }

        // 2. Eliminar imagen físicamente si es custom
        if (strpos($user['avatar_path'], 'uploaded/') !== false) {
            $oldFile = __DIR__ . '/../../public/' . $user['avatar_path'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        // 3. Generar nueva imagen default (Ui-Avatars)
        $storageDir = __DIR__ . '/../../public/storage/profilePictures/default/';
        $webDir = 'storage/profilePictures/default/';
        $newWebPath = Utils::generateAndSaveAvatar($user['nombre'], $user['uuid'], $storageDir, $webDir);

        if (!$newWebPath) {
            return ['success' => false, 'message' => 'No se pudo generar el avatar por defecto.'];
        }

        // 4. Guardar y actualizar
        $updStmt = $this->conn->prepare("UPDATE users SET avatar_path = :path WHERE id = :id");
        if ($updStmt->execute([':path' => $newWebPath, ':id' => $userId])) {
            $_SESSION['user_avatar'] = $newWebPath;

            return [
                'success' => true,
                'message' => 'Foto de perfil eliminada. Se restauró a predeterminada.',
                'avatar' => $newWebPath // <--- Se retorna relativo
            ];
        }

        return ['success' => false, 'message' => 'Error al restaurar el avatar.'];
    }
}
?>