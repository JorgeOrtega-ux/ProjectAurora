<?php
// includes/logic/chat/actions_service.php

class ChatActionsService {

    // Helper privado para verificar si la cuenta destino sigue activa en chats privados
    private static function ensurePartnerActive($pdo, $userId, $messageId, $table = 'private_messages') {
        // En DMs, el 'partner' es el otro ID que no es el mío.
        $stmt = $pdo->prepare("SELECT sender_id, receiver_id FROM $table WHERE id = ?");
        $stmt->execute([$messageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $partnerId = ($row['sender_id'] == $userId) ? $row['receiver_id'] : $row['sender_id'];
            
            $stmtUser = $pdo->prepare("SELECT account_status FROM users WHERE id = ?");
            $stmtUser->execute([$partnerId]);
            $status = $stmtUser->fetchColumn();

            if ($status !== 'active') {
                throw new Exception("Acción no permitida: La cuenta del usuario no está disponible.");
            }
        }
    }

    public static function reactMessage($pdo, $redis, $userId, $data) {
        $msgUuid = $data['message_id'] ?? '';
        $reaction = $data['reaction'] ?? '';
        $context = $data['context'] ?? 'community';
        $targetUuid = $data['target_uuid'] ?? ''; 

        $allowedReactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        if (empty($msgUuid) || !in_array($reaction, $allowedReactions)) {
            throw new Exception("Reacción no válida.");
        }

        $messagesTable = ($context === 'private') ? 'private_messages' : 'community_messages';
        $reactionsTable = ($context === 'private') ? 'private_message_reactions' : 'community_message_reactions';

        // 1. Obtener ID real
        $selectFields = "id";
        if ($context === 'community') {
            $selectFields .= ", community_id";
        }

        $stmtMsg = $pdo->prepare("SELECT $selectFields FROM $messagesTable WHERE uuid = ?");
        $stmtMsg->execute([$msgUuid]);
        $msgData = $stmtMsg->fetch(PDO::FETCH_ASSOC);

        // Rescue from Redis if not in DB
        if (!$msgData) {
            if (isset($redis) && $redis) {
                $foundAndFlushed = false;
                $redisKeysToSearch = [];

                if ($context === 'private') {
                    $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
                    $stmtU->execute([$targetUuid]);
                    $targetId = $stmtU->fetchColumn();
                    if ($targetId) {
                        $min = min($userId, $targetId);
                        $max = max($userId, $targetId);
                        $redisKeysToSearch[] = "chat:buffer:private:$min:$max";
                    }
                } else {
                    $stmtComm = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
                    $stmtComm->execute([$targetUuid]);
                    $commId = $stmtComm->fetchColumn();
                    if ($commId) {
                        $stmtChans = $pdo->prepare("SELECT id FROM community_channels WHERE community_id = ?");
                        $stmtChans->execute([$commId]);
                        $channels = $stmtChans->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($channels as $cId) {
                            $redisKeysToSearch[] = "chat:buffer:channel:$cId";
                        }
                    }
                }

                foreach ($redisKeysToSearch as $rKey) {
                    $list = $redis->lRange($rKey, 0, -1);
                    foreach ($list as $jsonMsg) {
                        $memMsg = json_decode($jsonMsg, true);
                        if ($memMsg && isset($memMsg['uuid']) && $memMsg['uuid'] === $msgUuid) {
                            $replyId = null;
                            if (!empty($memMsg['reply_to_uuid'])) {
                                $stmtRep = $pdo->prepare("SELECT id FROM $messagesTable WHERE uuid = ?");
                                $stmtRep->execute([$memMsg['reply_to_uuid']]);
                                $replyId = $stmtRep->fetchColumn() ?: null;
                            }

                            if ($context === 'private') {
                                $sqlIns = "INSERT INTO private_messages (uuid, sender_id, receiver_id, message, type, reply_to_id, reply_to_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                                $pdo->prepare($sqlIns)->execute([
                                    $memMsg['uuid'], $memMsg['sender_id'], $memMsg['receiver_id'], 
                                    $memMsg['message'], $memMsg['type'], $replyId, $memMsg['reply_to_uuid'] ?? null, $memMsg['created_at']
                                ]);
                                $msgData = ['id' => $pdo->lastInsertId(), 'community_id' => null];
                            } else {
                                $sqlIns = "INSERT INTO community_messages (uuid, community_id, channel_id, user_id, message, type, reply_to_id, reply_to_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                $pdo->prepare($sqlIns)->execute([
                                    $memMsg['uuid'], $memMsg['community_id'], $memMsg['channel_id'], $memMsg['user_id'],
                                    $memMsg['message'], $memMsg['type'], $replyId, $memMsg['reply_to_uuid'] ?? null, $memMsg['created_at']
                                ]);
                                $msgData = ['id' => $pdo->lastInsertId(), 'community_id' => $memMsg['community_id']];
                            }

                            $redis->lRem($rKey, $jsonMsg, 1);
                            $foundAndFlushed = true;
                            break 2; 
                        }
                    }
                }
                
                if (!$foundAndFlushed) throw new Exception("Mensaje no encontrado (ni en BD ni en Redis).");
            } else {
                throw new Exception("Mensaje no encontrado.");
            }
        }
        
        $msgId = $msgData['id'];

        // [NUEVO] Congelar interacción si el usuario no existe (Solo Privado)
        if ($context === 'private') {
            self::ensurePartnerActive($pdo, $userId, $msgId, 'private_messages');
        }

        // 2. Toggle/Update Reaction
        $stmtCheck = $pdo->prepare("SELECT id, reaction_code FROM $reactionsTable WHERE message_id = ? AND user_id = ?");
        $stmtCheck->execute([$msgId, $userId]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['reaction_code'] === $reaction) {
                $pdo->prepare("DELETE FROM $reactionsTable WHERE id = ?")->execute([$existing['id']]);
            } else {
                $pdo->prepare("UPDATE $reactionsTable SET reaction_code = ?, created_at = NOW() WHERE id = ?")->execute([$reaction, $existing['id']]);
            }
        } else {
            $pdo->prepare("INSERT INTO $reactionsTable (message_id, user_id, reaction_code) VALUES (?, ?, ?)")->execute([$msgId, $userId, $reaction]);
        }

        $stmtCounts = $pdo->prepare("SELECT reaction_code, COUNT(*) as count FROM $reactionsTable WHERE message_id = ? GROUP BY reaction_code");
        $stmtCounts->execute([$msgId]);
        $counts = $stmtCounts->fetchAll(PDO::FETCH_KEY_PAIR);

        $socketPayload = [
            'message_uuid' => $msgUuid,
            'reactions' => $counts,
            'actor_id' => $userId,
            'context' => $context
        ];

        if ($context === 'private') {
            $stmtRec = $pdo->prepare("SELECT receiver_id, sender_id FROM private_messages WHERE id = ?");
            $stmtRec->execute([$msgId]);
            $recData = $stmtRec->fetch(PDO::FETCH_ASSOC);
            $partnerId = ($recData['sender_id'] == $userId) ? $recData['receiver_id'] : $recData['sender_id'];
            
            send_live_notification($partnerId, 'message_reaction_update', ['message_data' => $socketPayload]);
            send_live_notification($userId, 'message_reaction_update', ['message_data' => $socketPayload]);

        } else {
            $commId = $msgData['community_id'];
            send_live_notification('community_broadcast', 'message_reaction_update', ['message_data' => $socketPayload, 'community_id' => $commId]);
        }

        return ['success' => true, 'reactions' => $counts];
    }

    public static function editMessage($pdo, $redis, $userId, $data) {
        $msgUuid = $data['message_id'] ?? '';
        $newContent = trim($data['new_content'] ?? '');
        $context = $data['context'] ?? 'community';
        $targetUuid = $data['target_uuid'] ?? $data['community_uuid'] ?? '';
        $channelUuid = $data['channel_uuid'] ?? null;

        if (empty($msgUuid) || empty($newContent)) throw new Exception("Contenido o ID faltante.");

        $table = ($context === 'private') ? 'private_messages' : 'community_messages';
        $userCol = ($context === 'private') ? 'sender_id' : 'user_id';
        
        // [MODIFICADO] Agregar edit_count a los campos seleccionados
        $selectFields = "id, created_at, edit_count";
        if ($context === 'community') {
            $selectFields .= ", community_id, channel_id";
        }
        
        $stmtCheck = $pdo->prepare("SELECT $selectFields FROM $table WHERE uuid = ? AND $userCol = ?");
        $stmtCheck->execute([$msgUuid, $userId]);
        $dbMsg = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $updatedInDb = false;
        $targetSocketId = null;
        $extraSocketData = [];

        if ($dbMsg) {
            // [NUEVO] Congelar interacción si el usuario no existe (Solo Privado)
            if ($context === 'private') {
                self::ensurePartnerActive($pdo, $userId, $dbMsg['id'], 'private_messages');
            }

            $created = strtotime($dbMsg['created_at']);
            // [MODIFICADO] Tiempo límite de 15 minutos (900 segundos)
            if (time() - $created > 900) {
                throw new Exception(translation('chat.error.edit_timeout') ?? "Tiempo de edición expirado (15 min).");
            }

            // [MODIFICADO] Validar máximo 3 ediciones
            if ($dbMsg['edit_count'] >= 3) {
                throw new Exception("Límite de ediciones alcanzado (máximo 3).");
            }

            // [MODIFICADO] Incrementar edit_count
            $stmtUpd = $pdo->prepare("UPDATE $table SET message = ?, is_edited = 1, edit_count = edit_count + 1, edited_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$newContent, $dbMsg['id']]);
            $updatedInDb = true;

            if ($context === 'private') {
                $stmtRec = $pdo->prepare("SELECT receiver_id FROM private_messages WHERE id = ?");
                $stmtRec->execute([$dbMsg['id']]);
                $receiverId = $stmtRec->fetchColumn();
                $targetSocketId = $receiverId;
            } else {
                $targetSocketId = 'community_broadcast';
                $extraSocketData = ['community_id' => $dbMsg['community_id']];
            }

        } else {
            if (isset($redis) && $redis) {
                $redisKey = '';
                if ($context === 'private') {
                    $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
                    $stmtU->execute([$targetUuid]);
                    $targetId = $stmtU->fetchColumn();
                    if ($targetId) {
                        $min = min($userId, $targetId);
                        $max = max($userId, $targetId);
                        $redisKey = "chat:buffer:private:$min:$max";
                    }
                } else {
                    $channelId = null;
                    if ($channelUuid) {
                        $stmtCh = $pdo->prepare("SELECT id FROM community_channels WHERE uuid = ?");
                        $stmtCh->execute([$channelUuid]);
                        $channelId = $stmtCh->fetchColumn();
                    }
                    if ($channelId) {
                        $redisKey = "chat:buffer:channel:$channelId";
                    }
                }

                if ($redisKey) {
                    $list = $redis->lRange($redisKey, 0, -1);
                    foreach ($list as $index => $json) {
                        $msgObj = json_decode($json, true);
                        if ($msgObj['uuid'] === $msgUuid && (int)$msgObj['sender_id'] === (int)$userId) {
                            $created = strtotime($msgObj['created_at']);
                            // [MODIFICADO] Tiempo límite de 15 minutos (900 segundos)
                            if (time() - $created > 900) {
                                throw new Exception(translation('chat.error.edit_timeout') ?? "Tiempo de edición expirado.");
                            }
                            
                            // [MODIFICADO] Validar máximo 3 ediciones (asumiendo 0 si no existe)
                            $currentEdits = $msgObj['edit_count'] ?? 0;
                            if ($currentEdits >= 3) {
                                throw new Exception("Límite de ediciones alcanzado (máximo 3).");
                            }

                            $msgObj['message'] = $newContent;
                            $msgObj['is_edited'] = true;
                            // [MODIFICADO] Incrementar conteo en Redis
                            $msgObj['edit_count'] = $currentEdits + 1;
                            $msgObj['edited_at'] = date('c');
                            
                            $redis->lSet($redisKey, $index, json_encode($msgObj));
                            $updatedInDb = true; 

                            if ($context === 'private') {
                                $targetSocketId = $msgObj['receiver_id'];
                            } else {
                                $targetSocketId = 'community_broadcast';
                                $extraSocketData = ['community_id' => $msgObj['community_id']];
                            }
                            break; 
                        }
                    }
                }
            }
        }

        if ($updatedInDb) {
            $socketPayload = [
                'uuid' => $msgUuid,
                'new_content' => $newContent,
                'is_edited' => true,
                'context' => $context,
                'channel_uuid' => $channelUuid,
                'target_uuid' => $targetUuid
            ];

            $socketData = ['message_data' => $socketPayload];
            if (!empty($extraSocketData)) {
                $socketData = array_merge($socketData, $extraSocketData);
            }

            send_live_notification($targetSocketId, 'message_edited', $socketData);
            if ($context === 'private') {
                 send_live_notification($userId, 'message_edited', $socketData);
            }

            return ['success' => true, 'message' => 'Mensaje editado'];
        } else {
            throw new Exception("El mensaje no se encontró o expiró el tiempo de edición.");
        }
    }

    public static function markAsRead($pdo, $redis, $userId, $data) {
        $targetUuid = $data['target_uuid'] ?? ''; 
        $context = $data['context'] ?? 'private';
        $channelUuid = $data['channel_uuid'] ?? null; 

        if (empty($targetUuid)) throw new Exception("UUID requerido");
        
        if ($context === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$targetUuid]);
            $senderId = $stmtU->fetchColumn();
            if ($senderId) {
                $stmtUpd = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                $stmtUpd->execute([$senderId, $userId]);
                if ($stmtUpd->rowCount() > 0) {
                    send_live_notification($senderId, 'messages_read', ['reader_id' => $userId, 'context' => 'private']);
                }
            }
        } else {
            $stmtC = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
            $stmtC->execute([$targetUuid]);
            $commId = $stmtC->fetchColumn();
            
            if ($commId && $channelUuid) {
                $stmtCh = $pdo->prepare("SELECT id FROM community_channels WHERE uuid = ?");
                $stmtCh->execute([$channelUuid]);
                $channelId = $stmtCh->fetchColumn();

                if ($channelId) {
                    $sql = "INSERT INTO community_channel_reads (user_id, community_id, channel_id, last_read_at) 
                            VALUES (?, ?, ?, NOW()) 
                            ON DUPLICATE KEY UPDATE last_read_at = NOW()";
                    $pdo->prepare($sql)->execute([$userId, $commId, $channelId]);
                }
            }
        }
        return ['success' => true];
    }

    public static function deleteMessage($pdo, $userId, $data) {
        $msgUuid = $data['message_id'] ?? '';
        $context = $data['context'] ?? 'community';
        if (empty($msgUuid)) throw new Exception(translation('global.action_invalid'));
        $table = ($context === 'private') ? 'private_messages' : 'community_messages';
        $userCol = ($context === 'private') ? 'sender_id' : 'user_id';
        $stmt = $pdo->prepare("SELECT id, created_at, status FROM $table WHERE uuid = ? AND $userCol = ?");
        $stmt->execute([$msgUuid, $userId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$msg) throw new Exception("El mensaje no se puede eliminar (aún no guardado en base de datos).");
        if ($msg['status'] === 'deleted') throw new Exception("Ya eliminado.");
        
        // [NUEVO] Congelar interacción (borrado) si el usuario no existe (Solo Privado)
        if ($context === 'private') {
            self::ensurePartnerActive($pdo, $userId, $msg['id'], 'private_messages');
        }

        $msgTime = strtotime($msg['created_at']);
        if (time() - $msgTime > (24 * 3600)) throw new Exception(translation('chat.error.delete_timeout') ?? 'Tiempo expirado');
        $pdo->prepare("UPDATE $table SET status = 'deleted', message = '', type = 'text' WHERE id = ?")->execute([$msg['id']]);
        $eventType = ($context === 'private') ? 'private_message_deleted' : 'message_deleted';
        $notifPayload = ['message_id' => $msgUuid, 'sender_id' => $userId];
        if ($context === 'private') {
            $stmtRec = $pdo->prepare("SELECT receiver_id FROM private_messages WHERE id = ?");
            $stmtRec->execute([$msg['id']]);
            $receiverId = $stmtRec->fetchColumn();
            send_live_notification($receiverId, $eventType, $notifPayload);
            send_live_notification($userId, $eventType, $notifPayload);
        } else {
            $stmtComm = $pdo->prepare("SELECT community_id FROM community_messages WHERE id = ?");
            $stmtComm->execute([$msg['id']]);
            $commId = $stmtComm->fetchColumn();
            send_live_notification('community_broadcast', $eventType, ['message_data' => $notifPayload, 'community_id' => $commId]);
        }
        return ['success' => true, 'message' => translation('chat.message_deleted')];
    }

    public static function reportMessage($pdo, $userId, $data) {
        $msgUuid = $data['message_id'] ?? '';
        $reason = trim($data['reason'] ?? '');
        $context = $data['context'] ?? 'community';
        if (empty($msgUuid) || empty($reason)) throw new Exception(translation('admin.error.reason_required'));
        $table = ($context === 'private') ? 'private_messages' : 'community_messages';
        $stmtCheck = $pdo->prepare("SELECT id FROM $table WHERE uuid = ?");
        $stmtCheck->execute([$msgUuid]);
        $msgId = $stmtCheck->fetchColumn();
        if (!$msgId) throw new Exception("Mensaje no encontrado o aún en proceso de guardado.");

        // [NUEVO] Congelar interacción (reporte) si el usuario no existe (Solo Privado)
        if ($context === 'private') {
            self::ensurePartnerActive($pdo, $userId, $msgId, 'private_messages');
        }

        if ($context === 'private') {
            $stmtRep = $pdo->prepare("SELECT id FROM private_message_reports WHERE message_id = ? AND reporter_id = ?");
            $stmtRep->execute([$msgId, $userId]);
            if ($stmtRep->rowCount() > 0) throw new Exception(translation('chat.error.already_reported') ?? 'Ya reportado');
            $stmtIns = $pdo->prepare("INSERT INTO private_message_reports (message_id, reporter_id, reason, created_at) VALUES (?, ?, ?, NOW())");
            $stmtIns->execute([$msgId, $userId, $reason]);
        } else {
            $stmtRep = $pdo->prepare("SELECT id FROM community_message_reports WHERE message_id = ? AND reporter_id = ?");
            $stmtRep->execute([$msgId, $userId]);
            if ($stmtRep->rowCount() > 0) throw new Exception(translation('chat.error.already_reported') ?? 'Ya reportado');
            $stmtIns = $pdo->prepare("INSERT INTO community_message_reports (message_id, reporter_id, reason, created_at) VALUES (?, ?, ?, NOW())");
            $stmtIns->execute([$msgId, $userId, $reason]);
        }
        return ['success' => true, 'message' => translation('chat.report_success')];
    }
}
?>