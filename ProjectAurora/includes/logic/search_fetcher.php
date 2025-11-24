<?php
// includes/logic/search_fetcher.php

class SearchFetcher {

    /**
     * Busca usuarios y calcula el estado de amistad y amigos en común.
     * @param PDO $pdo Conexión a la base de datos.
     * @param int $currentUserId ID del usuario que realiza la búsqueda.
     * @param string $query Término de búsqueda.
     * @param int $offset Desplazamiento para paginación.
     * @param int $limit Límite de resultados.
     * @return array ['results' => array, 'hasMore' => bool]
     */
    public static function searchUsers($pdo, $currentUserId, $query, $offset = 0, $limit = 5) {
        $results = [];
        $hasMore = false;
        
        // Si no hay búsqueda, retornamos vacío
        if (trim($query) === '') {
            return ['results' => [], 'hasMore' => false];
        }

        // Pedimos uno más del límite para saber si hay "siguiente página"
        $queryLimit = $limit + 1;

        try {
            // [CORRECCIÓN] Se cambió u.avatar por u.profile_picture para coincidir con la BD
            $sql = "SELECT u.id, u.username, u.profile_picture, u.role, 
                           f.status as friend_status, f.sender_id,
                           (
                               SELECT COUNT(*) 
                               FROM friendships fA 
                               JOIN friendships fB 
                               ON (CASE WHEN fA.sender_id = ? THEN fA.receiver_id ELSE fA.sender_id END) = 
                                  (CASE WHEN fB.sender_id = u.id THEN fB.receiver_id ELSE fB.sender_id END)
                               WHERE (fA.sender_id = ? OR fA.receiver_id = ?) AND fA.status = 'accepted'
                               AND (fB.sender_id = u.id OR fB.receiver_id = u.id) AND fB.status = 'accepted'
                           ) as mutual_friends
                    FROM users u
                    LEFT JOIN friendships f 
                    ON (f.sender_id = ? AND f.receiver_id = u.id) 
                    OR (f.sender_id = u.id AND f.receiver_id = ?)
                    WHERE u.username LIKE ? 
                    AND u.id != ? 
                    AND u.account_status = 'active'
                    LIMIT $queryLimit OFFSET $offset";

            $stmt = $pdo->prepare($sql);
            
            // Bind de parámetros
            $stmt->execute([
                $currentUserId, $currentUserId, $currentUserId, // Para subconsulta mutual_friends
                $currentUserId, $currentUserId,                 // Para JOIN friendships principal
                '%' . $query . '%',                             // Para LIKE username
                $currentUserId                                  // Para excluirse a sí mismo
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Lógica de paginación
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
}
?>