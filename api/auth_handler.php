<?php
// api/auth_handler.php
header('Content-Type: application/json');

// 1. Dependencias
require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/../GoogleAuthenticator.php'; 

// 2. Servicios
require_once __DIR__ . '/services/auth_service.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? 0;

$lang = null;
if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $lang = $stmt->fetchColumn();
    } catch(Exception $e){}
}
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
    $response = ['status' => 'error', 'message' => 'Action invalid (Auth Handler)'];

    // --- DISPATCHER ---
    
    switch ($action) {
        case 'login':
            $response = handle_login($pdo, trim($input['email'] ?? ''), $input['password'] ?? '');
            break;

        case 'register_step_1':
            $response = handle_register_step_1($pdo, trim($input['email'] ?? ''), $input['password'] ?? '');
            break;

        case 'register_step_2':
            $response = handle_register_step_2($pdo, trim($input['username'] ?? ''));
            break;

        case 'resend_verification_code':
            $response = handle_resend_verification_code($pdo);
            break;

        case 'verify_code':
            $response = handle_verify_code_create_account($pdo, trim($input['code'] ?? ''));
            break;

        case 'verify_2fa_login':
            $response = handle_verify_2fa_login($pdo, trim($input['code'] ?? ''));
            break;
        
        case 'request_password_reset':
            $response = handle_request_password_reset($pdo, trim($input['email'] ?? ''));
            break;
        
        case 'reset_password':
            $response = handle_reset_password($pdo, $input['token'] ?? '', $input['password'] ?? '');
            break;

        case 'logout':
            $response = handle_logout($pdo);
            break;
    }

    // Salida final
    echo json_encode($response);
    exit;
}
?>