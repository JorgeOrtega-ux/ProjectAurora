<?php
// api/admin_handler.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
require_once __DIR__ . '/utils.php';

// Cargar el nuevo servicio
require_once __DIR__ . '/services/admin_service.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Validar Autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => __('api.error.no_auth')]);
    exit;
}

// 2. Validar Roles (Solo Admin/Founder)
$allowedRoles = ['founder', 'administrator'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
    echo json_encode(['status' => 'error', 'message' => __('global.access_denied')]);
    exit;
}

// 3. Cargar idioma del usuario
$userId = $_SESSION['user_id'];
$lang = null;
try {
    $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lang = $stmt->fetchColumn();
} catch(Exception $e){}
if (!$lang) $lang = detect_browser_language(); 
load_translations($lang);

// 4. Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $input = $json;
    }

    // 5. Validar CSRF
    $incomingToken = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($incomingToken) || empty($sessionToken) || !hash_equals($sessionToken, $incomingToken)) {
        sendJsonResponse('error', __('api.error.csrf'));
    }

    $action = $input['action'] ?? '';
    $response = ['status' => 'error', 'message' => 'Action invalid (Admin Handler)'];
    
    // 6. Dispatcher usando AdminService
    switch ($action) {
        case 'get_dashboard_stats':
            $response = get_dashboard_stats($pdo);
            break;

        case 'get_server_config':
            $response = get_server_config_data($pdo);
            break;

        case 'update_server_config':
            $response = update_server_configuration($pdo, $userId, $input);
            break;
    }
    
    echo json_encode($response);
    exit;
}
?>