<?php
// includes/logic/communities/moderation_service.php

class ModerationService {

    public static function kickMember($pdo, $userId, $commUuid, $targetUuid) {
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
        if (function_exists('send_live_notification')) {
            send_live_notification($targetId, 'force_disconnect', ['community_id' => $commId, 'reason' => 'Has sido expulsado.']);
        }

        return ['success' => true, 'message' => 'Usuario expulsado.'];
    }

    public static function banMember($pdo, $userId, $commUuid, $targetUuid, $reason, $duration) {
        $reason = trim($reason ?? 'Sin razón especificada');
        $duration = $duration ?? 'permanent'; 

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

        // Cálculo de expiración
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
        
        if (function_exists('send_live_notification')) {
            send_live_notification($targetId, 'force_disconnect', ['community_id' => $commId, 'reason' => "$banMsg: $reason"]);
        }

        return ['success' => true, 'message' => 'Usuario sancionado correctamente.'];
    }

    public static function unbanMember($pdo, $userId, $commUuid, $targetUuid) {
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

        return ['success' => true, 'message' => 'Usuario desbaneado.'];
    }

    public static function muteMember($pdo, $userId, $commUuid, $targetUuid, $minutes) {
        $minutes = (int)$minutes;
        if ($minutes < 1) $minutes = 5;

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

        // Notificar al usuario
        if (function_exists('send_live_notification')) {
            send_live_notification($targetId, 'new_notification', [
                'type' => 'system',
                'message' => "Has sido silenciado por $minutes minutos en esta comunidad."
            ]);
        }

        return ['success' => true, 'message' => "Usuario silenciado por $minutes min."];
    }
}
?>