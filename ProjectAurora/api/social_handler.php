<?php
// api/social_handler.php

// --- CONFIGURACIÓN BÁSICA ---
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/social_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/database.php';
require_once '../config/utilities.php';

// VALIDACIÓN CSRF
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

// VALIDAR SESIÓN
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada.']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Acción no válida'];

try {

    // ==================================================================
    // ENVIAR SOLICITUD DE AMISTAD
    // ==================================================================
    if ($action === 'send_request') {
        $targetId = (int)($data['target_id'] ?? 0);

        if ($targetId === 0 || $targetId === $currentUserId) throw new Exception("Usuario inválido.");

        // Verificar si ya existe relación
        $sql = "SELECT id FROM friendships 
                WHERE (sender_id = ? AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $targetId, $targetId, $currentUserId]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Ya existe una solicitud o amistad.");
        }

        // Insertar solicitud
        $stmt = $pdo->prepare("INSERT INTO friendships (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$currentUserId, $targetId]);

        // Notificación
        $myUsername = $_SESSION['user_username'] ?? 'Alguien';
        if (!isset($_SESSION['user_username'])) {
            $uSt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $uSt->execute([$currentUserId]);
            $myUsername = $uSt->fetchColumn();
        }

        $msg = "<strong>$myUsername</strong> quiere ser tu amigo.";
        $notifSql = "INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_request', ?, ?)";
        $pdo->prepare($notifSql)->execute([$targetId, $msg, $currentUserId]);

        $response = ['success' => true, 'message' => 'Solicitud enviada.'];

    // ==================================================================
    // CANCELAR SOLICITUD (EL SENDER SE ARREPIENTE) [NUEVO]
    // ==================================================================
    } elseif ($action === 'cancel_request') {
        $targetId = (int)($data['target_id'] ?? 0);

        // Borrar la amistad pendiente donde YO soy el sender
        $sql = "DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $targetId]);

        if ($stmt->rowCount() > 0) {
            // Borrar también la notificación que le envié al otro usuario
            // Buscamos notificaciones de tipo friend_request donde el receptor es targetId y el related es currentUserId
            $delNotif = "DELETE FROM notifications WHERE user_id = ? AND related_id = ? AND type = 'friend_request'";
            $pdo->prepare($delNotif)->execute([$targetId, $currentUserId]);

            $response = ['success' => true, 'message' => 'Solicitud cancelada.'];
        } else {
            throw new Exception("No se encontró la solicitud o ya fue aceptada.");
        }

    // ==================================================================
    // ACEPTAR SOLICITUD
    // ==================================================================
    } elseif ($action === 'accept_request') {
        $senderId = (int)($data['sender_id'] ?? 0);
        $notifId = (int)($data['notification_id'] ?? 0);

        $sql = "UPDATE friendships SET status = 'accepted' 
                WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        if ($stmt->rowCount() > 0) {
            if ($notifId > 0) {
                $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$notifId, $currentUserId]);
            }

            // Notificar de vuelta
            $myUsername = $_SESSION['user_username'] ?? 'Usuario'; 
             if ($myUsername === 'Usuario') {
                $uSt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $uSt->execute([$currentUserId]);
                $myUsername = $uSt->fetchColumn();
            }

            $msg = "<strong>$myUsername</strong> aceptó tu solicitud de amistad.";
            $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'friend_accepted', ?, ?)")
                ->execute([$senderId, $msg, $currentUserId]);

            $response = ['success' => true, 'message' => 'Solicitud aceptada.'];
        } else {
            throw new Exception("No se pudo aceptar la solicitud.");
        }

    // ==================================================================
    // RECHAZAR SOLICITUD (DESDE EL RECEPTOR)
    // ==================================================================
    } elseif ($action === 'decline_request') {
        $senderId = (int)($data['sender_id'] ?? 0);
        $notifId = (int)($data['notification_id'] ?? 0);

        $sql = "DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$senderId, $currentUserId]);

        if ($notifId > 0) {
            $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$notifId, $currentUserId]);
        }

        $response = ['success' => true, 'message' => 'Solicitud rechazada.'];

    // ==================================================================
    // ELIMINAR AMIGO
    // ==================================================================
    } elseif ($action === 'remove_friend') {
        $friendId = (int)($data['target_id'] ?? 0);

        $sql = "DELETE FROM friendships 
                WHERE (sender_id = ? AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $friendId, $friendId, $currentUserId]);

        $response = ['success' => true, 'message' => 'Amigo eliminado.'];

    // ==================================================================
    // NOTIFICACIONES
    // ==================================================================
    } elseif ($action === 'get_notifications') {
        $sql = "SELECT n.*, u.avatar as sender_avatar 
                FROM notifications n 
                LEFT JOIN users u ON n.related_id = u.id
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response = ['success' => true, 'notifications' => $notifs];

    } elseif ($action === 'mark_read_all') {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$currentUserId]);
        $response = ['success' => true, 'message' => 'Limpiado.'];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>