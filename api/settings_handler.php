<?php
// api/settings_handler.php

header('Content-Type: application/json');

// Cargar configuración y helpers
require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
// IMPORTANTE: Incluir utilidades compartidas
require_once __DIR__ . '/utils.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) session_start();

// ---------------------------------------------------------
// 1. BARRERA DE SEGURIDAD (Solo usuarios logueados)
// ---------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

// ---------------------------------------------------------
// 2. CARGAR TRADUCCIONES (Basado en preferencia de usuario)
// ---------------------------------------------------------
$lang = null;
try {
    $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lang = $stmt->fetchColumn();
} catch(Exception $e){}

if (!$lang) {
    $lang = detect_browser_language(); 
}
load_translations($lang);

// ---------------------------------------------------------
// 5. LÓGICA PRINCIPAL (HANDLERS)
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $input = $json;
    }

    // Validación CSRF
    $incomingToken = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($incomingToken) || empty($sessionToken) || !hash_equals($sessionToken, $incomingToken)) {
        sendJsonResponse('error', __('api.error.csrf'));
    }

    $action = $input['action'] ?? '';

    // --- A) ACTUALIZAR PERFIL (User/Email) ---
    if ($action === 'update_profile') {
        $newUsername = trim($input['username'] ?? '');
        $newEmail = trim($input['email'] ?? '');

        if (empty($newUsername) || empty($newEmail)) sendJsonResponse('error', __('api.error.missing_data'));
        if (strlen($newUsername) < 6) sendJsonResponse('error', __('api.error.username_short'));

        $emailVal = validateEmailRequirements($newEmail);
        if ($emailVal !== true) sendJsonResponse('error', $emailVal);

        // Verificar que no exista otro usuario con ese nombre/email
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmtCheck->execute([$newUsername, $newEmail, $userId]);
        
        if ($stmtCheck->rowCount() > 0) {
            sendJsonResponse('error', __('api.error.username_exists')); // O email_exists
        }

        $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        if ($updateStmt->execute([$newUsername, $newEmail, $userId])) {
            $_SESSION['username'] = $newUsername;
            sendJsonResponse('success', __('api.success.profile_updated'), null, ['username' => $newUsername, 'email' => $newEmail]);
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // --- B) ACTUALIZAR PREFERENCIAS ---
    if ($action === 'update_preferences') {
        $language = $input['language'] ?? null;
        $openLinks = isset($input['open_links_new_tab']) ? (int)$input['open_links_new_tab'] : null;

        $fields = [];
        $params = [];

        if ($language) {
            $allowedLangs = ['es-419', 'en-US', 'en-GB', 'fr-FR', 'pt-BR'];
            if (in_array($language, $allowedLangs)) {
                $fields[] = "language = ?";
                $params[] = $language;
            }
        }

        if ($openLinks !== null) {
            $fields[] = "open_links_new_tab = ?";
            $params[] = $openLinks; 
        }

        if (empty($fields)) {
            sendJsonResponse('success', "OK"); 
        }

        $params[] = $userId;
        $sql = "UPDATE user_preferences SET " . implode(', ', $fields) . " WHERE user_id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                sendJsonResponse('success', __('api.success.preferences_saved'));
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // --- C) SUBIR FOTO DE PERFIL ---
    if ($action === 'upload_profile_picture') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
             sendJsonResponse('error', __('error.load_content'));
        }

        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedTypes)) {
            sendJsonResponse('error', __('api.error.upload_format'));
        }
        
        if ($file['size'] > 2 * 1024 * 1024) {
            sendJsonResponse('error', __('api.error.upload_size'));
        }

        $uuid = $_SESSION['uuid'];
        // DIR_CUSTOM ahora viene de utils.php
        $targetFile = DIR_CUSTOM . $uuid . '.png';
        $src = null;

        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $src = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
        }

        $uploadSuccess = false;
        if ($src) {
            imagepng($src, $targetFile, 9);
            imagedestroy($src);
            $uploadSuccess = true;
        } else {
             if(move_uploaded_file($file['tmp_name'], $targetFile)) $uploadSuccess = true;
        }

        if ($uploadSuccess) {
            // Eliminar default si existe para evitar conflictos de caché visual
            $defaultFile = DIR_DEFAULT . $uuid . '.png';
            if (file_exists($defaultFile)) unlink($defaultFile);
            
            $url = $basePath . URL_BASE_AVATARS . 'custom/' . $uuid . '.png?v=' . time();
            sendJsonResponse('success', __('api.success.photo_updated'), null, ['url' => $url]);
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // --- D) ELIMINAR FOTO DE PERFIL ---
    if ($action === 'delete_profile_picture') {
         $uuid = $_SESSION['uuid'];
         $username = $_SESSION['username'];
         
         // Constantes desde utils.php
         $customFile = DIR_CUSTOM . $uuid . '.png';
         if (file_exists($customFile)) unlink($customFile);
         
         $defaultFile = DIR_DEFAULT . $uuid . '.png';
         if (file_exists($defaultFile)) unlink($defaultFile);

         // Regenerar avatar por defecto (función desde utils.php)
         ensureDefaultAvatarExists($uuid, $username);

         $defaultUrl = $basePath . URL_BASE_AVATARS . 'default/' . $uuid . '.png?v=' . time();
         sendJsonResponse('success', __('api.success.photo_deleted'), null, ['url' => $defaultUrl]);
    }

    sendJsonResponse('error', "Action invalid (Settings Handler)");
}
?>