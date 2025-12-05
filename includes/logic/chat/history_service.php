<?php
// includes/logic/chat/history_service.php

class ChatHistoryService {

    public static function getMessages($pdo, $redis, $userId, $data) {
        $uuid = $data['target_uuid'] ?? $data['community_uuid'] ?? '';
        $context = $data['context'] ?? 'community';
        $channelUuid = $data['channel_uuid'] ?? null; 

        $limit = isset($data['limit']) ? (int)$data['limit'] : 50;
        $offset = isset($data['offset']) ? (int)$data['offset'] : 0;
        
        $targetId = null;
        $channelId = null;
        $redisKey = '';

        // --- 1. Preparación de Contexto ---
        if ($context === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $targetId = $stmtU->fetchColumn();
            if (!$targetId) throw new Exception("Usuario no encontrado");

            // Marcar como leídos al abrir
            $stmtRead = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
            $stmtRead->execute([$targetId, $userId]);

            if (isset($redis) && $redis) {
                $minId = min($userId, $targetId);
                $maxId = max($userId, $targetId);
                $redisKey = "chat:buffer:private:$minId:$maxId";
            }

        } else {
            $stmtC = $pdo->prepare("SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
            $stmtC->execute([$uuid, $userId]);
            $targetId = $stmtC->fetchColumn();
            if (!$targetId) throw new Exception("Acceso denegado");

            $channelData = null;
            if (!empty($channelUuid)) {
                $stmtCh = $pdo->prepare("SELECT id, status FROM community_channels WHERE uuid = ? AND community_id = ?");
                $stmtCh->execute([$channelUuid, $targetId]);
                $channelData = $stmtCh->fetch(PDO::FETCH_ASSOC);
            }
            if (!$channelData && $context === 'community') {
                 $stmtDef = $pdo->prepare("SELECT id, status FROM community_channels WHERE community_id = ? ORDER BY created_at ASC LIMIT 1");
                 $stmtDef->execute([$targetId]);
                 $channelData = $stmtDef->fetch(PDO::FETCH_ASSOC);
            }

            if (!$channelData) throw new Exception("Canal no encontrado.");

            $channelId = $channelData['id'];
            $channelStatus = $channelData['status'];
            
            $stmtRole = $pdo->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ?");
            $stmtRole->execute([$targetId, $userId]);
            $memberRole = $stmtRole->fetchColumn(); 

            if ($channelStatus === 'maintenance' && !in_array($memberRole, ['founder', 'administrator', 'admin', 'moderator'])) {
                return ['success' => true, 'messages' => [], 'has_more' => false];
            }

            $sqlMarkRead = "INSERT INTO community_channel_reads (user_id, community_id, channel_id, last_read_at) 
                            VALUES (?, ?, ?, NOW()) 
                            ON DUPLICATE KEY UPDATE last_read_at = NOW()";
            $pdo->prepare($sqlMarkRead)->execute([$userId, $targetId, $channelId]);
            
            if (isset($redis) && $redis) {
                $redisKey = "chat:buffer:channel:$channelId";
            }
        }

        // --- 2. Obtener Mensajes de Redis (Buffer) ---
        $redisMessages = [];
        if (isset($redis) && $redis && !empty($redisKey)) {
            try {
                $rawRedis = $redis->lRange($redisKey, 0, -1);
                foreach ($rawRedis as $json) {
                    $m = json_decode($json, true);
                    if ($m) $redisMessages[] = $m;
                }
                $redisMessages = array_reverse($redisMessages);
            } catch (Exception $e) { error_log("Redis Read Error: " . $e->getMessage()); }
        }

        // --- 3. Obtener Mensajes de MySQL ---
        $sqlMessages = [];
        if ($context === 'private') {
            $stmtClear = $pdo->prepare("SELECT cleared_at FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtClear->execute([$userId, $targetId]);
            $clearedAt = $stmtClear->fetchColumn() ?: '1970-01-01 00:00:00';

            $sql = "
                SELECT m.id, m.uuid, m.message, m.created_at, m.type, m.reply_to_id, m.reply_to_uuid, m.status, m.sender_id,
                       m.is_edited, m.edited_at,
                       u.username as sender_username, u.profile_picture as sender_profile_picture, u.role as sender_role,
                       p.message as reply_message, p.type as reply_type, pu.username as reply_sender_username,
                       (SELECT COUNT(*) FROM private_message_attachments WHERE message_id = p.id) as reply_attachment_count,
                       (SELECT GROUP_CONCAT(CONCAT('{\"path\":\"', f.file_path, '\",\"type\":\"', f.file_type, '\"}') SEPARATOR ',') FROM private_message_attachments cma JOIN community_files f ON cma.file_id = f.id WHERE cma.message_id = m.id) as attachments_json
                FROM private_messages m
                JOIN users u ON m.sender_id = u.id
                LEFT JOIN private_messages p ON m.reply_to_id = p.id
                LEFT JOIN users pu ON p.sender_id = pu.id
                WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                AND m.created_at > ?
                ORDER BY m.created_at DESC 
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $targetId, $targetId, $userId, $clearedAt]);
        } else {
            $sql = "
                SELECT m.id, m.uuid, m.message, m.created_at, m.type, m.reply_to_id, m.reply_to_uuid, m.status, m.user_id as sender_id,
                       m.is_edited, m.edited_at,
                       u.username as sender_username, u.profile_picture as sender_profile_picture, u.role as sender_role,
                       p.message as reply_message, p.type as reply_type, pu.username as reply_sender_username,
                       (SELECT COUNT(*) FROM community_message_attachments WHERE message_id = p.id) as reply_attachment_count,
                       (SELECT GROUP_CONCAT(CONCAT('{\"path\":\"', f.file_path, '\",\"type\":\"', f.file_type, '\"}') SEPARATOR ',') FROM community_message_attachments cma JOIN community_files f ON cma.file_id = f.id WHERE cma.message_id = m.id) as attachments_json
                FROM community_messages m
                JOIN users u ON m.user_id = u.id
                LEFT JOIN community_messages p ON m.reply_to_id = p.id
                LEFT JOIN users pu ON p.user_id = pu.id
                WHERE m.community_id = ? AND m.channel_id = ?
                ORDER BY m.created_at DESC 
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$targetId, $channelId]);
        }
        
        $dbMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar adjuntos y estados
        foreach ($dbMessages as &$msg) {
            if ($msg['status'] === 'deleted') {
                $msg['message'] = null; $msg['attachments'] = [];
            } else {
                if (!empty($msg['attachments_json'])) {
                    $jsonString = '[' . $msg['attachments_json'] . ']';
                    $msg['attachments'] = json_decode($jsonString, true);
                } else {
                    $msg['attachments'] = [];
                }
            }
            $msg['is_edited'] = (bool)$msg['is_edited'];
            unset($msg['attachments_json']);
        }

        // --- 4. Fusión y Deduplicación ---
        $finalList = [];
        if ($offset === 0) {
            $finalList = array_merge($redisMessages, $dbMessages);
        } else {
            $finalList = $dbMessages;
        }

        $uniqueMessages = [];
        $seenUuids = [];
        foreach ($finalList as $m) {
            if (!in_array($m['uuid'], $seenUuids)) {
                $uniqueMessages[] = $m;
                $seenUuids[] = $m['uuid'];
            }
        }

        usort($uniqueMessages, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // --- 5. Carga de Reacciones ---
        $messageIds = array_column($uniqueMessages, 'id');
        $validMessageIds = array_filter($messageIds, fn($id) => !empty($id));
        
        if (!empty($validMessageIds)) {
            $reactionsTable = ($context === 'private') ? 'private_message_reactions' : 'community_message_reactions';
            $placeholders = implode(',', array_fill(0, count($validMessageIds), '?'));
            
            $sqlReact = "SELECT 
                            message_id, 
                            reaction_code, 
                            COUNT(*) as count,
                            SUM(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as user_reacted
                         FROM $reactionsTable 
                         WHERE message_id IN ($placeholders)
                         GROUP BY message_id, reaction_code";
            
            $params = array_merge([$userId], $validMessageIds);
            $stmtReact = $pdo->prepare($sqlReact);
            $stmtReact->execute($params);
            $allReactions = $stmtReact->fetchAll(PDO::FETCH_ASSOC);
            
            $reactionsByMessage = [];
            foreach ($allReactions as $r) {
                $mId = $r['message_id'];
                if (!isset($reactionsByMessage[$mId])) {
                    $reactionsByMessage[$mId] = [];
                }
                $reactionsByMessage[$mId][$r['reaction_code']] = [
                    'count' => (int)$r['count'],
                    'user_reacted' => (bool)$r['user_reacted']
                ];
            }
            
            foreach ($uniqueMessages as &$msg) {
                $msgId = $msg['id'] ?? null;
                if ($msgId && isset($reactionsByMessage[$msgId])) {
                    $msg['reactions'] = $reactionsByMessage[$msgId];
                } else {
                    $msg['reactions'] = [];
                }
            }
        } else {
            foreach ($uniqueMessages as &$msg) {
                $msg['reactions'] = [];
            }
        }

        return ['success' => true, 'messages' => array_reverse($uniqueMessages), 'has_more' => (count($dbMessages) >= $limit)];
    }

    public static function deleteConversation($pdo, $redis, $userId, $data) {
        $uuid = $data['target_uuid'] ?? '';
        if (empty($uuid)) throw new Exception(translation('global.action_invalid'));
        
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
        $stmtU->execute([$uuid]);
        $partnerId = $stmtU->fetchColumn();
        
        if (!$partnerId) throw new Exception("Usuario no encontrado.");

        // Flush de Redis a BD antes de borrar (limpiar vista)
        if (isset($redis) && $redis) {
            $minId = min($userId, $partnerId);
            $maxId = max($userId, $partnerId);
            $redisKey = "chat:buffer:private:$minId:$maxId";

            try {
                $cachedMessages = $redis->lRange($redisKey, 0, -1);
                
                if (!empty($cachedMessages)) {
                    $pdo->beginTransaction();
                    
                    foreach ($cachedMessages as $jsonMsg) {
                        $msg = json_decode($jsonMsg, true);
                        if (!$msg) continue;

                        $stmtCheck = $pdo->prepare("SELECT id FROM private_messages WHERE uuid = ?");
                        $stmtCheck->execute([$msg['uuid']]);
                        if ($stmtCheck->fetch()) continue;

                        $replyId = null;
                        if (!empty($msg['reply_to_uuid'])) {
                            $stmtRep = $pdo->prepare("SELECT id FROM private_messages WHERE uuid = ?");
                            $stmtRep->execute([$msg['reply_to_uuid']]);
                            $replyId = $stmtRep->fetchColumn() ?: null;
                        }

                        $sqlInsert = "INSERT INTO private_messages 
                            (uuid, sender_id, receiver_id, message, type, reply_to_id, reply_to_uuid, created_at, is_read, is_edited, edited_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"; 
                        
                        $stmtIns = $pdo->prepare($sqlInsert);
                        $stmtIns->execute([
                            $msg['uuid'],
                            $msg['sender_id'], 
                            $msg['receiver_id'] ?? $partnerId, 
                            $msg['message'],
                            $msg['type'],
                            $replyId,
                            $msg['reply_to_uuid'] ?? null,
                            $msg['created_at'],
                            isset($msg['is_edited']) && $msg['is_edited'] ? 1 : 0,
                            $msg['edited_at'] ?? null
                        ]);
                        
                        $newMsgId = $pdo->lastInsertId();

                        if (!empty($msg['attachments'])) {
                            $stmtAtt = $pdo->prepare("INSERT INTO private_message_attachments (message_id, file_id) VALUES (?, ?)");
                            $stmtFileSearch = $pdo->prepare("SELECT id FROM community_files WHERE file_path = ? LIMIT 1");
                            
                            foreach ($msg['attachments'] as $att) {
                                if (isset($att['db_id'])) {
                                    $stmtAtt->execute([$newMsgId, $att['db_id']]);
                                } else {
                                    $stmtFileSearch->execute([$att['path']]);
                                    $fId = $stmtFileSearch->fetchColumn();
                                    if ($fId) $stmtAtt->execute([$newMsgId, $fId]);
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $redis->del($redisKey);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Emergency Flush Error: " . $e->getMessage());
            }
        }

        $sql = "INSERT INTO private_chat_clearance (user_id, partner_id, cleared_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE cleared_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $partnerId]);
        
        return ['success' => true, 'message' => 'Chat eliminado de tu lista.'];
    }
}
?>