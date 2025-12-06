<?php
// includes/logic/communities/channels_service.php

class ChannelsService {

    public static function createChannel($pdo, $userId, $communityUuid, $name) {
        $name = trim($name ?? '');
        // Forzar a 'text' y 0 usuarios por ahora
        $type = 'text';
        $maxUsers = 0;

        if (empty($communityUuid) || empty($name)) throw new Exception("Faltan datos.");
        
        $stmt = $pdo->prepare("SELECT c.id, cm.role FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
        $stmt->execute([$communityUuid, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Comunidad no encontrada.");
        
        // [MODIFICADO] Eliminado 'moderator' de la lista
        if (!in_array($row['role'], ['admin'])) throw new Exception("No tienes permisos para crear canales.");

        $newUuid = generate_uuid();
        
        $stmtIns = $pdo->prepare("INSERT INTO community_channels (uuid, community_id, name, type, max_users, status) VALUES (?, ?, ?, ?, ?, 'active')");
        
        if ($stmtIns->execute([$newUuid, $row['id'], $name, $type, $maxUsers])) {
            return [
                'success' => true, 
                'message' => "Canal creado.", 
                'channel' => [
                    'uuid' => $newUuid, 
                    'name' => $name, 
                    'type' => $type, 
                    'max_users' => $maxUsers, 
                    'status' => 'active', 
                    'unread_count' => 0
                ]
            ];
        } else {
            throw new Exception("Error al crear el canal.");
        }
    }

    public static function deleteChannel($pdo, $userId, $channelUuid) {
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
        
        // [MODIFICADO] Eliminado 'moderator' de la lista
        if (!in_array($row['role'], ['admin'])) throw new Exception("No tienes permisos.");

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM community_channels WHERE community_id = ?");
        $stmtCount->execute([$row['community_id']]);
        if ($stmtCount->fetchColumn() <= 1) {
            throw new Exception("No puedes eliminar el único canal de la comunidad.");
        }

        $stmtDel = $pdo->prepare("DELETE FROM community_channels WHERE id = ?");
        if ($stmtDel->execute([$row['id']])) {
            return ['success' => true, 'message' => "Canal eliminado."];
        } else {
            throw new Exception("Error al eliminar.");
        }
    }
}
?>