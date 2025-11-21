<?php
// api/settings_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/settings_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/database.php';
require_once '../config/utilities.php';

$data = json_decode(file_get_contents('php://input'), true);
// Soporte para FormData o JSON
$action = $_POST['action'] ?? $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada.']);
    exit;
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Acción no válida'];

// --- FUNCIONES AUXILIARES ---

function generate_uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_random_hex_color() {
    $colors = ['C84F4F', '4F7AC8', '8C4FC8', 'C87A4F', '4FC8C8'];
    return $colors[array_rand($colors)];
}

/**
 * Verifica si el usuario puede realizar el cambio basándose en el tiempo del último cambio.
 */
function check_cooldown($pdo, $userId, $type, $daysLimit) {
    $stmt = $pdo->prepare("SELECT changed_at FROM user_audit_logs 
                           WHERE user_id = ? AND change_type = ? 
                           ORDER BY changed_at DESC LIMIT 1");
    $stmt->execute([$userId, $type]);
    $lastChange = $stmt->fetchColumn();

    if ($lastChange) {
        $lastDate = new DateTime($lastChange);
        $now = new DateTime();
        $diff = $now->diff($lastDate);
        $daysPassed = $diff->days;

        if ($daysPassed < $daysLimit) {
            $remaining = $daysLimit - $daysPassed;
            // Si falta menos de 1 día (y el límite es 1 día), mostramos horas
            if ($daysLimit === 1 && $daysPassed < 1) {
                $hoursRemaining = 24 - $diff->h;
                throw new Exception("Debes esperar $hoursRemaining horas para volver a cambiar tu $type.");
            }
            $unit = ($remaining === 1) ? 'día' : 'días';
            throw new Exception("Debes esperar $remaining $unit para volver a cambiar tu $type.");
        }
    }
}

/**
 * Registra el cambio en la tabla de auditoría.
 */
function audit_log($pdo, $userId, $type, $oldValue, $newValue) {
    $ip = get_client_ip();
    $stmt = $pdo->prepare("INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip, changed_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $type, $oldValue, $newValue, $ip]);
}

try {
    
    // ==================================================================
    // ACTUALIZAR AVATAR (Con Cooldown)
    // ==================================================================
    if ($action === 'update_avatar') {
        
        // 1. Verificar Cooldown (1 día)
        check_cooldown($pdo, $userId, 'avatar', 1);

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No se recibió ningún archivo o hubo un error en la subida.');
        }

        $file = $_FILES['avatar'];
        $maxSize = 2 * 1024 * 1024; 
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if ($file['size'] > $maxSize) throw new Exception('La imagen supera el límite de 2MB.');

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) throw new Exception('Formato no permitido.');

        $uploadDir = __DIR__ . '/../public/assets/uploads/avatars/custom/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $newFileName = generate_uuid_v4() . '.' . $extension;
        $destination = $uploadDir . $newFileName;
        $dbPath = 'assets/uploads/avatars/custom/' . $newFileName; 

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Error al guardar la imagen en el servidor.');
        }

        // Obtener valor antiguo
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldAvatar = $stmt->fetchColumn();

        if ($oldAvatar && file_exists(__DIR__ . '/../public/' . $oldAvatar)) {
            if (strpos($oldAvatar, 'custom/') !== false) {
                @unlink(__DIR__ . '/../public/' . $oldAvatar);
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt->execute([$dbPath, $userId])) {
            $_SESSION['user_avatar'] = $dbPath;
            
            // Auditoría
            audit_log($pdo, $userId, 'avatar', $oldAvatar, $dbPath);

            $response = ['success' => true, 'message' => 'Foto de perfil actualizada.', 'avatar_url' => '/ProjectAurora/' . $dbPath];
        } else {
            throw new Exception('Error DB.');
        }

    // ==================================================================
    // ELIMINAR AVATAR (Sin Cooldown)
    // ==================================================================
    } elseif ($action === 'remove_avatar') {
        
        // NO verificamos cooldown aquí para permitir corregir errores.

        $stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) throw new Exception('Usuario no encontrado.');

        $oldAvatar = $user['avatar'];
        $username = $user['username'];
        
        // Generar default
        $color = get_random_hex_color();
        $uuid = generate_uuid_v4();
        $apiUrl = "https://ui-avatars.com/api/?name={$username}&size=256&background={$color}&color=ffffff&bold=true&length=1";
        $newFileName = $uuid . '.png';
        $uploadDir = __DIR__ . '/../public/assets/uploads/avatars/default/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $destPath = $uploadDir . $newFileName;
        $dbPath = 'assets/uploads/avatars/default/' . $newFileName;

        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) file_put_contents($destPath, $imageContent);

        if ($oldAvatar && file_exists(__DIR__ . '/../public/' . $oldAvatar)) {
             if (strpos($oldAvatar, 'custom/') !== false) {
                @unlink(__DIR__ . '/../public/' . $oldAvatar);
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt->execute([$dbPath, $userId])) {
            $_SESSION['user_avatar'] = $dbPath;

            // Auditoría (Esto reiniciará el contador para la próxima subida)
            audit_log($pdo, $userId, 'avatar', $oldAvatar, $dbPath);

            $response = ['success' => true, 'message' => 'Avatar restablecido.', 'avatar_url' => '/ProjectAurora/' . $dbPath];
        } else {
            throw new Exception('Error DB.');
        }

    // ==================================================================
    // ACTUALIZAR USERNAME (Con Cooldown 12 días)
    // ==================================================================
    } elseif ($action === 'update_username') {
        
        check_cooldown($pdo, $userId, 'username', 12);

        $newUsername = trim($data['username'] ?? '');
        if (strlen($newUsername) < 8 || strlen($newUsername) > 32) throw new Exception('El usuario debe tener entre 8 y 32 caracteres.');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) throw new Exception('Solo se permiten letras, números y guiones bajos.');

        $stmtGet = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtGet->execute([$userId]);
        $oldUsername = $stmtGet->fetchColumn();

        if ($oldUsername === $newUsername) throw new Exception('El nombre de usuario es igual al actual.');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$newUsername, $userId]);
        if ($stmt->rowCount() > 0) throw new Exception('Este nombre de usuario ya está en uso.');

        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        if ($stmt->execute([$newUsername, $userId])) {
            
            audit_log($pdo, $userId, 'username', $oldUsername, $newUsername);

            $response = ['success' => true, 'message' => 'Nombre de usuario actualizado.', 'new_username' => $newUsername];
        } else {
            throw new Exception('Error al actualizar la base de datos.');
        }

    // ==================================================================
    // ACTUALIZAR EMAIL (Con Cooldown 12 días)
    // ==================================================================
    } elseif ($action === 'update_email') {
        
        check_cooldown($pdo, $userId, 'email', 12);

        $newEmail = strtolower(trim($data['email'] ?? ''));
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) throw new Exception('Formato de correo inválido.');
        if (!preg_match('/^[^@\s]+@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i', $newEmail)) throw new Exception('Dominio no permitido.');

        $stmtGet = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmtGet->execute([$userId]);
        $oldEmail = $stmtGet->fetchColumn();

        if ($oldEmail === $newEmail) throw new Exception('El correo es igual al actual.');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->rowCount() > 0) throw new Exception('Este correo electrónico ya está registrado.');

        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        if ($stmt->execute([$newEmail, $userId])) {

            audit_log($pdo, $userId, 'email', $oldEmail, $newEmail);

            $response = ['success' => true, 'message' => 'Correo electrónico actualizado.', 'new_email' => $newEmail];
        } else {
            throw new Exception('Error al actualizar el correo.');
        }

    // ==================================================================
    // PREFERENCIAS (Rate Limit Técnico) - MENSAJE UNIFICADO
    // ==================================================================
    } elseif ($action === 'update_usage') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) {
            throw new Exception("No se pudo completar la solicitud. Por favor, inténtalo más tarde.");
        }
        logSecurityAction($pdo, $userId, 'pref_update_limit');

        $usage = $data['usage'] ?? 'personal';
        $allowed = ['personal', 'student', 'teacher', 'small_business', 'large_business'];
        
        if (!in_array($usage, $allowed)) throw new Exception("Valor de uso no válido.");

        $sql = "INSERT INTO user_preferences (user_id, usage_intent) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE usage_intent = VALUES(usage_intent)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$userId, $usage])) {
            // [CAMBIO] Mensaje genérico
            $response = ['success' => true, 'message' => 'Preferencia actualizada.'];
        } else {
            throw new Exception("Error actualizando preferencia.");
        }

    } elseif ($action === 'update_language') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) {
            throw new Exception("No se pudo completar la solicitud. Por favor, inténtalo más tarde.");
        }
        logSecurityAction($pdo, $userId, 'pref_update_limit');

        $lang = $data['language'] ?? 'en-us';
        $allowed = ['es-latam', 'es-mx', 'en-us', 'en-gb'];

        if (!in_array($lang, $allowed)) throw new Exception("Idioma no soportado.");

        $sql = "INSERT INTO user_preferences (user_id, language) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE language = VALUES(language)";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$userId, $lang])) {
            $_SESSION['user_lang'] = $lang;

            // [CAMBIO] Mensaje genérico
            $response = ['success' => true, 'message' => 'Preferencia actualizada.'];
        } else {
            throw new Exception("Error actualizando idioma.");
        }

    } elseif ($action === 'update_boolean_preference') {
        if (checkActionRateLimit($pdo, $userId, 'pref_update_limit', 10, 1)) {
            throw new Exception("No se pudo completar la solicitud. Por favor, inténtalo más tarde.");
        }
        logSecurityAction($pdo, $userId, 'pref_update_limit');

        $field = $data['field'] ?? '';
        $value = isset($data['value']) && $data['value'] ? 1 : 0;

        $allowedFields = ['open_links_in_new_tab']; 

        if (!in_array($field, $allowedFields)) {
            throw new Exception("Campo de preferencia no válido.");
        }

        $sql = "INSERT INTO user_preferences (user_id, $field) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE $field = VALUES($field)";
        
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$userId, $value])) {
            // [CAMBIO] Mensaje genérico (ya lo tenía, pero aseguramos consistencia)
            $response = ['success' => true, 'message' => 'Preferencia actualizada.'];
        } else {
            throw new Exception("Error actualizando preferencia.");
        }
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>