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

// Detectar si es JSON o FormData
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, "application/json") !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

$action = $data['action'] ?? '';
// En FormData el token viene en $_POST
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
    // --- ENVIAR MENSAJE (TEXTO E IMÁGENES) ---
    if ($action === 'send_message') {
        $uuid = $data['community_uuid'] ?? '';
        $messageText = trim($data['message'] ?? '');
        $replyToId = !empty($data['reply_to_id']) ? (int)$data['reply_to_id'] : null;
        
        if (empty($uuid)) throw new Exception("UUID requerido");

        // 1. Validar membresía
        $stmtC = $pdo->prepare("SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
        $stmtC->execute([$uuid, $userId]);
        $commId = $stmtC->fetchColumn();

        if (!$commId) throw new Exception("Acceso denegado.");

        // 2. Procesar Archivos
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
                        $uploadedFiles[] = [
                            'name' => $name,
                            'path' => $dbPath,
                            'mime' => $mime
                        ];
                    }
                }
            }
        }

        if (empty($messageText) && empty($uploadedFiles)) {
            throw new Exception("El mensaje no puede estar vacío.");
        }

        // 3. Insertar Mensaje
        $pdo->beginTransaction();
        try {
            $msgType = (!empty($uploadedFiles)) ? (empty($messageText) ? 'image' : 'mixed') : 'text';
            
            $stmtInsert = $pdo->prepare("INSERT INTO community_messages (community_id, user_id, message, type, reply_to_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmtInsert->execute([$commId, $userId, $messageText, $msgType, $replyToId]);
            $msgId = $pdo->lastInsertId();

            // Insertar adjuntos
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
            $pdo->commit();

            // 4. Preparar Payload para Python
            // Necesitamos los datos del usuario para el frontend
            $stmtUser = $pdo->prepare("SELECT username, profile_picture, role FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

            // Si es respuesta, obtener datos del padre
            $replyData = [];
            if ($replyToId) {
                // [MODIFICADO] Obtenemos type y contamos adjuntos
                $stmtRep = $pdo->prepare("SELECT m.message, m.type, u.username FROM community_messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
                $stmtRep->execute([$replyToId]);
                $rRow = $stmtRep->fetch(PDO::FETCH_ASSOC);
                if ($rRow) {
                    $replyData = [
                        'message' => $rRow['message'], 
                        'sender_username' => $rRow['username'],
                        'type' => $rRow['type']
                    ];
                    
                    // Contar adjuntos del mensaje padre
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM community_message_attachments WHERE message_id = ?");
                    $stmtCount->execute([$replyToId]);
                    $replyData['attachment_count'] = (int)$stmtCount->fetchColumn();
                }
            }

            $broadcastPayload = [
                'id' => $msgId,
                'community_uuid' => $uuid,
                'message' => $messageText,
                'sender_id' => $userId,
                'sender_username' => $userData['username'],
                'sender_profile_picture' => $userData['profile_picture'],
                'sender_role' => $userData['role'],
                'created_at' => date('c'), // ISO 8601
                'type' => $msgType,
                'reply_to_id' => $replyToId,
                'reply_message' => $replyData['message'] ?? null,
                'reply_sender_username' => $replyData['sender_username'] ?? null,
                'reply_type' => $replyData['type'] ?? null,
                'reply_attachment_count' => $replyData['attachment_count'] ?? 0, // [NUEVO]
                'attachments' => $uploadedFiles 
            ];

            // Enviar a Python para Broadcast
            send_live_notification('community_broadcast', 'new_chat_message', [
                'community_id' => $commId, 
                'message_data' => $broadcastPayload
            ]);

            echo json_encode(['success' => true, 'message' => 'Enviado']);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    // --- OBTENER MENSAJES (CORREGIDO PARA GROUP_CONCAT y CONTEO) ---
    } elseif ($action === 'get_messages') {
        $uuid = $data['community_uuid'] ?? '';
        $limit = isset($data['limit']) ? (int)$data['limit'] : 50;
        
        $stmtC = $pdo->prepare("SELECT c.id FROM communities c JOIN community_members cm ON c.id = cm.community_id WHERE c.uuid = ? AND cm.user_id = ?");
        $stmtC->execute([$uuid, $userId]);
        $commId = $stmtC->fetchColumn();

        if (!$commId) throw new Exception("Acceso denegado");

        // [MODIFICADO] Usamos GROUP_CONCAT para compatibilidad y contamos adjuntos de la respuesta (p)
        $sql = "
            SELECT m.id, m.message, m.created_at, m.type, m.reply_to_id,
                   u.id as sender_id, u.username as sender_username, u.profile_picture as sender_profile_picture, u.role as sender_role,
                   p.message as reply_message, p.type as reply_type, pu.username as reply_sender_username,
                   (SELECT COUNT(*) FROM community_message_attachments WHERE message_id = p.id) as reply_attachment_count,
                   (
                       SELECT GROUP_CONCAT(
                           CONCAT('{\"path\":\"', f.file_path, '\",\"type\":\"', f.file_type, '\"}')
                           SEPARATOR ','
                       )
                       FROM community_message_attachments cma
                       JOIN community_files f ON cma.file_id = f.id
                       WHERE cma.message_id = m.id
                   ) as attachments_json
            FROM community_messages m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN community_messages p ON m.reply_to_id = p.id
            LEFT JOIN users pu ON p.user_id = pu.id
            WHERE m.community_id = ?
            ORDER BY m.created_at DESC LIMIT $limit
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$commId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decodificar JSON de adjuntos
        foreach ($messages as &$msg) {
            if (!empty($msg['attachments_json'])) {
                // Envolver en corchetes para hacerlo un array JSON válido
                $jsonString = '[' . $msg['attachments_json'] . ']';
                $msg['attachments'] = json_decode($jsonString, true);
            } else {
                $msg['attachments'] = [];
            }
            unset($msg['attachments_json']);
        }

        echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);

    } else {
        throw new Exception(translation('global.action_invalid'));
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>