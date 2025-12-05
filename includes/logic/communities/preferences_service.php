<?php
// includes/logic/communities/preferences_service.php

class PreferencesService {

    public static function togglePin($pdo, $userId, $uuid, $type) {
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

        return ['success' => true, 'is_pinned' => $newState, 'message' => $newState ? 'Chat fijado' : 'Chat desfijado'];
    }

    public static function toggleFavorite($pdo, $userId, $uuid, $type) {
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

        return ['success' => true, 'is_favorite' => $newState, 'message' => $newState ? 'Marcado como favorito' : 'Quitado de favoritos'];
    }

    public static function toggleArchive($pdo, $userId, $uuid, $type) {
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

        return ['success' => true, 'is_archived' => $newState, 'message' => $newState ? 'Chat archivado' : 'Chat desarchivado'];
    }
}
?>