<?php
// api/settings_handler.php

// --- CONFIGURACIÓN DE LOGS ---
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

// VALIDACIÓN CSRF
$data = json_decode(file_get_contents('php://input'), true);
// Nota: Cuando se suben archivos (FormData), los datos vienen en $_POST y $_FILES, no en php://input
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

// --- FUNCIONES AUXILIARES (Locales para este handler) ---
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

try {
    
    // ==================================================================
    // ACTUALIZAR AVATAR (SUBIDA DE FOTO)
    // ==================================================================
    if ($action === 'update_avatar') {
        
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No se recibió ningún archivo o hubo un error en la subida.');
        }

        $file = $_FILES['avatar'];
        $maxSize = 2 * 1024 * 1024; // 2 MB
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        // 1. Validar Tamaño
        if ($file['size'] > $maxSize) {
            throw new Exception('La imagen supera el límite de 2MB.');
        }

        // 2. Validar Tipo MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Formato de archivo no permitido. Usa PNG, JPG, WEBP o GIF.');
        }

        // 3. Preparar Directorio
        $uploadDir = __DIR__ . '/../public/assets/uploads/avatars/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 4. Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!$extension) $extension = 'png';
        $newFileName = generate_uuid_v4() . '.' . $extension;
        $destination = $uploadDir . $newFileName;
        $dbPath = 'assets/uploads/avatars/' . $newFileName;

        // 5. Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Error al guardar la imagen en el servidor.');
        }

        // 6. Borrar avatar anterior (Limpieza)
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldAvatar = $stmt->fetchColumn();

        if ($oldAvatar && file_exists(__DIR__ . '/../public/' . $oldAvatar)) {
            // Opcional: Verificar si no es un avatar por defecto antes de borrar
            @unlink(__DIR__ . '/../public/' . $oldAvatar);
        }

        // 7. Actualizar BD
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt->execute([$dbPath, $userId])) {
            $_SESSION['user_avatar'] = $dbPath; // Actualizar sesión
            $response = [
                'success' => true, 
                'message' => 'Foto de perfil actualizada.',
                'avatar_url' => '/ProjectAurora/' . $dbPath
            ];
        } else {
            throw new Exception('Error al actualizar la base de datos.');
        }

    // ==================================================================
    // ELIMINAR AVATAR (GENERAR NUEVO POR DEFECTO)
    // ==================================================================
    } elseif ($action === 'remove_avatar') {
        
        // 1. Obtener nombre de usuario para generar el avatar
        $stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) throw new Exception('Usuario no encontrado.');

        $username = $user['username'];
        $color = get_random_hex_color();
        $uuid = generate_uuid_v4();
        
        // 2. Generar Avatar con UI-Avatars
        $apiUrl = "https://ui-avatars.com/api/?name={$username}&size=256&background={$color}&color=ffffff&bold=true&length=1";
        $newFileName = $uuid . '.png';
        $uploadDir = __DIR__ . '/../public/assets/uploads/avatars/';
        $destPath = $uploadDir . $newFileName;
        $dbPath = 'assets/uploads/avatars/' . $newFileName;

        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent === false) throw new Exception('No se pudo generar el avatar por defecto.');
        
        file_put_contents($destPath, $imageContent);

        // 3. Borrar avatar personalizado anterior
        if ($user['avatar'] && file_exists(__DIR__ . '/../public/' . $user['avatar'])) {
            @unlink(__DIR__ . '/../public/' . $user['avatar']);
        }

        // 4. Actualizar BD
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt->execute([$dbPath, $userId])) {
            $_SESSION['user_avatar'] = $dbPath;
            $response = [
                'success' => true, 
                'message' => 'Foto de perfil eliminada (restablecida).',
                'avatar_url' => '/ProjectAurora/' . $dbPath
            ];
        } else {
            throw new Exception('Error en base de datos.');
        }

    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>