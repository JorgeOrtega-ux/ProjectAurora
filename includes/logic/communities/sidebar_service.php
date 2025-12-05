<?php
// includes/logic/communities/sidebar_service.php

class SidebarService {

    /**
     * Obtiene la lista combinada de comunidades y chats privados para la barra lateral.
     * Fusiona datos de SQL con buffers de mensajes en tiempo real de Redis.
     */
    public static function getSidebarList($pdo, $redis, $userId) {
        
        // 1. Obtener Comunidades desde SQL [MODIFICADO: is_verified, status]
        $sqlCommunities = "SELECT 
                    'community' as type,
                    c.id, c.uuid, c.community_name as name, 
                    c.profile_picture, c.community_type, c.is_verified, c.status, 
                    cm.is_pinned, cm.is_favorite, cm.is_archived,
                    (SELECT message FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM community_messages WHERE community_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_at,
                    
                    (
                        SELECT COUNT(m.id)
                        FROM community_messages m
                        LEFT JOIN community_channel_reads ccr 
                            ON m.channel_id = ccr.channel_id 
                            AND ccr.user_id = cm.user_id
                        WHERE m.community_id = c.id 
                        AND m.user_id != cm.user_id
                        AND m.created_at > COALESCE(ccr.last_read_at, cm.joined_at, '1970-01-01 00:00:00')
                    ) as unread_count,

                    0 as is_blocked_by_me,
                    'member' as role,
                    (SELECT uuid FROM community_channels WHERE id = c.default_channel_id) as default_channel_uuid
                FROM communities c
                JOIN community_members cm ON c.id = cm.community_id
                WHERE cm.user_id = ?";
        
        $stmtComm = $pdo->prepare($sqlCommunities);
        $stmtComm->execute([$userId]);
        $communities = $stmtComm->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener DMs desde SQL
        $sqlDMs = "SELECT 
                    'private' as type,
                    u.id, u.uuid, u.username as name, 
                    u.profile_picture, u.role,
                    COALESCE(pcc.is_pinned, 0) as is_pinned,
                    COALESCE(pcc.is_favorite, 0) as is_favorite,
                    COALESCE(pcc.is_archived, 0) as is_archived,
                    m.message as last_message,
                    m.created_at as last_message_at,
                    (SELECT COUNT(*) FROM private_messages pm WHERE pm.sender_id = u.id AND pm.receiver_id = ? AND pm.is_read = 0) as unread_count,
                    COALESCE(up.message_privacy, 'friends') as message_privacy,
                    (SELECT status FROM friendships f WHERE (f.sender_id = u.id AND f.receiver_id = ?) OR (f.sender_id = ? AND f.receiver_id = u.id)) as friend_status,
                    (SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = u.id) as is_blocked_by_me
                   FROM users u
                   LEFT JOIN user_preferences up ON u.id = up.user_id
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
                   LEFT JOIN private_chat_clearance pcc ON (pcc.user_id = ? AND pcc.partner_id = u.id)
                   WHERE u.account_status = 'active'
                   AND (pcc.cleared_at IS NULL OR m.created_at > pcc.cleared_at)";

        $stmtDMs = $pdo->prepare($sqlDMs);
        $params = array_fill(0, 9, $userId);
        $stmtDMs->execute($params);
        $dms = $stmtDMs->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dms as &$dm) {
            $privacy = $dm['message_privacy'];
            $status = $dm['friend_status'];
            $dm['can_message'] = true;
            if ($privacy === 'nobody') $dm['can_message'] = false;
            elseif ($privacy === 'friends' && $status !== 'accepted') $dm['can_message'] = false;
            unset($dm['message_privacy']);
        }

        $fullList = array_merge($communities, $dms);
        
        // 3. Obtener buffers de Redis y fusionar
        if (isset($redis) && $redis) {
            try {
                $existingMap = [];
                foreach ($fullList as $index => $item) {
                    $key = $item['type'] . '_' . $item['id'];
                    $existingMap[$key] = $index;
                }

                $keys1 = $redis->keys("chat:buffer:private:$userId:*");
                $keys2 = $redis->keys("chat:buffer:private:*:$userId");
                $allBufferKeys = array_unique(array_merge($keys1, $keys2));

                foreach ($allBufferKeys as $key) {
                    $lastMsgJson = $redis->lIndex($key, -1);
                    if (!$lastMsgJson) continue;
                    
                    $msgData = json_decode($lastMsgJson, true);
                    if (!$msgData) continue;

                    $parts = explode(':', $key);
                    $id1 = $parts[3];
                    $id2 = $parts[4];
                    $partnerId = ($id1 == $userId) ? $id2 : $id1;
                    
                    $mapKey = 'private_' . $partnerId;
                    
                    $previewMsg = $msgData['message'];
                    if (empty($previewMsg) && !empty($msgData['attachments'])) {
                        $previewMsg = '📷 Imagen';
                    }
                    if ($msgData['sender_id'] == $userId) {
                        $previewMsg = 'Tú: ' . $previewMsg;
                    }

                    $redisTimestamp = $msgData['created_at'];

                    if (isset($existingMap[$mapKey])) {
                        $index = $existingMap[$mapKey];
                        $currentSqlTime = $fullList[$index]['last_message_at'];
                        
                        if (!$currentSqlTime || strtotime($redisTimestamp) > strtotime($currentSqlTime)) {
                            $fullList[$index]['last_message'] = $previewMsg;
                            $fullList[$index]['last_message_at'] = $redisTimestamp;
                            
                            if ($msgData['sender_id'] != $userId) {
                                $bufferLen = $redis->lLen($key);
                                $fullList[$index]['unread_count'] += $bufferLen; 
                            }
                        }
                    } else {
                        // CHAT NUEVO SOLO EN REDIS
                        $stmtUser = $pdo->prepare("SELECT id, uuid, username, profile_picture, role FROM users WHERE id = ?");
                        $stmtUser->execute([$partnerId]);
                        $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);
                        
                        if ($uData) {
                            $stmtStatus = $pdo->prepare("SELECT is_pinned, is_favorite, is_archived FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
                            $stmtStatus->execute([$userId, $partnerId]);
                            $statusData = $stmtStatus->fetch(PDO::FETCH_ASSOC);
                            
                            $realPinned = $statusData ? (int)$statusData['is_pinned'] : 0;
                            $realFav = $statusData ? (int)$statusData['is_favorite'] : 0;
                            $realArchived = $statusData ? (int)$statusData['is_archived'] : 0;

                            $newChat = [
                                'type' => 'private',
                                'id' => $uData['id'],
                                'uuid' => $uData['uuid'],
                                'name' => $uData['username'],
                                'profile_picture' => $uData['profile_picture'],
                                'role' => $uData['role'],
                                'is_pinned' => $realPinned, 
                                'is_favorite' => $realFav, 
                                'is_archived' => $realArchived, 
                                'last_message' => $previewMsg,
                                'last_message_at' => $redisTimestamp,
                                'unread_count' => ($msgData['sender_id'] != $userId) ? 1 : 0,
                                'can_message' => true,
                                'is_blocked_by_me' => 0
                            ];
                            $fullList[] = $newChat;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Redis Sidebar Merge Error: " . $e->getMessage());
            }
        }

        // 4. Ordenamiento final
        usort($fullList, function($a, $b) {
            $pinA = (int)($a['is_pinned'] ?? 0);
            $pinB = (int)($b['is_pinned'] ?? 0);
            if ($pinA !== $pinB) return $pinB - $pinA; 
            $t1 = $a['last_message_at'] ? strtotime($a['last_message_at']) : 0;
            $t2 = $b['last_message_at'] ? strtotime($b['last_message_at']) : 0;
            return $t2 - $t1;
        });

        return $fullList;
    }
}