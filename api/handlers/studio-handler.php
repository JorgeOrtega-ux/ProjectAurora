<?php
// api/handlers/studio-handler.php

$services = require_once __DIR__ . '/../../includes/bootstrap.php';
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

use Aurora\Services\StudioService;
use Aurora\Libs\Utils;

// 1. Validar Sesión (Solo usuarios logueados)
if (!isset($_SESSION['user_id'])) {
    Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.session_expired')]);
}

// 2. Validar CSRF
Utils::validateCsrf($i18n);

// 3. Inicializar Servicio
$studioService = new StudioService($pdo, $redis, $i18n, $_SESSION['user_id']);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'init_upload':
        $batchId = $_POST['batch_id'] ?? '';
        $fileName = $_POST['file_name'] ?? 'unknown.mp4';
        Utils::jsonResponse($studioService->initUpload($batchId, $fileName));
        break;

    case 'upload_chunk':
        $uuid = $_POST['video_uuid'] ?? '';
        $chunkIndex = $_POST['chunk_index'] ?? 0;
        $isLast = isset($_POST['is_last']) && $_POST['is_last'] === 'true';
        // El archivo viene en $_FILES['chunk']
        Utils::jsonResponse($studioService->uploadChunk($uuid, $_FILES['chunk'], $chunkIndex, $isLast));
        break;

    case 'upload_thumbnail':
        $uuid = $_POST['video_uuid'] ?? '';
        Utils::jsonResponse($studioService->uploadThumbnail($uuid, $_FILES['thumbnail']));
        break;

    case 'save_metadata':
        $uuid = $_POST['video_uuid'] ?? '';
        $data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'publish' => isset($_POST['publish']) && $_POST['publish'] === 'true'
        ];
        Utils::jsonResponse($studioService->saveMetadata($uuid, $data));
        break;

    case 'get_pending':
        Utils::jsonResponse($studioService->getPendingVideos());
        break;

    case 'generate_thumbnails':
        $uuid = $_POST['video_uuid'] ?? '';
        Utils::jsonResponse($studioService->requestAutoThumbnails($uuid));
        break;

    case 'cancel_batch':
        $batchId = $_POST['batch_id'] ?? '';
        Utils::jsonResponse($studioService->cancelBatch($batchId));
        break;

    // [NUEVO] Eliminación granular de video
    case 'delete_video':
        $uuid = $_POST['video_uuid'] ?? '';
        Utils::jsonResponse($studioService->deleteVideo($uuid));
        break;

    default:
        Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>