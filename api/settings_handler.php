<?php
// api/settings_handler.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/../GoogleAuthenticator.php';

// Servicios
require_once __DIR__ . '/services/profile_service.php';
require_once __DIR__ . '/services/security_service.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $input = $json;
    }

    $incomingToken = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($incomingToken) || empty($sessionToken) || !hash_equals($sessionToken, $incomingToken)) {
        sendJsonResponse('error', __('api.error.csrf'));
    }

    $action = $input['action'] ?? '';
    $response = ['status' => 'error', 'message' => 'Action invalid (Settings Handler)'];

    // --- DISPATCHER ---

    switch ($action) {
        
        // --- SERVICIO DE SEGURIDAD ---
        case 'verify_current_password':
            $response = verify_current_password_check($pdo, $userId, $input['password'] ?? '');
            break;

        case 'delete_account':
            $response = delete_user_account($pdo, $userId, $input['password'] ?? '');
            break;

        case 'get_active_sessions':
            $response = get_active_sessions_list($pdo, $userId);
            break;

        case 'revoke_session':
            $response = revoke_single_session($pdo, $userId, $input['session_db_id'] ?? 0);
            break;

        case 'revoke_all_sessions':
            $response = revoke_all_sessions($pdo, $userId, $input['password'] ?? '');
            break;

        case 'init_2fa':
            $response = init_2fa_setup($pdo, $userId, $input['current_password'] ?? '');
            break;

        case 'enable_2fa':
            $response = enable_2fa_confirm($pdo, $userId, trim($input['code'] ?? ''));
            break;

        case 'disable_2fa':
            $response = disable_2fa($pdo, $userId, $input['current_password'] ?? '');
            break;
        
        case 'update_password':
            $response = update_user_password($pdo, $userId, $input['current_password'] ?? '', $input['new_password'] ?? '');
            break;

        // --- SERVICIO DE PERFIL ---
        case 'update_profile':
            $response = update_profile_data($pdo, $userId, trim($input['username'] ?? ''), trim($input['email'] ?? ''));
            break;

        case 'update_preferences':
            $response = update_preferences($pdo, $userId, $input);
            break;

        case 'upload_profile_picture':
            $response = handle_upload_avatar($pdo, $userId, $_FILES['image'] ?? null);
            break;

        case 'delete_profile_picture':
            $response = handle_delete_avatar($pdo, $userId);
            break;

        // --- NUEVO: REPARACIÓN DE AVATAR ---
        case 'repair_avatar':
            $response = handle_repair_avatar($pdo, $userId);
            break;
    }

    echo json_encode($response);
    exit;
}
?>