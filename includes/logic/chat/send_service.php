<?php
// includes/logic/chat/send_service.php

class ChatSendService {

    public static function sendMessage($pdo, $redis, $userId, $data, $files) {
        // --- 1. Validaciones Previas ---
        
        // Anti-Spam Check
        if (function_exists('checkChatSpam') && checkChatSpam($pdo, $userId)) {
            $config = getServerConfig($pdo);
            throw new Exception(translation('chat.error.spam_limit', ['limit' => $config['chat_msg_limit'], 'seconds' => $config['chat_time_window']]));
        }

        $uuid = $data['target_uuid'] ?? $data['community_uuid'] ?? ''; 
        $channelUuid = $data['channel_uuid'] ?? null;
        
        $context = $data['context'] ?? 'community'; 
        
        // [FIX DE SEGURIDAD] Sanitización de entrada (Stored XSS Prevention)
        // Convertimos caracteres especiales a entidades HTML para neutralizar scripts
        $rawMessage = trim($data['message'] ?? '');
        $messageText = htmlspecialchars($rawMessage, ENT_QUOTES, 'UTF-8');
        
        $replyToUuid = !empty($data['reply_to_uuid']) ? $data['reply_to_uuid'] : null;
        
        if (empty($uuid)) throw new Exception("UUID destino requerido");

        $targetId = null;
        $channelId = null;

        // --- 2. Validación de Contexto (Privado vs Comunidad) ---
        if ($context === 'private') {
            // [MODIFICADO] Obtenemos también account_status para validar "Conversación Congelada"
            $stmtU = $pdo->prepare("SELECT id, account_status FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $targetUser = $stmtU->fetch(PDO::FETCH_ASSOC);
            
            if (!$targetUser) throw new Exception("Usuario no encontrado.");
            
            $targetId = $targetUser['id'];
            
            // [NUEVO] Validación de estado de cuenta
            if ($targetId == $userId) throw new Exception("No puedes enviarte mensajes a ti mismo.");
            if ($targetUser['account_status'] !== 'active') {
                throw new Exception("Este usuario ya no está disponible.");
            }

            // Validaciones de Bloqueo
            $stmtBlock = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
            $stmtBlock->execute([$userId, $targetId, $targetId, $userId]);
            if ($stmtBlock->rowCount() > 0) throw new Exception(translation('chat.error.privacy_block'));

            // Validaciones de Privacidad
            $stmtPriv = $pdo->prepare("SELECT COALESCE(up.message_privacy, 'friends') as privacy, (SELECT status FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) as status FROM users u LEFT JOIN user_preferences up ON u.id = up.user_id WHERE u.id = ?");
            $stmtPriv->execute([$userId, $targetId, $targetId, $userId, $targetId]);
            $res = $stmtPriv->fetch(PDO::FETCH_ASSOC);
            $privacy = $res['privacy'] ?? 'friends';
            $status = $res['status']; 
            if ($privacy === 'nobody') {
                throw new Exception(translation('chat.error.privacy_block'));
            }
            if ($privacy === 'friends' && $status !== 'accepted') {
                throw new Exception(translation('chat.error.privacy_block'));
            }

        } else {
            // Contexto Comunidad
            $stmtC = $pdo->prepare("SELECT c.id, c.status as comm_status, cm.role, cm.muted_until FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
            $stmtC->execute([$uuid, $userId]);
            $commData = $stmtC->fetch(PDO::FETCH_ASSOC);
            
            if (!$commData) throw new Exception("Acceso denegado a la comunidad.");
            
            if (!empty($commData['muted_until']) && strtotime($commData['muted_until']) > time()) {
                throw new Exception("Estás silenciado en esta comunidad hasta: " . $commData['muted_until']);
            }
            
            $targetId = $commData['id'];
            $userRole = $commData['role'];
            $isImmune = in_array($userRole, ['founder', 'administrator', 'admin', 'moderator']);

            if ($commData['comm_status'] === 'maintenance' && !$isImmune) {
                throw new Exception(translation('status.community_maintenance_msg') ?? 'Comunidad en mantenimiento.');
            }

            if (empty($channelUuid)) throw new Exception("Canal requerido.");
            
            $stmtCh = $pdo->prepare("SELECT id, status FROM community_channels WHERE uuid = ? AND community_id = ?");
            $stmtCh->execute([$channelUuid, $targetId]);
            $chanData = $stmtCh->fetch(PDO::FETCH_ASSOC);
            
            if (!$chanData) throw new Exception("Canal no válido o no pertenece a esta comunidad.");
            
            $channelId = $chanData['id'];

            if ($chanData['status'] === 'maintenance' && !$isImmune) {
                throw new Exception(translation('status.channel_maintenance_msg') ?? 'Canal en mantenimiento.');
            }
        }

        // --- 3. Lógica de Respuestas (Reply) ---
        $reply_data = [];
        if ($replyToUuid) {
            $table = ($context === 'private') ? 'private_messages' : 'community_messages';
            $joinCol = ($context === 'private') ? 'sender_id' : 'user_id';
            
            // Buscar primero en BD
            $parent_query = "SELECT m.message, m.type, u.username FROM $table m JOIN users u ON m.$joinCol = u.id WHERE m.uuid = ?";
            $stmtReply = $pdo->prepare($parent_query);
            $stmtReply->execute([$replyToUuid]);
            $parent_row = $stmtReply->fetch(PDO::FETCH_ASSOC);

            if ($parent_row) {
                $reply_data = [
                    'message' => $parent_row['message'], 
                    'sender_username' => $parent_row['username'],
                    'type' => $parent_row['type']
                ];
            } elseif (isset($redis) && $redis) {
                // Buscar en Redis si no está en BD
                $searchRedisKey = ($context === 'private') 
                    ? "chat:buffer:private:".min($userId, $targetId).":".max($userId, $targetId) 
                    : "chat:buffer:channel:$channelId"; 
                
                $cachedMessages = $redis->lRange($searchRedisKey, 0, -1);
                foreach ($cachedMessages as $jsonMsg) {
                    $item = json_decode($jsonMsg, true);
                    if ($item && isset($item['uuid']) && $item['uuid'] === $replyToUuid) {
                        $reply_data = [
                            'message' => $item['message'],
                            'sender_username' => $item['sender_username'], 
                            'type' => $item['type']
                        ];
                        break; 
                    }
                }
            }
        }

        // --- 4. Procesamiento de Archivos ---
        $uploadedFiles = [];
        if (isset($files['attachments']) && !empty($files['attachments']['name'][0])) {
            $f = $files['attachments'];
            $count = count($f['name']);
            if ($count > 4) throw new Exception("Máximo 4 imágenes permitidas.");
            
            // Ajuste de ruta: subimos 3 niveles desde includes/logic/chat/
            $uploadDir = __DIR__ . '/../../../public/assets/uploads/chat/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

            // [SECURITY PATCH] Mapa de extensiones seguras
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];

            for ($i = 0; $i < $count; $i++) {
                if ($f['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $f['tmp_name'][$i];
                    $name = basename($f['name'][$i]);
                    
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmpName);
                    
                    // [SECURITY FIX] Validamos MIME y forzamos extensión segura
                    if (!array_key_exists($mime, $allowedMimes)) continue;
                    
                    $safeExt = $allowedMimes[$mime];
                    $newFileName = generate_uuid() . '.' . $safeExt;
                    
                    $targetPath = $uploadDir . $newFileName;
                    $dbPath = 'assets/uploads/chat/' . $newFileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $fileUuid = generate_uuid();
                        $stmtFile = $pdo->prepare("INSERT INTO community_files (uuid, uploader_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?, ?)");
                        $stmtFile->execute([$fileUuid, $userId, $dbPath, $name, $mime]);
                        $fileId = $pdo->lastInsertId();
                        $uploadedFiles[] = ['name' => $name, 'path' => $dbPath, 'mime' => $mime, 'type' => 'image', 'db_id' => $fileId];
                    }
                }
            }
        }

        if (empty($messageText) && empty($uploadedFiles)) throw new Exception("El mensaje no puede estar vacío.");

        // --- 5. Construcción del Mensaje ---
        $msgType = (!empty($uploadedFiles)) ? (empty($messageText) ? 'image' : 'mixed') : 'text';
        $messageUuid = generate_uuid();
        $createdAt = date('c');

        $stmtUser = $pdo->prepare("SELECT uuid, username, profile_picture, role FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $messagePayload = [
            'id' => null, 
            'uuid' => $messageUuid,
            'target_uuid' => $uuid, 
            'context' => $context,
            'message' => $messageText,
            'sender_id' => $userId,
            'sender_uuid' => $userData['uuid'],
            'sender_username' => $userData['username'],
            'sender_profile_picture' => $userData['profile_picture'],
            'sender_role' => $userData['role'],
            'created_at' => $createdAt,
            'type' => $msgType,
            'status' => 'active',
            'is_edited' => false, 
            'reply_to_uuid' => $replyToUuid,
            'attachments' => $uploadedFiles,
            'reply_message' => $reply_data['message'] ?? null,
            'reply_sender_username' => $reply_data['sender_username'] ?? null,
            'reply_type' => $reply_data['type'] ?? null,
            'community_uuid' => ($context === 'community') ? $uuid : null,
            'channel_uuid' => $channelUuid, 
            'community_id' => ($context === 'community') ? $targetId : null,
            'channel_id' => ($context === 'community') ? $channelId : null, 
            'user_id' => ($context === 'community') ? $userId : null,
            'receiver_id' => ($context === 'private') ? $targetId : null
        ];

        // --- 6. Guardado (Redis Buffer o MySQL Directo) ---
        $savedToRedis = false;
        
        $isFirstMessage = false;
        if ($context === 'private') {
            $stmtCheckHistory = $pdo->prepare("SELECT id FROM private_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1");
            $stmtCheckHistory->execute([$userId, $targetId, $targetId, $userId]);
            if ($stmtCheckHistory->rowCount() === 0) {
                $isFirstMessage = true;
            }
        }

        if (isset($redis) && $redis && !$isFirstMessage) {
            try {
                if ($context === 'private') {
                    $minId = min($userId, $targetId);
                    $maxId = max($userId, $targetId);
                    $redisKey = "chat:buffer:private:$minId:$maxId";
                } else {
                    $redisKey = "chat:buffer:channel:$channelId";
                }
                $redis->rPush($redisKey, json_encode($messagePayload));
                $savedToRedis = true;
            } catch (Exception $e) {
                error_log("Redis Push Failed: " . $e->getMessage());
                $savedToRedis = false;
            }
        }

        if (!$savedToRedis) {
            if ($context === 'private') {
                $replyToId = null;
                if ($replyToUuid) {
                    $stmtRid = $pdo->prepare("SELECT id FROM private_messages WHERE uuid = ?");
                    $stmtRid->execute([$replyToUuid]);
                    $replyToId = $stmtRid->fetchColumn() ?: null;
                }
                $sql = "INSERT INTO private_messages (uuid, sender_id, receiver_id, message, type, reply_to_id, reply_to_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql)->execute([$messageUuid, $userId, $targetId, $messageText, $msgType, $replyToId, $replyToUuid]);
                $msgDbId = $pdo->lastInsertId();
                if (!empty($uploadedFiles)) {
                    $stmtAtt = $pdo->prepare("INSERT INTO private_message_attachments (message_id, file_id) VALUES (?, ?)");
                    foreach ($uploadedFiles as $file) $stmtAtt->execute([$msgDbId, $file['db_id']]);
                }
            } else {
                $replyToId = null;
                if ($replyToUuid) {
                    $stmtRid = $pdo->prepare("SELECT id FROM community_messages WHERE uuid = ?");
                    $stmtRid->execute([$replyToUuid]);
                    $replyToId = $stmtRid->fetchColumn() ?: null;
                }
                $sql = "INSERT INTO community_messages (uuid, community_id, channel_id, user_id, message, type, reply_to_id, reply_to_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql)->execute([$messageUuid, $targetId, $channelId, $userId, $messageText, $msgType, $replyToId, $replyToUuid]);
                $msgDbId = $pdo->lastInsertId();
                if (!empty($uploadedFiles)) {
                    $stmtAtt = $pdo->prepare("INSERT INTO community_message_attachments (message_id, file_id) VALUES (?, ?)");
                    foreach ($uploadedFiles as $file) $stmtAtt->execute([$msgDbId, $file['db_id']]);
                }
            }
            $messagePayload['id'] = $msgDbId; 
        }
        
        // --- 7. Notificación Websocket ---
        $socketType = ($context === 'private') ? 'private_message' : 'new_chat_message';
        $socketTarget = ($context === 'private') ? $targetId : 'community_broadcast';
        
        $extraData = ['message_data' => $messagePayload];
        if ($context === 'community') $extraData['community_id'] = $targetId;
        if ($context === 'private') $extraData['sender_id'] = $userId;

        send_live_notification($socketTarget, $socketType, $extraData);
        
        return ['success' => true, 'message' => 'Enviado'];
    }
}
?>