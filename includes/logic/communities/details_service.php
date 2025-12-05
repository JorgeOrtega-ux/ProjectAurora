<?php
// includes/logic/communities/details_service.php

class DetailsService {

    public static function getCommunityDetails($pdo, $userId, $uuid) {
        $uuid = trim($uuid ?? '');
        
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

        return [
            'success' => true, 
            'info' => $info, 
            'members' => $members, 
            'files' => $files,
            'channels' => $channels
        ];
    }

    public static function getPrivateChatDetails($pdo, $userId, $uuid) {
        $uuid = trim($uuid ?? '');
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
        
        return [
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
        ];
    }

    public static function getCommunityByUuid($pdo, $userId, $uuid) {
        $uuid = trim($uuid ?? '');
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
            return ['success' => true, 'data' => $comm]; 
        } 
        else return ['success' => false, 'message' => 'Error'];
    }

    public static function getUserChatByUuid($pdo, $userId, $uuid) {
        $uuid = trim($uuid ?? '');
        
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
            
            return ['success' => true, 'data' => $user]; 
        }
        else return ['success' => false, 'message' => 'Usuario no encontrado'];
    }
}
?>