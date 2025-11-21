<?php
// api/social_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/social_error.log';
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

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada.']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    $uSt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
    $uSt->execute([$currentUserId]);
    $currentUserData = $uSt->fetch();
    $myUsername = $currentUserData['username'];
    $myAvatar = $currentUserData['avatar'];

    // --- ENVIAR SOLICITUD ---
    if ($action === 'send_request') {
        
        // [SEGURIDAD] Rate Limiting: Max 10 solicitudes por minuto
        if (checkActionRateLimit($pdo, $currentUserId, 'friend_request_limit', 10, 1)) {
            throw new Exception("Estás enviando solicitudes muy rápido. Por favor espera un momento.");
        }
        logSecurityAction($pdo, $currentUserId, 'friend_request_limit');

        $targetId = (int)($data['target_id'] ?? 0);
        if ($targetId === 0 || $targetId === $currentUserId) throw new Exception("Usuario inválido.");

        $sql = "SELECT id FROM friendships 
                WHERE (sender_id = ? AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $targetId, $targetId, $currentUserId]);
        
        if ($stmt->rowCount() > 0) throw new Exception("Ya existe una solicitud o amistad.");

        $stmt = $pdo->prepare("INSERT INTO friendships (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$currentUserId, $targetId]);

        // Notificación
        $msg = "<strong>$myUsername</strong> quiere ser tu amigo.";
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_request', ?, ?)")
            ->execute([$targetId, $msg, $currentUserId]);

        send_live_notification($targetId, 'friend_request', [
            'message' => "<strong>$myUsername</strong> te ha enviado una solicitud.",
            'sender_id' => $currentUserId,
            'sender_username' => $myUsername,
            'sender_avatar' => $myAvatar
        ]);

        $response = ['success' => true, 'message' => 'Solicitud enviada.'];

    // --- CANCELAR SOLICITUD ---
    } elseif ($action === 'cancel_request') {
        $targetId = (int)($data['target_id'] ?? 0);

        $sql = "DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $targetId]);

        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
                ->execute([$targetId, $currentUserId]);

            send_live_notification($targetId, 'request_cancelled', [
                'sender_id' => $currentUserId
            ]);

            $response = ['success' => true, 'message' => 'Solicitud cancelada.'];
        } else {
            throw new Exception("No se pudo cancelar.");
        }

    // --- ACEPTAR SOLICITUD ---
    } elseif ($action === 'accept_request') {
        $senderId = (int)($data['sender_id'] ?? 0);

        $sql = "UPDATE friendships SET status = 'accepted' 
                WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        if ($stmt->rowCount() > 0) {
            // 1. Eliminar notificación de solicitud pendiente
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
                ->execute([$currentUserId, $senderId]);

            // 2. Notificar al remitente
            $msg = "<strong>$myUsername</strong> aceptó tu solicitud.";
            $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_accepted', ?, ?)")
                ->execute([$senderId, $msg, $currentUserId]);

            send_live_notification($senderId, 'friend_accepted', [
                'message' => "<strong>$myUsername</strong> aceptó tu solicitud.",
                'accepter_id' => $currentUserId,
                'accepter_username' => $myUsername
            ]);

            $response = ['success' => true, 'message' => 'Solicitud aceptada.'];
        } else {
            throw new Exception("Error al aceptar.");
        }

    // --- RECHAZAR SOLICITUD ---
    } elseif ($action === 'decline_request') {
        $senderId = (int)($data['sender_id'] ?? 0);

        $sql = "DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        // Eliminar notificación
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
            ->execute([$currentUserId, $senderId]);
        
        send_live_notification($senderId, 'request_declined', ['sender_id' => $currentUserId]);

        $response = ['success' => true, 'message' => 'Solicitud rechazada.'];

    // --- ELIMINAR AMIGO ---
    } elseif ($action === 'remove_friend') {
        $friendId = (int)($data['target_id'] ?? 0);
        
        $sql = "DELETE FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $friendId, $friendId, $currentUserId]);

        $pdo->prepare("DELETE FROM notifications 
                       WHERE user_id = ? AND related_id = ? 
                       AND type IN ('friend_request', 'friend_accepted')")
            ->execute([$currentUserId, $friendId]);

        send_live_notification($friendId, 'friend_removed', ['sender_id' => $currentUserId]);

        $response = ['success' => true, 'message' => 'Amigo eliminado.'];

    // --- OBTENER NOTIFICACIONES (MODIFICADO) ---
    } elseif ($action === 'get_notifications') {
        // [CORRECCIÓN]: Agregamos u.role as sender_role para obtener el rol del remitente
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