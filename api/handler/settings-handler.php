<?php
// api/handler/settings-handler.php

use App\Api\Services\SettingsService;
use App\Core\Utils;

return function($dbConnection, $action) {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        Utils::sendResponse(['success' => false, 'message' => 'No autorizado. Por favor inicia sesión.'], 401);
    }

    $settings = new SettingsService($dbConnection);
    $userId = $_SESSION['user_id'];

    switch ($action) {
        case 'upload_avatar':
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
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) {
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido).']);
            }
            Utils::sendResponse($settings->deleteAvatar($userId));
            break;

        case 'update_field':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) {
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido).']);
            }
            if (empty($data->field) || !isset($data->value)) {
                Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']);
            }
            Utils::sendResponse($settings->updateField($userId, $data->field, $data->value));
            break;
            
        case 'request_email_change':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) {
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido).']);
            }
            if (empty($data->new_email)) {
                Utils::sendResponse(['success' => false, 'message' => 'El nuevo correo es requerido.']);
            }
            Utils::sendResponse($settings->requestEmailChange($userId, $data->new_email));
            break;

        case 'confirm_email_change':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) {
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido).']);
            }
            if (empty($data->code)) {
                Utils::sendResponse(['success' => false, 'message' => 'El código es requerido.']);
            }
            Utils::sendResponse($settings->confirmEmailChange($userId, $data->code));
            break;

        // NUEVO: Obtener preferencias del usuario
        case 'get_preferences':
            Utils::sendResponse(['success' => true, 'preferences' => $settings->getPreferences($userId)]);
            break;

        // NUEVO: Actualizar preferencia del usuario
        case 'update_preference':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) {
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido).']);
            }
            if (empty($data->field) || !isset($data->value)) {
                Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']);
            }
            Utils::sendResponse($settings->updatePreference($userId, $data->field, $data->value));
            break;

        default:
            Utils::sendResponse(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
};
?>