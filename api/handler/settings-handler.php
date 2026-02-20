<?php
// api/handler/settings-handler.php

use App\Api\Services\SettingsService;
use App\Core\Utils;

return function($dbConnection, $action) {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar que el usuario esté logueado
    if (!isset($_SESSION['user_id'])) {
        Utils::sendResponse(['success' => false, 'message' => 'No autorizado. Por favor inicia sesión.'], 401);
    }

    $settings = new SettingsService($dbConnection);
    $userId = $_SESSION['user_id'];

    switch ($action) {
        case 'upload_avatar':
            // En subida de archivos (FormData), usamos $_POST en lugar de JSON raw
            $token = $_POST['csrf_token'] ?? '';
            if (!Utils::validateCSRF($token)) {
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido).']);
            }
            if (!isset($_FILES['avatar'])) {
                Utils::sendResponse(['success' => false, 'message' => 'No se recibió ninguna imagen.']);
            }
            Utils::sendResponse($settings->uploadAvatar($userId, $_FILES['avatar']));
            break;

        case 'delete_avatar':
            // Aquí sí recibimos JSON normal
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) {
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido).']);
            }
            Utils::sendResponse($settings->deleteAvatar($userId));
            break;

        default:
            Utils::sendResponse(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
};
?>