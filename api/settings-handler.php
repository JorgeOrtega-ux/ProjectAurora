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

// 2. Verificar CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        jsonResponse(false, 'Error de seguridad: Token inválido (CSRF).');
    }
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Directorios para avatares
$baseDir = __DIR__ . '/../storage/profilePicture/';
$dirDefault = $baseDir . 'default/';
$dirCustom  = $baseDir . 'custom/';

if (!is_dir($dirDefault)) mkdir($dirDefault, 0777, true);
if (!is_dir($dirCustom)) mkdir($dirCustom, 0777, true);

function deleteOldAvatar($pdo, $userId, $currentPath) {
    if ($currentPath && file_exists(__DIR__ . '/../' . $currentPath)) {
        @unlink(__DIR__ . '/../' . $currentPath);
    }
}

// =========================================================
//  ACCIÓN: SUBIR FOTO
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
        jsonResponse(false, 'Formato de imagen no permitido.');
    }

    $stmt = $pdo->prepare("SELECT avatar_path, uuid FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    $oldPath = $currentUser['avatar_path'];

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = $currentUser['uuid'] . '-' . time() . '.' . $extension;
    $targetPath = $dirCustom . $newFileName;
    $dbPath = 'storage/profilePicture/custom/' . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $update = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        if ($update->execute([$dbPath, $userId])) {
            deleteOldAvatar($pdo, $userId, $oldPath);
            $_SESSION['avatar'] = $dbPath;
            jsonResponse(true, 'Foto de perfil actualizada.', ['new_src' => $dbPath, 'type' => 'custom']);
        } else {
            @unlink($targetPath);
            jsonResponse(false, 'Error al guardar en la base de datos.');
        }
    } else {
        jsonResponse(false, 'Error al mover el archivo al servidor.');
    }

// =========================================================
//  ACCIÓN: ELIMINAR FOTO
// =========================================================
} elseif ($action === 'delete_avatar') {
    $stmt = $pdo->prepare("SELECT avatar_path, username, uuid FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    $oldPath = $currentUser['avatar_path'];

    $firstLetter = substr($currentUser['username'], 0, 1);
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($firstLetter) . "&background=random&color=fff&size=128&format=png";
    
    $newFileName = $currentUser['uuid'] . '-' . time() . '.png';
    $targetPath = $dirDefault . $newFileName;
    $dbPath = 'storage/profilePicture/default/' . $newFileName;
    $imageContent = @file_get_contents($avatarUrl);
    
    if ($imageContent !== false && file_put_contents($targetPath, $imageContent)) {
        $update = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        if ($update->execute([$dbPath, $userId])) {
            deleteOldAvatar($pdo, $userId, $oldPath);
            $_SESSION['avatar'] = $dbPath;
            jsonResponse(true, 'Foto eliminada. Se ha generado una por defecto.', ['type' => 'default']);
        } else {
            jsonResponse(false, 'Error al actualizar base de datos.');
        }
    } else {
        jsonResponse(false, 'No se pudo generar el avatar por defecto.');
    }

// =========================================================
//  ACCIÓN: ACTUALIZAR PERFIL (Email / Username)
// =========================================================
} elseif ($action === 'update_profile') {
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');

    if (!in_array($field, ['username', 'email'])) {
        jsonResponse(false, 'Campo no válido.');
    }

    if (empty($value)) {
        jsonResponse(false, 'El campo no puede estar vacío.');
    }

    if ($field === 'email') {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Formato de correo inválido.');
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE $field = ? AND id != ?");
    $stmt->execute([$value, $userId]);
    if ($stmt->fetch()) {
        jsonResponse(false, "Este $field ya está en uso por otro usuario.");
    }

    try {
        $update = $pdo->prepare("UPDATE users SET $field = ? WHERE id = ?");
        $update->execute([$value, $userId]);
        $_SESSION[$field] = $value;
        jsonResponse(true, ucfirst($field) . ' actualizado correctamente.');
    } catch (Exception $e) {
        jsonResponse(false, 'Error al actualizar: ' . $e->getMessage());
    }

// =========================================================
//  ACCIÓN: VALIDAR CONTRASEÑA ACTUAL (SOLO VALIDACIÓN)
// =========================================================
} elseif ($action === 'validate_current_password') {
    $currentPass = $_POST['current_password'] ?? '';

    if (empty($currentPass)) {
        jsonResponse(false, 'Ingresa tu contraseña actual.');
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && password_verify($currentPass, $user['password'])) {
        jsonResponse(true, 'Contraseña correcta.');
    } else {
        jsonResponse(false, 'La contraseña actual es incorrecta.');
    }

// =========================================================
//  ACCIÓN: CAMBIAR CONTRASEÑA (FLUJO FINAL)
// =========================================================
} elseif ($action === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';

    if (empty($currentPass) || empty($newPass)) {
        jsonResponse(false, 'Faltan datos requeridos.');
    }
    if (strlen($newPass) < 6) {
        jsonResponse(false, 'La nueva contraseña debe tener al menos 6 caracteres.');
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPass, $user['password'])) {
        jsonResponse(false, 'La contraseña actual es incorrecta.');
    }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    try {
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$newHash, $userId]);
        jsonResponse(true, 'Contraseña actualizada exitosamente.');
    } catch (Exception $e) {
        jsonResponse(false, 'Error al guardar contraseña.');
    }

// =========================================================
//  ACCIÓN: ACTUALIZAR PREFERENCIAS
// =========================================================
} elseif ($action === 'update_preference') {
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';

    $allowedKeys = [
        'language' => 'language',
        'open_links_new_tab' => 'open_links_new_tab'
    ];

    if (!array_key_exists($key, $allowedKeys)) {
        jsonResponse(false, 'Preferencia no válida.');
    }

    $dbValue = $value;
    
    if ($key === 'language') {
        $allowedLangs = ['es-latam', 'es-mx', 'en-us', 'en-gb', 'fr-fr'];
        if (!in_array($value, $allowedLangs)) {
            jsonResponse(false, 'Idioma no soportado.');
        }
    } elseif ($key === 'open_links_new_tab') {
        $dbValue = ($value === 'true' || $value === '1') ? 1 : 0;
    }

    try {
        // === OPTIMIZACIÓN BACKEND: Evitar writes innecesarios ===
        $stmtCheck = $pdo->prepare("SELECT $key FROM user_preferences WHERE user_id = ?");
        $stmtCheck->execute([$userId]);
        $currentDbValue = $stmtCheck->fetchColumn();
        
        if ($currentDbValue !== false) {
            $val1 = $currentDbValue; 
            $val2 = $dbValue;
            
            if ($key === 'open_links_new_tab') {
                $val1 = (int)$val1;
                $val2 = (int)$val2;
            }

            if ($val1 === $val2) {
                jsonResponse(true, 'Preferencia actualizada (Sin cambios).');
            }
        }

        $sql = "INSERT INTO user_preferences (user_id, $key) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE $key = VALUES($key)";
            
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $dbValue]);

        if (!isset($_SESSION['preferences'])) { $_SESSION['preferences'] = []; }
        
        if ($key === 'open_links_new_tab') {
            $_SESSION['preferences'][$key] = (bool)$dbValue;
        } else {
            $_SESSION['preferences'][$key] = $dbValue;
        }

        jsonResponse(true, 'Preferencia guardada.');
    } catch (Exception $e) {
        jsonResponse(false, 'Error al guardar preferencia: ' . $e->getMessage());
    }
}

jsonResponse(false, 'Acción no reconocida.');
?>