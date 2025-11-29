<?php
// api/chat_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/chat_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/i18n_server.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, "application/json") !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => translation('global.error_csrf')]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => translation('global.session_expired')]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // --- ENVIAR MENSAJE ---
    if ($action === 'send_message') {
        $uuid = $data['target_uuid'] ?? $data['community_uuid'] ?? ''; 
        $context = $data['context'] ?? 'community'; 
        $messageText = trim($data['message'] ?? '');
        $replyToId = !empty($data['reply_to_id']) ? (int)$data['reply_to_id'] : null;
        
        if (empty($uuid)) throw new Exception("UUID destino requerido");

        $targetId = null;
        if ($context === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $targetId = $stmtU->fetchColumn();
            if (!$targetId || $targetId == $userId) throw new Exception("Usuario inválido.");

            // --- [PRIVACIDAD REFORZADA: BLOQUEO EN SERVIDOR] ---
            $stmtPriv = $pdo->prepare("
                SELECT COALESCE(up.message_privacy, 'friends') as privacy,
                       (SELECT status FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) as status
                FROM users u
                LEFT JOIN user_preferences up ON u.id = up.user_id
                WHERE u.id = ?
            ");
            $stmtPriv->execute([$userId, $targetId, $targetId, $userId, $targetId]);
            $res = $stmtPriv->fetch(PDO::FETCH_ASSOC);
            
            $privacy = $res['privacy'] ?? 'friends';
            $status = $res['status']; 

            if ($privacy === 'nobody') {
                throw new Exception(translation('chat.error.privacy_block') ?? "Privacidad: Usuario no recibe mensajes.");
            }
            if ($privacy === 'friends' && $status !== 'accepted') {
                throw new Exception(translation('chat.error.privacy_block') ?? "Privacidad: Solo amigos.");
            }
            // ----------------------------------------

        } else {
            $stmtC = $pdo->prepare("SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
            $stmtC->execute([$uuid, $userId]);
            $targetId = $stmtC->fetchColumn();
            if (!$targetId) throw new Exception("Acceso denegado a la comunidad.");
        }

        $uploadedFiles = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $files = $_FILES['attachments'];
            $count = count($files['name']);
            if ($count > 4) throw new Exception("Máximo 4 imágenes permitidas.");

            $uploadDir = __DIR__ . '/../public/assets/uploads/chat/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$i];
                    $name = basename($files['name'][$i]);
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmpName);

                    if (!str_starts_with($mime, 'image/')) throw new Exception("Solo se permiten imágenes.");

                    $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'png';
                    $newFileName = generate_uuid() . '.' . $ext;
                    $targetPath = $uploadDir . $newFileName;
                    $dbPath = 'assets/uploads/chat/' . $newFileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $uploadedFiles[] = ['name' => $name, 'path' => $dbPath, 'mime' => $mime];
                    }
                }
            }
        }

        if (empty($messageText) && empty($uploadedFiles)) throw new Exception("El mensaje no puede estar vacío.");

        $pdo->beginTransaction();
        try {
            $msgType = (!empty($uploadedFiles)) ? (empty($messageText) ? 'image' : 'mixed') : 'text';
            $msgId = 0;

            if ($context === 'private') {
                $stmtInsert = $pdo->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, type, reply_to_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmtInsert->execute([$userId, $targetId, $messageText, $msgType, $replyToId]);
                $msgId = $pdo->lastInsertId();

                if (!empty($uploadedFiles)) {
                    $stmtFile = $pdo->prepare("INSERT INTO community_files (uuid, uploader_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?, ?)");
                    $stmtAttach = $pdo->prepare("INSERT INTO private_message_attachments (message_id, file_id) VALUES (?, ?)");
                    foreach ($uploadedFiles as $file) {
                        $fileUuid = generate_uuid();
                        $stmtFile->execute([$fileUuid, $userId, $file['path'], $file['name'], $file['mime']]);
                        $fileId = $pdo->lastInsertId();
                        $stmtAttach->execute([$msgId, $fileId]);
                    }
                }
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO community_messages (community_id, user_id, message, type, reply_to_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmtInsert->execute([$targetId, $userId, $messageText, $msgType, $replyToId]);
                $msgId = $pdo->lastInsertId();

                if (!empty($uploadedFiles)) {
                    $stmtFile = $pdo->prepare("INSERT INTO community_files (uuid, uploader_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?, ?)");
                    $stmtAttach = $pdo->prepare("INSERT INTO community_message_attachments (message_id, file_id) VALUES (?, ?)");
                    foreach ($uploadedFiles as $file) {
                        $fileUuid = generate_uuid();
                        $stmtFile->execute([$fileUuid, $userId, $file['path'], $file['name'], $file['mime']]);
                        $fileId = $pdo->lastInsertId();
                        $stmtAttach->execute([$msgId, $fileId]);
                    }
                }
            }
            $pdo->commit();

            // Preparar broadcast
            $stmtUser = $pdo->prepare("SELECT uuid, username, profile_picture, role FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

            $broadcastPayload = [
                'id' => $msgId,
                'target_uuid' => $uuid, // UUID destino
                'context' => $context,
                'message' => $messageText,
                'sender_id' => $userId,
                'sender_uuid' => $userData['uuid'],
                'sender_username' => $userData['username'],
                'sender_profile_picture' => $userData['profile_picture'],
                'sender_role' => $userData['role'],
                'created_at' => date('c'),
                'type' => $msgType,
                'status' => 'active',
                'reply_to_id' => $replyToId,
                'attachments' => $uploadedFiles 
            ];

            $socketType = ($context === 'private') ? 'private_message' : 'new_chat_message';
            $socketTarget = ($context === 'private') ? $targetId : 'community_broadcast';
            
            $extraData = ['message_data' => $broadcastPayload];
            if ($context === 'community') $extraData['community_id'] = $targetId;
            if ($context === 'private') $extraData['sender_id'] = $userId;

            send_live_notification($socketTarget, $socketType, $extraData);

            echo json_encode(['success' => true, 'message' => 'Enviado']);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    // --- OBTENER MENSAJES ---
    } elseif ($action === 'get_messages') {
        $uuid = $data['target_uuid'] ?? $data['community_uuid'] ?? '';
        $context = $data['context'] ?? 'community';
        $limit = isset($data['limit']) ? (int)$data['limit'] : 50;
        $offset = isset($data['offset']) ? (int)$data['offset'] : 0;
        
        $targetId = null;

        if ($context === 'private') {
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$uuid]);
            $targetId = $stmtU->fetchColumn();
            if (!$targetId) throw new Exception("Usuario no encontrado");

            // Marcar como leídos al obtener historial
            $stmtRead = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
            $stmtRead->execute([$targetId, $userId]);

            $stmtClear = $pdo->prepare("SELECT cleared_at FROM private_chat_clearance WHERE user_id = ? AND partner_id = ?");
            $stmtClear->execute([$userId, $targetId]);
            $clearedAt = $stmtClear->fetchColumn(); 
            $clearedAt = $clearedAt ? $clearedAt : '1970-01-01 00:00:00';

            $sql = "
                SELECT m.id, m.message, m.created_at, m.type, m.reply_to_id, m.status, m.sender_id,
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
            $stmtC = $pdo->prepare("SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
            $stmtC->execute([$uuid, $userId]);
            $targetId = $stmtC->fetchColumn();
            if (!$targetId) throw new Exception("Acceso denegado");

            $stmtRead = $pdo->prepare("UPDATE community_members SET last_read_at = NOW() WHERE community_id = ? AND user_id = ?");
            $stmtRead->execute([$targetId, $userId]);

            $sql = "
                SELECT m.id, m.message, m.created_at, m.type, m.reply_to_id, m.status, m.user_id as sender_id,
                       u.username as sender_username, u.profile_picture as sender_profile_picture, u.role as sender_role,
                       p.message as reply_message, p.type as reply_type, pu.username as reply_sender_username,
                       (SELECT COUNT(*) FROM community_message_attachments WHERE message_id = p.id) as reply_attachment_count,
                       (SELECT GROUP_CONCAT(CONCAT('{\"path\":\"', f.file_path, '\",\"type\":\"', f.file_type, '\"}') SEPARATOR ',') FROM community_message_attachments cma JOIN community_files f ON cma.file_id = f.id WHERE cma.message_id = m.id) as attachments_json
                FROM community_messages m
                JOIN users u ON m.user_id = u.id
                LEFT JOIN community_messages p ON m.reply_to_id = p.id
                LEFT JOIN users pu ON p.user_id = pu.id
                WHERE m.community_id = ?
                ORDER BY m.created_at DESC 
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$targetId]);
        }
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as &$msg) {
            if ($msg['status'] === 'deleted') {
                $msg['message'] = null; 
                $msg['attachments'] = []; 
            } else {
                if (!empty($msg['attachments_json'])) {
                    $jsonString = '[' . $msg['attachments_json'] . ']';
                    $msg['attachments'] = json_decode($jsonString, true);
                } else {
                    $msg['attachments'] = [];
                }
            }
            unset($msg['attachments_json']);
        }

        echo json_encode(['success' => true, 'messages' => array_reverse($messages), 'has_more' => (count($messages) >= $limit)]);

    // --- MARCAR COMO LEÍDO (Real-time) [NUEVO] ---
    } elseif ($action === 'mark_as_read') {
        $targetUuid = $data['target_uuid'] ?? ''; // El UUID del que envió los mensajes (el otro usuario)
        $context = $data['context'] ?? 'private';

        if (empty($targetUuid)) throw new Exception("UUID requerido");

        if ($context === 'private') {
            // 1. Obtener ID del otro usuario
            $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
            $stmtU->execute([$targetUuid]);
            $senderId = $stmtU->fetchColumn();

            if ($senderId) {
                // 2. Actualizar DB: Marcar como leídos los mensajes que este usuario me envió
                $stmtUpd = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                $stmtUpd->execute([$senderId, $userId]);
                
                // Si hubo cambios, notificar al remitente (Sender) que sus mensajes fueron leídos
                if ($stmtUpd->rowCount() > 0) {
                    send_live_notification($senderId, 'messages_read', [
                        'reader_id' => $userId, // Yo (quien leyó)
                        'context'   => 'private'
                    ]);
                }
            }
        } else {
            // Lógica para comunidades (actualizar last_read_at)
            $stmtC = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
            $stmtC->execute([$targetUuid]);
            $commId = $stmtC->fetchColumn();

            if ($commId) {
                $pdo->prepare("UPDATE community_members SET last_read_at = NOW() WHERE community_id = ? AND user_id = ?")
                    ->execute([$commId, $userId]);
            }
        }

        echo json_encode(['success' => true]);

    // --- ELIMINAR MENSAJE INDIVIDUAL ---
    } elseif ($action === 'delete_message') {
        $msgId = (int)($data['message_id'] ?? 0);
        $context = $data['context'] ?? 'community';
        
        if (!$msgId) throw new Exception(translation('global.action_invalid'));

        $table = ($context === 'private') ? 'private_messages' : 'community_messages';
        $userCol = ($context === 'private') ? 'sender_id' : 'user_id';

        $stmt = $pdo->prepare("SELECT id, created_at, status FROM $table WHERE id = ? AND $userCol = ?");
        $stmt->execute([$msgId, $userId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$msg) throw new Exception("Mensaje no encontrado o no tienes permiso.");
        if ($msg['status'] === 'deleted') throw new Exception("Ya eliminado.");

        $msgTime = strtotime($msg['created_at']);
        if (time() - $msgTime > (24 * 3600)) throw new Exception(translation('chat.error.delete_timeout') ?? 'Tiempo expirado para borrar');

        $pdo->prepare("UPDATE $table SET status = 'deleted', message = '', type = 'text' WHERE id = ?")->execute([$msgId]);
        
        $eventType = ($context === 'private') ? 'private_message_deleted' : 'message_deleted';
        
        if ($context === 'private') {
            $stmtRec = $pdo->prepare("SELECT receiver_id FROM private_messages WHERE id = ?");
            $stmtRec->execute([$msgId]);
            $receiverId = $stmtRec->fetchColumn();
            
            send_live_notification($receiverId, $eventType, ['message_id' => $msgId, 'sender_id' => $userId]);
            send_live_notification($userId, $eventType, ['message_id' => $msgId, 'sender_id' => $userId]);
        } else {
            $stmtComm = $pdo->prepare("SELECT community_id FROM community_messages WHERE id = ?");
            $stmtComm->execute([$msgId]);
            $commId = $stmtComm->fetchColumn();
            
            send_live_notification('community_broadcast', $eventType, [
                'message_data' => ['message_id' => $msgId, 'sender_id' => $userId],
                'community_id' => $commId
            ]);
        }

        echo json_encode(['success' => true, 'message' => translation('chat.message_deleted')]);

    // --- ELIMINAR CONVERSACIÓN (SOLO PARA MI) ---
    } elseif ($action === 'delete_conversation') {
        $uuid = $data['target_uuid'] ?? '';
        
        if (empty($uuid)) throw new Exception(translation('global.action_invalid'));

        // 1. Obtener ID del partner
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE uuid = ?");
        $stmtU->execute([$uuid]);
        $partnerId = $stmtU->fetchColumn();

        if (!$partnerId) throw new Exception("Usuario no encontrado.");

        // 2. Insertar o actualizar la fecha de limpieza
        $sql = "INSERT INTO private_chat_clearance (user_id, partner_id, cleared_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE cleared_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $partnerId]);

        echo json_encode(['success' => true, 'message' => 'Chat eliminado de tu lista.']);

    // --- REPORTAR MENSAJE ---
    } elseif ($action === 'report_message') {
        $msgId = (int)($data['message_id'] ?? 0);
        $reason = trim($data['reason'] ?? '');
        $context = $data['context'] ?? 'community';

        if (!$msgId || empty($reason)) throw new Exception(translation('admin.error.reason_required'));

        $table = ($context === 'private') ? 'private_messages' : 'community_messages';
        
        $stmtCheck = $pdo->prepare("SELECT id FROM $table WHERE id = ?");
        $stmtCheck->execute([$msgId]);
        if (!$stmtCheck->fetch()) throw new Exception("Mensaje no existe.");

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

        echo json_encode(['success' => true, 'message' => translation('chat.report_success')]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>