<?php
// api/chat_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/chat_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/i18n_server.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$data = json_decode(file_get_contents('php://input'), true);
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

    // --- OBTENER HISTORIAL DE MENSAJES ---
    if ($action === 'get_messages') {
        $uuid = $data['community_uuid'] ?? '';
        $limit = isset($data['limit']) ? (int)$data['limit'] : 50;
        
        if (empty($uuid)) throw new Exception("UUID requerido");

        // 1. Obtener ID de comunidad y verificar membresía
        $stmtC = $pdo->prepare("
            SELECT c.id 
            FROM communities c
            JOIN community_members cm ON c.id = cm.community_id
            WHERE c.uuid = ? AND cm.user_id = ?
        ");
        $stmtC->execute([$uuid, $userId]);
        $commId = $stmtC->fetchColumn();

        if (!$commId) {
            throw new Exception("No tienes acceso a este chat.");
        }

        // [NUEVO] Marcar como leído (Actualizar last_read_at)
        $stmtRead = $pdo->prepare("UPDATE community_members SET last_read_at = NOW() WHERE community_id = ? AND user_id = ?");
        $stmtRead->execute([$commId, $userId]);

        // 2. Obtener mensajes
        // [CORRECCIÓN AQUÍ]: Se agregaron alias (AS sender_...) para coincidir con el JS y el Socket
        $sql = "
            SELECT m.id, m.message, m.created_at, m.type,
                   u.id as sender_id, 
                   u.username as sender_username, 
                   u.profile_picture as sender_profile_picture, 
                   u.role as sender_role
            FROM community_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.community_id = ?
            ORDER BY m.created_at DESC
            LIMIT $limit
        ";
        
        $stmtM = $pdo->prepare($sql);
        $stmtM->execute([$commId]);
        $messages = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        // Invertir para mostrar cronológicamente (ASC) en el frontend
        $messages = array_reverse($messages);

        echo json_encode(['success' => true, 'messages' => $messages]);

    } else {
        throw new Exception(translation('global.action_invalid'));
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>