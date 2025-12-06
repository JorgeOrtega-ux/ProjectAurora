<?php
// includes/logic/search_fetcher.php

class SearchFetcher {

    /**
     * Busca usuarios (Código existente)
     */
    public static function searchUsers($pdo, $currentUserId, $query, $offset = 0, $limit = 5) {
        $results = [];
        $hasMore = false;
        
        if (trim($query) === '') {
            return ['results' => [], 'hasMore' => false];
        }

        $queryLimit = $limit + 1;

        try {
            $sql = "SELECT u.id, u.username, u.profile_picture, u.role, 
                           f.status as friend_status, f.sender_id,
                           COALESCE(up.message_privacy, 'friends') as message_privacy,
                           (
                               SELECT COUNT(*) 
                               FROM friendships fA 
                               JOIN friendships fB 
                               ON (CASE WHEN fA.sender_id = ? THEN fA.receiver_id ELSE fA.sender_id END) = 
                                  (CASE WHEN fB.sender_id = u.id THEN fB.receiver_id ELSE fB.sender_id END)
                               WHERE (fA.sender_id = ? OR fA.receiver_id = ?) AND fA.status = 'accepted'
                               AND (fB.sender_id = u.id OR fB.receiver_id = u.id) AND fB.status = 'accepted'
                           ) as mutual_friends,
                           (SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = u.id) as is_blocked_by_me
                    FROM users u
                    LEFT JOIN user_preferences up ON u.id = up.user_id
                    LEFT JOIN friendships f 
                    ON (f.sender_id = ? AND f.receiver_id = u.id) 
                    OR (f.sender_id = u.id AND f.receiver_id = ?)
                    WHERE u.username LIKE ? 
                    AND u.id != ? 
                    AND u.account_status = 'active'
                    AND u.id NOT IN (
                        SELECT blocker_id FROM user_blocks WHERE blocked_id = ?
                    )
                    LIMIT $queryLimit OFFSET $offset";

            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                $currentUserId, $currentUserId, $currentUserId, 
                $currentUserId,                                 
                $currentUserId, $currentUserId,                 
                '%' . $query . '%',                             
                $currentUserId,                                 
                $currentUserId                                  
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > $limit) {
                $hasMore = true;
                array_pop($results); 
            }

        } catch (PDOException $e) {
            error_log("Error en SearchFetcher: " . $e->getMessage());
            return ['results' => [], 'hasMore' => false];
        }

        return [
            'results' => $results,
            'hasMore' => $hasMore
        ];
    }

    /**
     * [MODIFICADO] Busca comunidades públicas y privadas por nombre
     * Ahora retorna también si el usuario es miembro.
     */
    public static function searchCommunities($pdo, $query, $currentUserId, $limit = 3) {
        if (trim($query) === '') {
            return [];
        }

        try {
            // Buscamos comunidades activas (públicas o privadas)
            // Añadimos subconsulta para is_member
            $sql = "SELECT c.id, c.uuid, c.community_name, c.profile_picture, c.member_count, c.privacy,
                           (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id AND cm.user_id = ?) as is_member
                    FROM communities c 
                    WHERE c.community_name LIKE ? 
                    AND c.status = 'active'
                    AND (c.privacy = 'public' OR c.privacy = 'private')
                    LIMIT ?";
            
            $stmt = $pdo->prepare($sql);
            
            $stmt->bindValue(1, $currentUserId, PDO::PARAM_INT);
            $stmt->bindValue(2, '%' . $query . '%');
            $stmt->bindValue(3, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error en SearchFetcher (Communities): " . $e->getMessage());
            return [];
        }
    }
}
?>