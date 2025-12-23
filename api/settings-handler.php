<?php
// api/settings-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../includes/libs/TOTP.php'; 

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

// Helper simple para parsear User Agent
function parseUserAgent($ua) {
    $platform = 'Desconocido';
    $browser  = 'Desconocido';
    
    // Plataforma
    if (preg_match('/windows|win32/i', $ua)) $platform = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'Mac OS';
    elseif (preg_match('/linux/i', $ua)) $platform = 'Linux';
    elseif (preg_match('/android/i', $ua)) $platform = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $platform = 'iOS';

    // Navegador
    if (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Opera|OPR/i', $ua)) $browser = 'Opera';
    elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';

    return ['platform' => $platform, 'browser' => $browser];
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

// =========================================================
//  NUEVA LÓGICA: 2FA (TWO FACTOR AUTH)
// =========================================================

} elseif ($action === 'init_2fa') {
    // 1. Generar secreto temporal
    $secret = TOTP::createSecret();
    $_SESSION['temp_2fa_secret'] = $secret;

    // 2. Generar URL de QR
    $username = $_SESSION['username'] ?? 'User';
    // Genera URL para gráfico QR
    $qrUrl = TOTP::getQRCodeUrl($username, $secret, 'ProjectAurora');

    jsonResponse(true, 'Escanea el código', ['qr_url' => $qrUrl, 'secret' => $secret]);

} elseif ($action === 'enable_2fa') {
    $code = $_POST['code'] ?? '';
    
    if (!isset($_SESSION['temp_2fa_secret'])) {
        jsonResponse(false, 'La sesión de configuración ha expirado. Recarga.');
    }

    $secret = $_SESSION['temp_2fa_secret'];

    // 1. Verificar el código TOTP
    if (TOTP::verifyCode($secret, $code)) {
        
        // 2. Generar códigos de recuperación
        $recoveryCodes = [];
        for($i=0; $i<8; $i++) {
            $recoveryCodes[] = bin2hex(random_bytes(4)); // Ej: a1b2c3d4
        }
        $jsonCodes = json_encode($recoveryCodes);

        // 3. Guardar en BD
        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_recovery_codes = ? WHERE id = ?");
        
        if ($stmt->execute([$secret, $jsonCodes, $userId])) {
            unset($_SESSION['temp_2fa_secret']);
            // === ACTUALIZAR SESIÓN ===
            $_SESSION['two_factor_enabled'] = 1; 
            jsonResponse(true, '2FA Activado correctamente.', ['recovery_codes' => $recoveryCodes]);
        } else {
            jsonResponse(false, 'Error al guardar en base de datos.');
        }

    } else {
        jsonResponse(false, 'Código incorrecto. Intenta de nuevo.');
    }

// === NUEVO BLOQUE: DESACTIVAR 2FA ===
} elseif ($action === 'disable_2fa') {
    
    $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_recovery_codes = NULL WHERE id = ?");
    
    if ($stmt->execute([$userId])) {
        $_SESSION['two_factor_enabled'] = 0;
        jsonResponse(true, '2FA desactivado correctamente.');
    } else {
        jsonResponse(false, 'Error al desactivar en la base de datos.');
    }

// =========================================================
//  GESTIÓN DE DISPOSITIVOS (NUEVO)
// =========================================================

} elseif ($action === 'get_sessions') {
    $stmt = $pdo->prepare("SELECT id, selector, ip_address, user_agent, created_at FROM user_auth_tokens WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $sessions = $stmt->fetchAll();

    // Identificar sesión actual por Cookie
    $currentSelector = '';
    if (isset($_COOKIE['auth_persistence_token'])) {
        $parts = explode(':', $_COOKIE['auth_persistence_token']);
        if (count($parts) === 2) $currentSelector = $parts[0];
    }

    $formatted = [];
    foreach($sessions as $s) {
        $info = parseUserAgent($s['user_agent'] ?? '');
        $isCurrent = ($s['selector'] === $currentSelector);
        
        $formatted[] = [
            'id' => $s['id'],
            'ip' => $s['ip_address'] ?? 'Desconocida',
            'platform' => $info['platform'],
            'browser' => $info['browser'],
            'created_at' => $s['created_at'],
            'is_current' => $isCurrent
        ];
    }

    jsonResponse(true, 'Lista de sesiones', ['sessions' => $formatted]);

} elseif ($action === 'revoke_session') {
    $tokenId = $_POST['token_id'] ?? '';
    
    if(!$tokenId) jsonResponse(false, 'ID no válido');

    $stmt = $pdo->prepare("DELETE FROM user_auth_tokens WHERE id = ? AND user_id = ?");
    $stmt->execute([$tokenId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        jsonResponse(true, 'Sesión cerrada exitosamente.');
    } else {
        jsonResponse(false, 'No se pudo cerrar la sesión.');
    }

} elseif ($action === 'revoke_all_sessions') {
    // Borramos todas
    $stmt = $pdo->prepare("DELETE FROM user_auth_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Al borrar todas, borramos también la actual del usuario, 
    // lo que provocará logout en la siguiente petición.
    // Opcionalmente podríamos mantener la actual:
    // DELETE FROM ... WHERE user_id = ? AND selector != ?
    
    // Pero "Cerrar todas las sesiones" suele implicar logout global.
    setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    
    jsonResponse(true, 'Todas las sesiones han sido cerradas. Serás redirigido.', ['logout' => true]);
}

jsonResponse(false, 'Acción no reconocida.');
?>