<?php
// api/settings-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';

header('Content-Type: application/json');

function jsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// 1. Verificar Autenticación
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Sesión expirada.');
}

// 2. Verificar CSRF (Seguridad)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        jsonResponse(false, 'Error de seguridad: Token inválido.');
    }
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Directorios
$baseDir = __DIR__ . '/../storage/profilePicture/';
$dirDefault = $baseDir . 'default/';
$dirCustom  = $baseDir . 'custom/';

// Asegurar que existan las carpetas
if (!is_dir($dirDefault)) mkdir($dirDefault, 0777, true);
if (!is_dir($dirCustom)) mkdir($dirCustom, 0777, true);

// Función auxiliar para borrar archivo anterior
function deleteOldAvatar($pdo, $userId, $currentPath) {
    if ($currentPath && file_exists(__DIR__ . '/../' . $currentPath)) {
        // Solo borramos si el archivo existe
        @unlink(__DIR__ . '/../' . $currentPath);
    }
}

// =========================================================
//  ACCIÓN: SUBIR FOTO (Upload / Change)
// =========================================================
if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'No se ha seleccionado una imagen válida.');
    }

    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedTypes)) {
        jsonResponse(false, 'Formato de imagen no permitido (solo JPG, PNG, WEBP, GIF).');
    }

    // Obtener info actual para borrar la vieja DESPUÉS de subir la nueva
    $stmt = $pdo->prepare("SELECT avatar_path, uuid FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    $oldPath = $currentUser['avatar_path'];

    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = $currentUser['uuid'] . '-' . time() . '.' . $extension;
    $targetPath = $dirCustom . $newFileName;
    $dbPath = 'storage/profilePicture/custom/' . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Actualizar BD
        $update = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        if ($update->execute([$dbPath, $userId])) {
            
            // Borrar la anterior (sea default o custom vieja)
            deleteOldAvatar($pdo, $userId, $oldPath);

            // Actualizar Sesión
            $_SESSION['avatar'] = $dbPath;

            jsonResponse(true, 'Foto de perfil actualizada.', [
                'new_src' => $dbPath, // El frontend puede necesitar prefijar esto
                'type' => 'custom'
            ]);
        } else {
            // Si falla BD, borrar la imagen subida para no dejar basura
            @unlink($targetPath);
            jsonResponse(false, 'Error al guardar en la base de datos.');
        }
    } else {
        jsonResponse(false, 'Error al mover el archivo al servidor.');
    }

// =========================================================
//  ACCIÓN: ELIMINAR FOTO (Volver a Default)
// =========================================================
} elseif ($action === 'delete_avatar') {
    
    // Obtener info actual
    $stmt = $pdo->prepare("SELECT avatar_path, username, uuid FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    $oldPath = $currentUser['avatar_path'];

    // Si ya está vacía o es default (por si acaso), no hacemos nada complejo, pero regeneramos
    // Generar nueva imagen Default con UI Avatars
    $firstLetter = substr($currentUser['username'], 0, 1);
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=random&color=fff&size=128&format=png";
    
    $newFileName = $currentUser['uuid'] . '-' . time() . '.png';
    $targetPath = $dirDefault . $newFileName;
    $dbPath = 'storage/profilePicture/default/' . $newFileName;

    $imageContent = @file_get_contents($avatarUrl);
    
    if ($imageContent !== false && file_put_contents($targetPath, $imageContent)) {
        // Actualizar BD
        $update = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        if ($update->execute([$dbPath, $userId])) {
            
            // Borrar la foto custom anterior
            deleteOldAvatar($pdo, $userId, $oldPath);

            // Actualizar Sesión
            $_SESSION['avatar'] = $dbPath;

            jsonResponse(true, 'Foto eliminada. Se ha generado una por defecto.', [
                'type' => 'default'
            ]);
        } else {
            jsonResponse(false, 'Error al actualizar base de datos.');
        }
    } else {
        jsonResponse(false, 'No se pudo generar el avatar por defecto.');
    }
}

jsonResponse(false, 'Acción no reconocida.');
?>