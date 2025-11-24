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

$currentUserId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => translation('global.action_invalid')];

try {
    $uSt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $uSt->execute([$currentUserId]);
    $currentUserData = $uSt->fetch();
    $myUsername = $currentUserData['username'];
    $myProfilePic = $currentUserData['profile_picture'];

    if ($action === 'send_request') {
        if (checkActionRateLimit($pdo, $currentUserId, 'friend_request_limit', 10, 1)) {
            throw new Exception(translation('auth.errors.too_many_attempts'));
        }
        logSecurityAction($pdo, $currentUserId, 'friend_request_limit');

        $targetId = (int)($data['target_id'] ?? 0);
        if ($targetId === 0 || $targetId === $currentUserId) throw new Exception(translation('admin.error.user_not_exist'));

        $sql = "SELECT id FROM friendships 
                WHERE (sender_id = ? AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $targetId, $targetId, $currentUserId]);
        
        if ($stmt->rowCount() > 0) throw new Exception(translation('friends.error.exists') ?? 'Ya existe solicitud');

        $stmt = $pdo->prepare("INSERT INTO friendships (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$currentUserId, $targetId]);

        $msg = translation('notifications.friend_request', ['username' => $myUsername]);
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_request', ?, ?)")
            ->execute([$targetId, $msg, $currentUserId]);

        send_live_notification($targetId, 'friend_request', [
            'message' => $msg,
            'sender_id' => $currentUserId,
            'sender_username' => $myUsername,
            'sender_profile_picture' => $myProfilePic
        ]);

        $response = ['success' => true, 'message' => translation('notifications.request_sent')];

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

            $response = ['success' => true, 'message' => translation('notifications.request_cancelled')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'accept_request') {
        $senderId = (int)($data['sender_id'] ?? 0);

        $sql = "UPDATE friendships SET status = 'accepted' 
                WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
                ->execute([$currentUserId, $senderId]);

            $msg = translation('notifications.friend_accepted', ['username' => $myUsername]);
            $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_accepted', ?, ?)")
                ->execute([$senderId, $msg, $currentUserId]);

            send_live_notification($senderId, 'friend_accepted', [
                'message' => $msg,
                'accepter_id' => $currentUserId,
                'accepter_username' => $myUsername
            ]);

            $response = ['success' => true, 'message' => translation('notifications.now_friends')];
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'decline_request') {
        $senderId = (int)($data['sender_id'] ?? 0);

        $sql = "DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'")
            ->execute([$currentUserId, $senderId]);
        
        send_live_notification($senderId, 'request_declined', ['sender_id' => $currentUserId]);

        $response = ['success' => true, 'message' => translation('notifications.request_declined')];

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

        $response = ['success' => true, 'message' => translation('notifications.friend_removed')];

    } elseif ($action === 'get_notifications') {
        // [MODIFICADO] profile_picture alias
        $sql = "SELECT n.*, u.profile_picture as sender_profile_picture, u.role as sender_role
                FROM notifications n 
                LEFT JOIN users u ON n.related_id = u.id
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlCount = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([$currentUserId]);
        $unreadCount = $stmtCount->fetchColumn();

        $response = [
            'success' => true, 
            'notifications' => $notifs,
            'unread_count' => (int)$unreadCount
        ];

    } elseif ($action === 'mark_read_all') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$currentUserId]);
        $response = ['success' => true, 'message' => translation('header.mark_read')];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>