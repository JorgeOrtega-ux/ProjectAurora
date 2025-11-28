<?php
// api/communities_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/communities_error.log';
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

    // --- UNIRSE POR CÓDIGO ---
    if ($action === 'join_by_code') {
        $code = trim($data['access_code'] ?? '');
        
        if (empty($code) || strlen($code) !== 14) {
            throw new Exception("El código debe tener el formato XXXX-XXXX-XXXX.");
        }

        $stmt = $pdo->prepare("SELECT id, community_name, privacy FROM communities WHERE access_code = ?");
        $stmt->execute([$code]);
        $community = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$community) {
            throw new Exception("Código de acceso inválido o comunidad no encontrada.");
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$community['id'], $userId]);
        if ($stmtCheck->rowCount() > 0) {
            throw new Exception("Ya eres miembro de <strong>" . htmlspecialchars($community['community_name']) . "</strong>.");
        }

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')");
            $stmtInsert->execute([$community['id'], $userId]);

            $stmtCount = $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?");
            $stmtCount->execute([$community['id']]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Te has unido a <strong>" . htmlspecialchars($community['community_name']) . "</strong> correctamente."]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new Exception(translation('global.error_connection'));
        }

    // --- OBTENER MIS COMUNIDADES (LISTA IZQUIERDA) ---
    } elseif ($action === 'get_my_communities') {
        // [CORRECCIÓN CRÍTICA]: 
        // 1. Se agregó 'AND user_id != cm.user_id' en el conteo de unread_count para ignorar mensajes propios.
        // 2. Se mantienen las subconsultas para last_message.
        $sql = "SELECT 
                    c.id, c.uuid, c.community_name, c.description, c.privacy, c.member_count, 
                    c.profile_picture, c.banner_picture, cm.role,
                    (SELECT message FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_at,
                    (SELECT COUNT(*) FROM community_messages WHERE community_id = c.id AND created_at > cm.last_read_at AND user_id != cm.user_id) as unread_count
                FROM communities c
                JOIN community_members cm ON c.id = cm.community_id
                WHERE cm.user_id = ?
                ORDER BY last_message_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'communities' => $communities]);

    // --- OBTENER COMUNIDADES PÚBLICAS ---
    } elseif ($action === 'get_public_communities') {
        $sql = "SELECT c.id, c.uuid, c.community_name, c.description, c.member_count, c.profile_picture, c.banner_picture
                FROM communities c
                WHERE c.privacy = 'public'
                AND c.id NOT IN (SELECT community_id FROM community_members WHERE user_id = ?)
                ORDER BY c.member_count DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'communities' => $communities]);

    // --- UNIRSE A PÚBLICA ---
    } elseif ($action === 'join_public') {
        $communityId = (int)($data['community_id'] ?? 0);
        
        $stmtC = $pdo->prepare("SELECT id, privacy FROM communities WHERE id = ?");
        $stmtC->execute([$communityId]);
        $comm = $stmtC->fetch();

        if (!$comm || $comm['privacy'] !== 'public') {
            throw new Exception(translation('global.action_invalid'));
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$communityId, $userId]);
        if ($stmtCheck->rowCount() > 0) throw new Exception("Ya eres miembro.");

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)")->execute([$communityId, $userId]);
        $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?")->execute([$communityId]);
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Te has unido al grupo.']);

    // --- ABANDONAR ---
    } elseif ($action === 'leave_community') {
        $communityId = (int)($data['community_id'] ?? 0);

        $pdo->beginTransaction();
        $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtDel->execute([$communityId, $userId]);

        if ($stmtDel->rowCount() > 0) {
            $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$communityId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Has salido del grupo.']);
        } else {
            $pdo->rollBack();
            throw new Exception(translation('global.action_invalid'));
        }

    // --- OBTENER DETALLES DE UNA COMUNIDAD POR UUID (PARA VALIDAR URL) ---
    } elseif ($action === 'get_community_by_uuid') {
        $uuid = trim($data['uuid'] ?? '');
        // Solo devolver si el usuario es miembro
        $sql = "SELECT c.id, c.uuid, c.community_name, c.profile_picture, c.banner_picture, cm.role
                FROM communities c
                JOIN community_members cm ON c.id = cm.community_id
                WHERE c.uuid = ? AND cm.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uuid, $userId]);
        $comm = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($comm) {
            echo json_encode(['success' => true, 'community' => $comm]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Comunidad no encontrada o acceso denegado']);
        }

    } else {
        throw new Exception(translation('global.action_invalid'));
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>