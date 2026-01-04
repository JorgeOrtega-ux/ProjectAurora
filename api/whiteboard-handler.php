<?php
// api/whiteboard-handler.php
require_once __DIR__ . '/../includes/bootstrap.php'; // Tu bootstrap existente
require_once __DIR__ . '/services/WhiteboardService.php';

Utils::validateCsrf($i18n); // Si usas tokens CSRF

if (!isset($_SESSION['user_id'])) {
    Utils::jsonResponse(['success' => false, 'message' => 'No autorizado']);
}

$wbService = new WhiteboardService($pdo);
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $name = trim($_POST['name'] ?? 'Sin título');
        Utils::jsonResponse($wbService->create($_SESSION['user_id'], $name));
        break;

    case 'list':
        Utils::jsonResponse($wbService->listByUser($_SESSION['user_id']));
        break;

    case 'load':
        $uuid = $_POST['uuid'] ?? '';
        Utils::jsonResponse($wbService->load($uuid, $_SESSION['user_id']));
        break;

    case 'save':
        $uuid = $_POST['uuid'] ?? '';
        $data = $_POST['data'] ?? '[]'; // JSON String
        $isBeacon = isset($_POST['beacon']) && $_POST['beacon'] === 'true';
        
        if ($isBeacon) {
            Utils::jsonResponse($wbService->forceSaveToDisk($uuid, $data));
        } else {
            Utils::jsonResponse($wbService->save($uuid, $data));
        }
        break;

    default:
        Utils::jsonResponse(['success' => false, 'message' => 'Acción desconocida']);
}
?>