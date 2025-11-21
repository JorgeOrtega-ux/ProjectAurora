<?php
// api/notifications_handler.php

// Configuración de logs específica para notificaciones
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/notifications_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/database.php';
require_once '../config/utilities.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

// Validación CSRF
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

// Validación de Sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada.']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    // --- OBTENER NOTIFICACIONES ---
    if ($action === 'get_notifications') {
        // Obtenemos notificaciones y datos del remitente (incluyendo rol)
        $sql = "SELECT n.*, u.avatar as sender_avatar, u.role as sender_role
                FROM notifications n 
                LEFT JOIN users u ON n.related_id = u.id
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contar las no leídas
        $sqlCount = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([$currentUserId]);
        $unreadCount = $stmtCount->fetchColumn();

        $response = [
            'success' => true, 
            'notifications' => $notifs,
            'unread_count' => (int)$unreadCount
        ];

    // --- MARCAR TODAS LEÍDAS ---
    } elseif ($action === 'mark_read_all') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$currentUserId]);
        $response = ['success' => true, 'message' => 'Marcadas como leídas.'];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>