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
// Las interacciones modifican estado o leen datos sensibles, requieren protección
Utils::validateCsrf($i18n);

// 4. Inicializar Servicio
$interactionService = new InteractionService($pdo, $i18n, $redis);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'toggle_like':
        // Recibe el UUID del video y el tipo de interacción (like/dislike)
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        $type = trim($_POST['type'] ?? 'like'); 
        
        Utils::jsonResponse($interactionService->toggleLike($videoUuid, $type));
        break;

    case 'toggle_subscribe':
        // Recibe el UUID del canal (usuario) al que queremos suscribirnos
        $channelUuid = trim($_POST['channel_uuid'] ?? '');
        
        Utils::jsonResponse($interactionService->toggleSubscribe($channelUuid));
        break;

    case 'register_view':
        // Registra una visita (con debounce en Redis)
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        
        Utils::jsonResponse($interactionService->registerView($videoUuid));
        break;

    case 'register_share':
        // Registra que se ha compartido un video
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        
        Utils::jsonResponse($interactionService->registerShare($videoUuid));
        break;

    // ==========================================
    // SECCIÓN DE COMENTARIOS (NUEVO)
    // ==========================================

    case 'load_comments':
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
        $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

        // Validar límites para evitar sobrecarga
        if ($limit > 100) $limit = 100;

        Utils::jsonResponse($interactionService->getComments($videoUuid, $limit, $offset));
        break;

    case 'post_comment':
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $parentId = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) 
                    ? (int)$_POST['parent_id'] 
                    : null;

        Utils::jsonResponse($interactionService->addComment($videoUuid, $content, $parentId));
        break;

    default:
        Utils::jsonResponse([
            'success' => false, 
            'message' => $i18n->t('api.unknown_action')
        ], 400);
        break;
}
?>