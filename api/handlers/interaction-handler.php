<?php
// api/handlers/interaction-handler.php

// 1. BOOTSTRAP: Subimos dos niveles para llegar a includes
$services = require_once __DIR__ . '/../../includes/bootstrap.php';

// 2. Extraer servicios necesarios
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

use Aurora\Services\InteractionService;
use Aurora\Libs\Utils;

// 3. Validar CSRF (Seguridad estándar de Aurora)
Utils::validateCsrf($i18n);

// 4. Inicializar Servicio
$interactionService = new InteractionService($pdo, $i18n, $redis);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'toggle_like':
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        $type = trim($_POST['type'] ?? 'like'); 
        Utils::jsonResponse($interactionService->toggleLike($videoUuid, $type));
        break;

    case 'toggle_subscribe':
        $channelUuid = trim($_POST['channel_uuid'] ?? '');
        Utils::jsonResponse($interactionService->toggleSubscribe($channelUuid));
        break;

    case 'register_view':
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        Utils::jsonResponse($interactionService->registerView($videoUuid));
        break;

    case 'register_share':
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        Utils::jsonResponse($interactionService->registerShare($videoUuid));
        break;

    // ==========================================
    // SECCIÓN DE COMENTARIOS
    // ==========================================

    case 'load_comments':
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
        $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
        if ($limit > 100) $limit = 100;
        Utils::jsonResponse($interactionService->getComments($videoUuid, $limit, $offset));
        break;

    case 'post_comment':
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        // FIX: Ya NO castear a int porque parent_id puede ser un UUID si es una respuesta a un comentario reciente
        $parentId = $_POST['parent_id'] ?? null;
        if ($parentId === 'null' || $parentId === '') $parentId = null;

        Utils::jsonResponse($interactionService->addComment($videoUuid, $content, $parentId));
        break;

    case 'toggle_comment_like':
        // FIX: Eliminar (int) casting. Recibimos UUID string.
        $commentId = trim($_POST['comment_id'] ?? '');
        $type = trim($_POST['type'] ?? 'like');

        if (empty($commentId)) {
            Utils::jsonResponse(['success' => false, 'message' => 'ID de comentario inválido'], 400);
        } else {
            Utils::jsonResponse($interactionService->toggleCommentLike($commentId, $type));
        }
        break;

    default:
        Utils::jsonResponse([
            'success' => false, 
            'message' => $i18n->t('api.unknown_action')
        ], 400);
        break;
}
?>