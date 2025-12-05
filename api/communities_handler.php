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
// [NUEVO] Importamos el servicio de sidebar
require_once '../includes/logic/communities/sidebar_service.php';

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

        // [MODIFICADO] Obtenemos max_members y member_count
        $stmt = $pdo->prepare("SELECT id, community_name, privacy, member_count, max_members FROM communities WHERE access_code = ?");
        $stmt->execute([$code]);
        $community = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$community) {
            throw new Exception("Código de acceso inválido o comunidad no encontrada.");
        }

        // [MODIFICADO] Verificar Baneo y Expiración
        $stmtBan = $pdo->prepare("SELECT reason, expires_at FROM community_bans WHERE community_id = ? AND user_id = ?");
        $stmtBan->execute([$community['id'], $userId]);
        $banData = $stmtBan->fetch(PDO::FETCH_ASSOC);

        if ($banData) {
            $isBanned = true;
            $msg = "No puedes unirte. ";
            
            if ($banData['expires_at']) {
                $expires = new DateTime($banData['expires_at']);
                $now = new DateTime();
                
                if ($now > $expires) {
                    // La suspensión ya expiró, borramos el registro y permitimos entrar
                    $pdo->prepare("DELETE FROM community_bans WHERE community_id = ? AND user_id = ?")->execute([$community['id'], $userId]);
                    $isBanned = false;
                } else {
                    $msg .= "Estás suspendido hasta el " . $expires->format('d/m/Y H:i') . ". Razón: " . htmlspecialchars($banData['reason']);
                }
            } else {
                // Es un baneo permanente
                $msg .= "Estás vetado permanentemente. Razón: " . htmlspecialchars($banData['reason']);
            }

            if ($isBanned) {
                throw new Exception($msg);
            }
        }

        // [NUEVO] Validación de Límite de Miembros
        if ($community['max_members'] > 0 && $community['member_count'] >= $community['max_members']) {
            throw new Exception("La comunidad <strong>" . htmlspecialchars($community['community_name']) . "</strong> está llena.");
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

    // --- OBTENER SIDEBAR LIST (FUSIÓN SQL + REDIS) ---
    } elseif ($action === 'get_sidebar_list') {
        
        // Usamos el nuevo servicio para obtener la lista
        $fullList = SidebarService::getSidebarList($pdo, $redis ?? null, $userId);
        
        echo json_encode(['success' => true, 'list' => $fullList]);

    } elseif ($action === 'toggle_pin') {
        $uuid = $data['uuid'] ?? '';
        $type = $data['type'] ?? ''; 
        
        if (!$uuid || !$type) throw new Exception(translation('global.action_invalid'));

        $sqlCount = "SELECT 
            (SELECT COUNT(*) FROM community_members WHERE user_id = ? AND is_pinned = 1) + 
            (SELECT COUNT(*) FROM private_chat_clearance WHERE user_id = ? AND is_pinned = 1) as total_pinned";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([$userId, $userId]);
        $totalPinned = (int)$stmtCount->fetchColumn();

        $newState = 0;

        if ($type === 'community') {
            $stmtCurr = $pdo->prepare("SELECT is_pinned, id FROM community_members WHERE user_id = ? AND community_id = (SELECT id FROM communities WHERE uuid = ?)");
            $stmtCurr->execute([$userId, $uuid]);
            $row = $stmtCurr->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) throw new Exception("Comunidad no encontrada.");
            
            $currentPinned = (int)$row['is_pinned'];
            $newState = $currentPinned ? 0 : 1;

            if ($newState === 1 && $totalPinned >= 3) throw new Exception("Máximo 3 chats fijados.");

            $pdo->prepare("UPDATE community_members SET is_pinned = ? WHERE id = ?")->execute([$newState, $row['id']]);

        } elseif ($type === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $partnerId = $stmtU->fetchColumn();
            if (!$partnerId) throw new Exception("Usuario no encontrado.");

            $stmtCurr = $pdo->prepare("SELECT is_pinned FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtCurr->execute([$userId, $partnerId]);
            $currentPinned = (int)$stmtCurr->fetchColumn();

            $newState = $currentPinned ? 0 : 1;

            if ($newState === 1 && $totalPinned >= 3) throw new Exception("Máximo 3 chats fijados.");

            $sqlUpd = "INSERT INTO private_chat_clearance (user_id, partner_id, is_pinned, cleared_at) VALUES (?, ?, ?, NULL) 
                       ON DUPLICATE KEY UPDATE is_pinned = VALUES(is_pinned)";
            $pdo->prepare($sqlUpd)->execute([$userId, $partnerId, $newState]);
        }

        echo json_encode(['success' => true, 'is_pinned' => $newState, 'message' => $newState ? 'Chat fijado' : 'Chat desfijado']);

    } elseif ($action === 'toggle_favorite') {
        $uuid = $data['uuid'] ?? '';
        $type = $data['type'] ?? ''; 
        
        if (!$uuid || !$type) throw new Exception(translation('global.action_invalid'));

        $newState = 0;

        if ($type === 'community') {
            $stmtCurr = $pdo->prepare("SELECT is_favorite, id FROM community_members WHERE user_id = ? AND community_id = (SELECT id FROM communities WHERE uuid = ?)");
            $stmtCurr->execute([$userId, $uuid]);
            $row = $stmtCurr->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) throw new Exception("Error.");
            
            $newState = ((int)$row['is_favorite']) ? 0 : 1;
            $pdo->prepare("UPDATE community_members SET is_favorite = ? WHERE id = ?")->execute([$newState, $row['id']]);

        } elseif ($type === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $partnerId = $stmtU->fetchColumn();
            if (!$partnerId) throw new Exception("Error.");

            $stmtCurr = $pdo->prepare("SELECT is_favorite FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtCurr->execute([$userId, $partnerId]);
            $currentFav = (int)$stmtCurr->fetchColumn();

            $newState = $currentFav ? 0 : 1;

            $sqlUpd = "INSERT INTO private_chat_clearance (user_id, partner_id, is_favorite, cleared_at) VALUES (?, ?, ?, NULL) 
                       ON DUPLICATE KEY UPDATE is_favorite = VALUES(is_favorite)";
            $pdo->prepare($sqlUpd)->execute([$userId, $partnerId, $newState]);
        }

        echo json_encode(['success' => true, 'is_favorite' => $newState, 'message' => $newState ? 'Marcado como favorito' : 'Quitado de favoritos']);

    } elseif ($action === 'toggle_archive') {
        $uuid = $data['uuid'] ?? '';
        $type = $data['type'] ?? ''; 
        
        if (!$uuid || !$type) throw new Exception(translation('global.action_invalid'));

        $newState = 0;

        if ($type === 'community') {
            $stmtCurr = $pdo->prepare("SELECT is_archived, id FROM community_members WHERE user_id = ? AND community_id = (SELECT id FROM communities WHERE uuid = ?)");
            $stmtCurr->execute([$userId, $uuid]);
            $row = $stmtCurr->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) throw new Exception("Comunidad no encontrada.");
            
            $newState = ((int)$row['is_archived']) ? 0 : 1;
            $pdo->prepare("UPDATE community_members SET is_archived = ? WHERE id = ?")->execute([$newState, $row['id']]);

        } elseif ($type === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $partnerId = $stmtU->fetchColumn();
            if (!$partnerId) throw new Exception("Usuario no encontrado.");

            $stmtCurr = $pdo->prepare("SELECT is_archived FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtCurr->execute([$userId, $partnerId]);
            $currentArchived = (int)$stmtCurr->fetchColumn();

            $newState = $currentArchived ? 0 : 1;

            $sqlUpd = "INSERT INTO private_chat_clearance (user_id, partner_id, is_archived, cleared_at) VALUES (?, ?, ?, NULL) 
                       ON DUPLICATE KEY UPDATE is_archived = VALUES(is_archived)";
            $pdo->prepare($sqlUpd)->execute([$userId, $partnerId, $newState]);
        }

        echo json_encode(['success' => true, 'is_archived' => $newState, 'message' => $newState ? 'Chat archivado' : 'Chat desarchivado']);

    } elseif ($action === 'get_public_communities') {
        // [MODIFICADO: is_verified]
        $sql = "SELECT c.id, c.uuid, c.community_name, c.community_type, c.is_verified, c.member_count, c.profile_picture, c.banner_picture
                FROM communities c
                WHERE c.privacy = 'public'
                AND c.id NOT IN (SELECT community_id FROM community_members WHERE user_id = ?)
                ORDER BY c.member_count DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'communities' => $communities]);

    } elseif ($action === 'join_public') {
        $communityId = (int)($data['community_id'] ?? 0);

        // [MODIFICADO] Verificar Baneo y Expiración
        $stmtBan = $pdo->prepare("SELECT reason, expires_at FROM community_bans WHERE community_id = ? AND user_id = ?");
        $stmtBan->execute([$communityId, $userId]);
        $banData = $stmtBan->fetch(PDO::FETCH_ASSOC);

        if ($banData) {
            $isBanned = true;
            $msg = "No puedes unirte. ";
            
            if ($banData['expires_at']) {
                $expires = new DateTime($banData['expires_at']);
                $now = new DateTime();
                
                if ($now > $expires) {
                    $pdo->prepare("DELETE FROM community_bans WHERE community_id = ? AND user_id = ?")->execute([$communityId, $userId]);
                    $isBanned = false;
                } else {
                    $msg .= "Estás suspendido hasta el " . $expires->format('d/m/Y H:i') . ". Razón: " . htmlspecialchars($banData['reason']);
                }
            } else {
                $msg .= "Estás vetado permanentemente. Razón: " . htmlspecialchars($banData['reason']);
            }

            if ($isBanned) {
                throw new Exception($msg);
            }
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$communityId, $userId]);
        if ($stmtCheck->rowCount() > 0) throw new Exception("Ya eres miembro.");
        
        // [NUEVO] Validación de Límite
        $stmtLimit = $pdo->prepare("SELECT member_count, max_members FROM communities WHERE id = ?");
        $stmtLimit->execute([$communityId]);
        $commData = $stmtLimit->fetch(PDO::FETCH_ASSOC);

        if ($commData['max_members'] > 0 && $commData['member_count'] >= $commData['max_members']) {
            throw new Exception("La comunidad está llena.");
        }

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)")->execute([$communityId, $userId]);
        $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?")->execute([$communityId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Te has unido al grupo.']);

    } elseif ($action === 'leave_community') {
        $uuid = $data['uuid'] ?? '';
        $communityId = (int)($data['community_id'] ?? 0);

        if ($communityId === 0 && !empty($uuid)) {
            $stmtId = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
            $stmtId->execute([$uuid]);
            $communityId = $stmtId->fetchColumn();
        }

        if (empty($communityId)) throw new Exception(translation('global.action_invalid'));

        $pdo->beginTransaction();
        $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtDel->execute([$communityId, $userId]);

        if ($stmtDel->rowCount() > 0) {
            $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$communityId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Has salido del grupo.']);
        } else {
            $pdo->rollBack();
            throw new Exception("No eres miembro de este grupo.");
        }

    // --- ACCIONES DE MODERACIÓN (NUEVO) ---

    } elseif ($action === 'kick_member') {
        $commUuid = $data['community_uuid'] ?? '';
        $targetUuid = $data['target_uuid'] ?? '';

        if (empty($commUuid) || empty($targetUuid)) throw new Exception("Datos incompletos.");

        $stmtC = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
        $stmtC->execute([$commUuid]);
        $commId = $stmtC->fetchColumn();
        if (!$commId) throw new Exception("Comunidad no encontrada.");

        $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
        $stmtU->execute([$targetUuid]);
        $targetId = $stmtU->fetchColumn();
        if (!$targetId) throw new Exception("Usuario no encontrado.");
        if ($targetId == $userId) throw new Exception("No te puedes expulsar a ti mismo.");

        // Verificar roles
        $stmtRoles = $pdo->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
        
        // Rol del ejecutor
        $stmtRoles->execute([$commId, $userId]);
        $myRole = $stmtRoles->fetchColumn(); // 'admin', 'moderator', 'member'

        if (!in_array($myRole, ['admin', 'moderator'])) throw new Exception("No tienes permisos.");

        // Rol del objetivo
        $stmtRoles->execute([$commId, $targetId]);
        $targetRole = $stmtRoles->fetchColumn();

        if (!$targetRole) throw new Exception("El usuario no es miembro.");

        // Jerarquía: Admin > Mod > Member
        if ($myRole === 'moderator' && in_array($targetRole, ['admin', 'moderator'])) {
            throw new Exception("No puedes expulsar a un superior o igual.");
        }
        if ($myRole === 'admin' && $targetRole === 'admin') {
             throw new Exception("No puedes expulsar a otro administrador.");
        }

        $pdo->beginTransaction();
        $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtDel->execute([$commId, $targetId]);
        $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$commId]);
        $pdo->commit();

        // Notificar desconexión forzada
        send_live_notification($targetId, 'force_disconnect', ['community_id' => $commId, 'reason' => 'Has sido expulsado.']);

        echo json_encode(['success' => true, 'message' => 'Usuario expulsado.']);

    } elseif ($action === 'ban_member') {
        $commUuid = $data['community_uuid'] ?? '';
        $targetUuid = $data['target_uuid'] ?? '';
        $reason = trim($data['reason'] ?? 'Sin razón especificada');
        // [MODIFICADO] Recibir duración
        $duration = $data['duration'] ?? 'permanent'; // 'permanent', '12h', '1d', '3d', '1w'

        if (empty($commUuid) || empty($targetUuid)) throw new Exception("Datos incompletos.");

        $stmtC = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
        $stmtC->execute([$commUuid]);
        $commId = $stmtC->fetchColumn();

        $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
        $stmtU->execute([$targetUuid]);
        $targetId = $stmtU->fetchColumn();

        if (!$commId || !$targetId) throw new Exception("Datos inválidos.");
        if ($targetId == $userId) throw new Exception("No te puedes banear a ti mismo.");

        // Verificar roles
        $stmtRoles = $pdo->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
        
        $stmtRoles->execute([$commId, $userId]);
        $myRole = $stmtRoles->fetchColumn();

        if (!in_array($myRole, ['admin', 'moderator'])) throw new Exception("No tienes permisos.");

        $stmtRoles->execute([$commId, $targetId]);
        $targetRole = $stmtRoles->fetchColumn();

        // Si es miembro actual, verificar jerarquía
        if ($targetRole) {
            if ($myRole === 'moderator' && in_array($targetRole, ['admin', 'moderator'])) {
                throw new Exception("No puedes banear a un superior o igual.");
            }
            if ($myRole === 'admin' && $targetRole === 'admin') {
                throw new Exception("No puedes banear a otro administrador.");
            }
        }

        // [MODIFICADO] Cálculo de expiración
        $expiresAt = null;
        $modifiers = [
            '12h' => '+12 hours',
            '1d'  => '+1 day',
            '3d'  => '+3 days',
            '1w'  => '+1 week'
        ];
        
        if ($duration !== 'permanent' && isset($modifiers[$duration])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime($modifiers[$duration]));
        } else {
            $expiresAt = null; // Permanente
        }

        $pdo->beginTransaction();
        // 1. Eliminar membresía si existe
        $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtDel->execute([$commId, $targetId]);
        if ($stmtDel->rowCount() > 0) {
            $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$commId]);
        }

        // 2. Insertar Ban con expiración
        $stmtBan = $pdo->prepare("INSERT INTO community_bans (community_id, user_id, banned_by, reason, expires_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)");
        $stmtBan->execute([$commId, $targetId, $userId, $reason, $expiresAt]);
        
        $pdo->commit();

        $banMsg = $expiresAt ? "Suspendido hasta " . date('d/m H:i', strtotime($expiresAt)) : "Baneado permanentemente";
        send_live_notification($targetId, 'force_disconnect', ['community_id' => $commId, 'reason' => "$banMsg: $reason"]);

        echo json_encode(['success' => true, 'message' => 'Usuario sancionado correctamente.']);

    } elseif ($action === 'unban_member') {
        $commUuid = $data['community_uuid'] ?? '';
        $targetUuid = $data['target_uuid'] ?? '';

        $stmtC = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
        $stmtC->execute([$commUuid]);
        $commId = $stmtC->fetchColumn();

        $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
        $stmtU->execute([$targetUuid]);
        $targetId = $stmtU->fetchColumn();

        if (!$commId || !$targetId) throw new Exception("Error en datos.");

        // Solo admins/mods pueden desbanear
        $stmtCheck = $pdo->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$commId, $userId]);
        $myRole = $stmtCheck->fetchColumn();

        if (!in_array($myRole, ['admin', 'moderator'])) throw new Exception("No tienes permisos.");

        $pdo->prepare("DELETE FROM community_bans WHERE community_id = ? AND user_id = ?")->execute([$commId, $targetId]);

        echo json_encode(['success' => true, 'message' => 'Usuario desbaneado.']);

    } elseif ($action === 'mute_member') {
        $commUuid = $data['community_uuid'] ?? '';
        $targetUuid = $data['target_uuid'] ?? '';
        $minutes = (int)($data['duration'] ?? 5); // Default 5 mins

        if ($minutes < 1) throw new Exception("Duración inválida.");

        $stmtC = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
        $stmtC->execute([$commUuid]);
        $commId = $stmtC->fetchColumn();

        $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
        $stmtU->execute([$targetUuid]);
        $targetId = $stmtU->fetchColumn();

        if (!$commId || !$targetId) throw new Exception("Error.");

        // Verificar roles
        $stmtRoles = $pdo->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
        
        $stmtRoles->execute([$commId, $userId]);
        $myRole = $stmtRoles->fetchColumn();

        if (!in_array($myRole, ['admin', 'moderator'])) throw new Exception("No tienes permisos.");

        $stmtRoles->execute([$commId, $targetId]);
        $targetRole = $stmtRoles->fetchColumn();

        if (!$targetRole) throw new Exception("Usuario no es miembro.");

        if ($myRole === 'moderator' && in_array($targetRole, ['admin', 'moderator'])) {
            throw new Exception("No puedes silenciar a superiores.");
        }
        if ($myRole === 'admin' && $targetRole === 'admin') {
            throw new Exception("No puedes silenciar a otros admins.");
        }

        // Calcular timestamp
        $until = date('Y-m-d H:i:s', strtotime("+$minutes minutes"));

        $pdo->prepare("UPDATE community_members SET muted_until = ? WHERE community_id = ? AND user_id = ?")->execute([$until, $commId, $targetId]);

        // Notificar al usuario (opcionalmente podrías agregar un evento socket 'user_muted')
        send_live_notification($targetId, 'new_notification', [
            'type' => 'system',
            'message' => "Has sido silenciado por $minutes minutos en esta comunidad."
        ]);

        echo json_encode(['success' => true, 'message' => "Usuario silenciado por $minutes min."]);

    } elseif ($action === 'get_community_by_uuid') {
        $uuid = trim($data['uuid'] ?? '');
        // [MODIFICADO: is_verified, status]
        $sql = "SELECT c.id, c.uuid, c.community_name, c.community_type, c.is_verified, c.status, c.profile_picture, c.banner_picture, cm.role,
                       (SELECT uuid FROM community_channels WHERE id = c.default_channel_id) as default_channel_uuid 
                FROM communities c 
                JOIN community_members cm ON c.id = cm.community_id 
                WHERE c.uuid = ? AND cm.user_id = ?";
        $stmt = $pdo->prepare($sql); 
        $stmt->execute([$uuid, $userId]); 
        $comm = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($comm) { 
            $comm['type'] = 'community'; 
            echo json_encode(['success' => true, 'data' => $comm]); 
        } 
        else echo json_encode(['success' => false, 'message' => 'Error']);

    } elseif ($action === 'get_user_chat_by_uuid') {
        $uuid = trim($data['uuid'] ?? '');
        
        $sql = "SELECT u.id, u.uuid, u.username as community_name, u.profile_picture, u.role,
                       COALESCE(up.message_privacy, 'friends') as message_privacy,
                       (SELECT status FROM friendships f WHERE (f.sender_id = u.id AND f.receiver_id = ?) OR (f.sender_id = ? AND f.receiver_id = u.id)) as friend_status
                FROM users u 
                LEFT JOIN user_preferences up ON u.id = up.user_id
                WHERE u.uuid = ? AND u.id != ?";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $uuid, $userId]); 
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) { 
            $user['type'] = 'private'; 
            $user['banner_picture'] = null; 
            if(empty($user['role'])) $user['role'] = 'member'; 
            
            $privacy = $user['message_privacy'];
            $status = $user['friend_status'];
            $user['can_message'] = true;

            if ($privacy === 'nobody') {
                $user['can_message'] = false;
            } elseif ($privacy === 'friends' && $status !== 'accepted') {
                $user['can_message'] = false;
            }
            
            unset($user['message_privacy']);
            unset($user['friend_status']);
            
            echo json_encode(['success' => true, 'data' => $user]); 
        }
        else echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);

    // --- OBTENER DETALLES COMPLETOS DE COMUNIDAD + CANALES ---
    } elseif ($action === 'get_community_details') {
        $uuid = trim($data['uuid'] ?? '');
        
        // [MODIFICADO: is_verified, max_members, status]
        $stmtC = $pdo->prepare("SELECT c.id, c.community_name, c.community_type, c.is_verified, c.max_members, c.status, c.profile_picture, c.access_code, c.member_count, cm.role,
                                (SELECT uuid FROM community_channels WHERE id = c.default_channel_id) as default_channel_uuid
                                FROM communities c 
                                JOIN community_members cm ON c.id = cm.community_id 
                                WHERE c.uuid = ? AND cm.user_id = ?");
        $stmtC->execute([$uuid, $userId]);
        $info = $stmtC->fetch(PDO::FETCH_ASSOC);
        
        if (!$info) throw new Exception("Error o no tienes acceso.");
        
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

        // [MODIFICADO] Agregar max_users y status a la consulta de canales
        $sqlChannels = "
            SELECT 
                cc.id, cc.uuid, cc.name, cc.type, cc.max_users, cc.status,
                (
                    SELECT COUNT(*) 
                    FROM community_messages cm
                    LEFT JOIN community_channel_reads ccr ON ccr.channel_id = cm.channel_id AND ccr.user_id = ?
                    WHERE cm.channel_id = cc.id 
                    AND cm.created_at > COALESCE(ccr.last_read_at, '1970-01-01 00:00:00')
                ) as unread_count
            FROM community_channels cc 
            WHERE cc.community_id = ? 
            ORDER BY cc.created_at ASC";

        $stmtChannels = $pdo->prepare($sqlChannels);
        $stmtChannels->execute([$userId, $info['id']]);
        $channels = $stmtChannels->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channels)) {
            $newUuid = generate_uuid();
            $pdo->prepare("INSERT INTO community_channels (uuid, community_id, name, type) VALUES (?, ?, 'General', 'text')")
                ->execute([$newUuid, $info['id']]);
            $channels[] = ['id' => $pdo->lastInsertId(), 'uuid' => $newUuid, 'name' => 'General', 'type' => 'text', 'max_users' => 0, 'status' => 'active', 'unread_count' => 0];
        }

        echo json_encode([
            'success' => true, 
            'info' => $info, 
            'members' => $members, 
            'files' => $files,
            'channels' => $channels
        ]);

    } elseif ($action === 'get_private_chat_details') {
        $uuid = trim($data['uuid'] ?? '');
        $stmtU = $pdo->prepare("SELECT id, username, profile_picture, role FROM users WHERE uuid = ?");
        $stmtU->execute([$uuid]);
        $user = $stmtU->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new Exception("Usuario no encontrado.");
        $targetId = $user['id'];
        $sqlFiles = "
            SELECT f.file_path, f.file_type, f.created_at, u.username 
            FROM community_files f 
            JOIN users u ON f.uploader_id = u.id 
            JOIN private_message_attachments pma ON f.id = pma.file_id 
            JOIN private_messages pm ON pma.message_id = pm.id 
            WHERE 
                ((pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND pm.receiver_id = ?))
                AND pm.status = 'active' 
                AND f.file_type LIKE 'image/%' 
            ORDER BY f.created_at DESC 
            LIMIT 12";
        $stmtF = $pdo->prepare($sqlFiles);
        $stmtF->execute([$userId, $targetId, $targetId, $userId]);
        $files = $stmtF->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true, 
            'info' => [
                'community_name' => $user['username'], 
                'profile_picture' => $user['profile_picture'],
                'role' => $user['role'],
                'member_count' => null, 
                'access_code' => 'Chat Directo' 
            ], 
            'members' => [], 
            'files' => $files,
            'channels' => [] 
        ]);

    // --- CREAR CANAL (MODIFICADO) ---
    } elseif ($action === 'create_channel') {
        $communityUuid = $data['community_uuid'] ?? '';
        $name = trim($data['name'] ?? '');
        
        // [MODIFICADO] Forzar a 'text' y 0 usuarios
        $type = 'text';
        $maxUsers = 0;

        if (empty($communityUuid) || empty($name)) throw new Exception("Faltan datos.");
        
        $stmt = $pdo->prepare("SELECT c.id, cm.role FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
        $stmt->execute([$communityUuid, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Comunidad no encontrada.");
        if (!in_array($row['role'], ['admin', 'moderator'])) throw new Exception("No tienes permisos para crear canales.");

        $newUuid = generate_uuid();
        // Se añade max_users y status al INSERT
        $stmtIns = $pdo->prepare("INSERT INTO community_channels (uuid, community_id, name, type, max_users, status) VALUES (?, ?, ?, ?, ?, 'active')");
        
        if ($stmtIns->execute([$newUuid, $row['id'], $name, $type, $maxUsers])) {
            echo json_encode(['success' => true, 'message' => "Canal creado.", 'channel' => ['uuid' => $newUuid, 'name' => $name, 'type' => $type, 'max_users' => $maxUsers, 'status' => 'active', 'unread_count' => 0]]);
        } else {
            throw new Exception("Error al crear el canal.");
        }

    // --- ELIMINAR CANAL ---
    } elseif ($action === 'delete_channel') {
        $channelUuid = $data['channel_uuid'] ?? '';
        if (empty($channelUuid)) throw new Exception("UUID requerido.");

        $stmt = $pdo->prepare("
            SELECT cc.id, cc.community_id, cm.role 
            FROM community_channels cc 
            JOIN community_members cm ON cc.community_id = cm.community_id 
            WHERE cc.uuid = ? AND cm.user_id = ?
        ");
        $stmt->execute([$channelUuid, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Canal no encontrado o sin permisos.");
        if (!in_array($row['role'], ['admin', 'moderator'])) throw new Exception("No tienes permisos.");

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM community_channels WHERE community_id = ?");
        $stmtCount->execute([$row['community_id']]);
        if ($stmtCount->fetchColumn() <= 1) {
            throw new Exception("No puedes eliminar el único canal de la comunidad.");
        }

        $stmtDel = $pdo->prepare("DELETE FROM community_channels WHERE id = ?");
        if ($stmtDel->execute([$row['id']])) {
            echo json_encode(['success' => true, 'message' => "Canal eliminado."]);
        } else {
            throw new Exception("Error al eliminar.");
        }

    // [ELIMINADO] Acciones de join/leave voice channel

    } else {
        throw new Exception(translation('global.action_invalid'));
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>