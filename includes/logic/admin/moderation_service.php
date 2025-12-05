<?php
// includes/logic/admin/moderation_service.php

function get_community_members($pdo, $commId) {
    $sql = "SELECT u.id, u.username, u.email, u.profile_picture, cm.role, cm.muted_until 
            FROM community_members cm 
            JOIN users u ON cm.user_id = u.id 
            WHERE cm.community_id = ? 
            ORDER BY FIELD(cm.role, 'admin', 'moderator', 'member'), u.username ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$commId]);
    return ['success' => true, 'members' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function get_community_banned_users($pdo, $commId) {
    $sql = "SELECT u.id, u.username, u.profile_picture, cb.reason, cb.created_at 
            FROM community_bans cb 
            JOIN users u ON cb.user_id = u.id 
            WHERE cb.community_id = ? 
            ORDER BY cb.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$commId]);
    return ['success' => true, 'banned_users' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function kick_member($pdo, $currentAdminId, $commId, $targetId) {
    if ($targetId === $currentAdminId) throw new Exception("No puedes expulsarte a ti mismo desde el panel de admin.");

    $pdo->beginTransaction();
    $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmtDel->execute([$commId, $targetId]);
    
    if ($stmtDel->rowCount() > 0) {
        $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$commId]);
        $pdo->commit();
        send_live_notification($targetId, 'force_disconnect', ['community_id' => $commId, 'reason' => 'Expulsado por administrador del sistema']);
        return ['success' => true, 'message' => "Usuario expulsado."];
    } else {
        $pdo->rollBack();
        throw new Exception("El usuario no es miembro de esta comunidad.");
    }
}

function ban_member($pdo, $currentAdminId, $commId, $targetId, $reason, $duration) {
    if ($targetId === $currentAdminId) throw new Exception("No puedes banearte a ti mismo.");

    $reason = trim($reason ?? 'Baneado por administración');
    $duration = $duration ?? 'permanent';
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
        $expiresAt = null;
    }

    $pdo->beginTransaction();
    $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
    $stmtDel->execute([$commId, $targetId]);
    if ($stmtDel->rowCount() > 0) {
        $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$commId]);
    }

    $stmtBan = $pdo->prepare("INSERT INTO community_bans (community_id, user_id, banned_by, reason, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)");
    $stmtBan->execute([$commId, $targetId, $currentAdminId, $reason, $expiresAt]);
    
    $pdo->commit();
    
    $banMsg = $expiresAt ? "Suspendido hasta " . date('d/m H:i', strtotime($expiresAt)) : "Baneado permanentemente";
    send_live_notification($targetId, 'force_disconnect', ['community_id' => $commId, 'reason' => "$banMsg: $reason"]);
    
    return ['success' => true, 'message' => "Usuario sancionado."];
}

function mute_member($pdo, $commId, $targetId, $duration) {
    $duration = (int)($duration ?? 15);
    $until = date('Y-m-d H:i:s', strtotime("+$duration minutes"));
    $stmt = $pdo->prepare("UPDATE community_members SET muted_until = ? WHERE community_id = ? AND user_id = ?");
    $stmt->execute([$until, $commId, $targetId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => "Usuario silenciado por $duration min."];
    } else {
        throw new Exception("Usuario no encontrado en la comunidad.");
    }
}

function unban_member($pdo, $commId, $targetId) {
    $stmt = $pdo->prepare("DELETE FROM community_bans WHERE community_id = ? AND user_id = ?");
    $stmt->execute([$commId, $targetId]);
    return ['success' => true, 'message' => "Baneo levantado."];
}
?>