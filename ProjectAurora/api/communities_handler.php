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

    // --- OBTENER SIDEBAR LIST (Comunidades + DMs) ---
    } elseif ($action === 'get_sidebar_list') {
        
        // 1. Obtener Comunidades
        $sqlCommunities = "SELECT 
                    'community' as type,
                    c.id, c.uuid, c.community_name as name, 
                    c.profile_picture, 
                    (SELECT message FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_at,
                    (SELECT COUNT(*) FROM community_messages WHERE community_id = c.id AND created_at > cm.last_read_at AND user_id != cm.user_id) as unread_count
                FROM communities c
                JOIN community_members cm ON c.id = cm.community_id
                WHERE cm.user_id = ?";
        
        $stmtComm = $pdo->prepare($sqlCommunities);
        $stmtComm->execute([$userId]);
        $communities = $stmtComm->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener Chats Privados Activos
        // [MODIFICADO] Agregado u.role a la selección
        $sqlDMs = "SELECT 
                    'private' as type,
                    u.id, u.uuid, u.username as name, 
                    u.profile_picture, u.role, 
                    m.message as last_message,
                    m.created_at as last_message_at,
                    (SELECT COUNT(*) FROM private_messages pm WHERE pm.sender_id = u.id AND pm.receiver_id = ? AND pm.is_read = 0) as unread_count
                   FROM users u
                   JOIN (
                       SELECT 
                           CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id,
                           message, created_at
                       FROM private_messages
                       WHERE id IN (
                           SELECT MAX(id) 
                           FROM private_messages 
                           WHERE sender_id = ? OR receiver_id = ? 
                           GROUP BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
                       )
                   ) m ON u.id = m.partner_id
                   WHERE u.account_status = 'active'";

        $stmtDMs = $pdo->prepare($sqlDMs);
        $stmtDMs->execute([$userId, $userId, $userId, $userId, $userId]);
        $dms = $stmtDMs->fetchAll(PDO::FETCH_ASSOC);

        // 3. Unificar y Ordenar
        $fullList = array_merge($communities, $dms);
        
        // Ordenar por fecha del último mensaje descendente
        usort($fullList, function($a, $b) {
            $t1 = $a['last_message_at'] ? strtotime($a['last_message_at']) : 0;
            $t2 = $b['last_message_at'] ? strtotime($b['last_message_at']) : 0;
            return $t2 - $t1;
        });

        echo json_encode(['success' => true, 'list' => $fullList]);

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

    // --- OBTENER DETALLES DE UNA COMUNIDAD POR UUID ---
    } elseif ($action === 'get_community_by_uuid') {
        $uuid = trim($data['uuid'] ?? '');
        $sql = "SELECT c.id, c.uuid, c.community_name, c.profile_picture, c.banner_picture, cm.role
                FROM communities c
                JOIN community_members cm ON c.id = cm.community_id
                WHERE c.uuid = ? AND cm.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uuid, $userId]);
        $comm = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($comm) {
            $comm['type'] = 'community'; 
            echo json_encode(['success' => true, 'data' => $comm]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Comunidad no encontrada o acceso denegado']);
        }

    // --- OBTENER DETALLES DE USUARIO (PARA DM) POR UUID ---
    } elseif ($action === 'get_user_chat_by_uuid') {
        $uuid = trim($data['uuid'] ?? '');
        // [MODIFICADO] Añadido role a la selección
        $sql = "SELECT id, uuid, username as community_name, profile_picture, role FROM users WHERE uuid = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uuid, $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user['type'] = 'private'; 
            $user['banner_picture'] = null; 
            // Ya no forzamos 'role' = 'member', usamos el real del usuario si existe, o member por defecto
            if(empty($user['role'])) $user['role'] = 'member';
            
            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }

    // --- OBTENER DETALLES COMPLETOS (INFO PANEL) ---
    } elseif ($action === 'get_community_details') {
        $uuid = trim($data['uuid'] ?? '');
        
        $stmtC = $pdo->prepare("SELECT c.id, c.community_name, c.description, c.profile_picture, c.access_code, c.member_count 
                                FROM communities c 
                                JOIN community_members cm ON c.id = cm.community_id 
                                WHERE c.uuid = ? AND cm.user_id = ?");
        $stmtC->execute([$uuid, $userId]);
        $info = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$info) throw new Exception("Acceso denegado o comunidad no encontrada.");

        $sqlMembers = "SELECT u.id, u.username, u.profile_picture, cm.role 
                       FROM community_members cm 
                       JOIN users u ON cm.user_id = u.id 
                       WHERE cm.community_id = ? 
                       ORDER BY FIELD(cm.role, 'admin', 'moderator', 'member'), u.username ASC";
        $stmtM = $pdo->prepare($sqlMembers);
        $stmtM->execute([$info['id']]);
        $members = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        $sqlFiles = "SELECT f.file_path, f.file_type, f.created_at, u.username, u.profile_picture 
                     FROM community_files f
                     JOIN users u ON f.uploader_id = u.id
                     JOIN community_message_attachments cma ON f.id = cma.file_id
                     JOIN community_messages m ON cma.message_id = m.id
                     WHERE m.community_id = ? AND m.status = 'active' AND f.file_type LIKE 'image/%'
                     ORDER BY f.created_at DESC LIMIT 12";
        $stmtF = $pdo->prepare($sqlFiles);
        $stmtF->execute([$info['id']]);
        $files = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'info' => $info,
            'members' => $members,
            'files' => $files
        ]);

    } else {
        throw new Exception(translation('global.action_invalid'));
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>