<?php
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/chat_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

// [RECOMENDADO] Desactivar impresión de errores en la salida para no romper JSON
ini_set('display_errors', 0); 

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

// --- Dependencias Globales ---
require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/i18n_server.php';

// --- Servicios de Chat ---
require_once '../includes/logic/chat/send_service.php';
require_once '../includes/logic/chat/history_service.php';
require_once '../includes/logic/chat/actions_service.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, "application/json") !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => translation('global.error_csrf')]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => translation('global.session_expired')]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $response = [];

    switch ($action) {
        case 'send_message':
            $response = ChatSendService::sendMessage($pdo, $redis, $userId, $data, $_FILES);
            break;

        case 'get_messages':
            $response = ChatHistoryService::getMessages($pdo, $redis, $userId, $data);
            break;

        case 'delete_conversation':
            $response = ChatHistoryService::deleteConversation($pdo, $redis, $userId, $data);
            break;

        case 'react_message':
            $response = ChatActionsService::reactMessage($pdo, $redis, $userId, $data);
            break;

        case 'edit_message':
            $response = ChatActionsService::editMessage($pdo, $redis, $userId, $data);
            break;

        case 'mark_as_read':
            $response = ChatActionsService::markAsRead($pdo, $redis, $userId, $data);
            break;

        case 'delete_message':
            $response = ChatActionsService::deleteMessage($pdo, $userId, $data);
            break;

        case 'report_message':
            $response = ChatActionsService::reportMessage($pdo, $userId, $data);
            break;

        default:
            throw new Exception(translation('global.action_invalid') ?? "Acción no válida");
    }

    echo json_encode($response);

} catch (Throwable $e) {
    error_log("Chat API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>