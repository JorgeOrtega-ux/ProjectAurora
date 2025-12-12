<?php
// api/admin_handler.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
require_once __DIR__ . '/utils.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar Admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => __('api.error.no_auth')]);
    exit;
}
$allowedRoles = ['founder', 'administrator'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
    echo json_encode(['status' => 'error', 'message' => __('global.access_denied')]);
    exit;
}

// Cargar idioma
$userId = $_SESSION['user_id'];
$lang = null;
try {
    $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lang = $stmt->fetchColumn();
} catch(Exception $e){}
if (!$lang) $lang = detect_browser_language(); 
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
    
    if ($action === 'get_server_config') {
        try {
            $stmt = $pdo->query("SELECT maintenance_mode, allow_registrations FROM server_config WHERE id=1");
            $data = $stmt->fetch();
            if(!$data) {
                $pdo->exec("INSERT INTO server_config (id, maintenance_mode, allow_registrations) VALUES (1, 0, 1)");
                $data = ['maintenance_mode' => 0, 'allow_registrations' => 1];
            }
            sendJsonResponse('success', 'OK', null, $data);
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    if ($action === 'update_server_config') {
        $maintenance = isset($input['maintenance_mode']) ? (int)$input['maintenance_mode'] : 0;
        $registrations = isset($input['allow_registrations']) ? (int)$input['allow_registrations'] : 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE server_config SET maintenance_mode = ?, allow_registrations = ? WHERE id = 1");
            if ($stmt->execute([$maintenance, $registrations])) {
                logSecurityEvent($pdo, "uid_".$userId, "server_config_update: m=$maintenance, r=$registrations");
                sendJsonResponse('success', __('api.success.preferences_saved'));
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }
}
?>