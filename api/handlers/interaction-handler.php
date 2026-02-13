<?php
// api/handlers/interaction-handler.php

// 1. BOOTSTRAP: Subimos dos niveles para llegar a includes
$services = require_once __DIR__ . '/../../includes/bootstrap.php';

// 2. Extraer servicios necesarios
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

// Usaremos este Namespace en la Fase 2
use Aurora\Services\InteractionService;
use Aurora\Libs\Utils;

// 3. Validar CSRF (Seguridad estándar de Aurora)
// Las interacciones modifican estado, así que requieren protección CSRF
Utils::validateCsrf($i18n);

// 4. Inicializar Servicio
// NOTA: InteractionService se creará en la Fase 2
$interactionService = new InteractionService($pdo, $i18n, $redis);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'toggle_like':
        // Recibe el UUID del video y el tipo de interacción (like/dislike) opcional
        // Si type no se envía, se asume "like" por defecto o toggle simple
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
        // Registra una visita. Debe llamarse tras unos segundos de reproducción (debounce en frontend)
        $videoUuid = trim($_POST['video_uuid'] ?? '');
        
        Utils::jsonResponse($interactionService->registerView($videoUuid));
        break;

    default:
        Utils::jsonResponse([
            'success' => false, 
            'message' => $i18n->t('api.unknown_action')
        ], 400);
        break;
}
?>