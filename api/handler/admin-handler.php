<?php
// api/handler/admin-handler.php

use App\Api\Services\AdminService;
use App\Core\Utils;
use App\Core\Logger;

return function($dbConnection, $action) {

    // 1. Validar que la sesión esté activa
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        Utils::sendResponse(['success' => false, 'message' => 'No autorizado.'], 401);
    }

    // 2. Middleware de Administrador (Bloquea a usuarios normales o moderadores)
    if (!in_array($_SESSION['user_role'], ['administrator', 'founder'])) {
        Logger::system("Intento de acceso denegado al admin-handler por el usuario ID: " . $_SESSION['user_id'], Logger::LEVEL_WARNING);
        Utils::sendResponse(['success' => false, 'message' => 'Permisos insuficientes para realizar esta acción.'], 403);
    }

    // 3. Inicializar el servicio de Admin
    $adminService = new AdminService($dbConnection);
    $adminId = $_SESSION['user_id'];

    // Para la subida de archivos (multipart/form-data) procesamos $_POST, para lo demás el JSON raw
    $data = Utils::getJsonInput();

    // 4. Validar el token CSRF siempre
    $csrfToken = $_POST['csrf_token'] ?? $data->csrf_token ?? '';
    if (!Utils::validateCSRF($csrfToken)) {
        Logger::system("Fallo de validación CSRF en admin-handler para el Admin ID: $adminId", Logger::LEVEL_WARNING);
        Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad CSRF.']);
    }

    switch ($action) {
        case 'update_avatar':
            $targetUuid = $_POST['target_uuid'] ?? '';
            if (empty($targetUuid)) Utils::sendResponse(['success' => false, 'message' => 'UUID objetivo no proporcionado.']);
            if (!isset($_FILES['avatar'])) Utils::sendResponse(['success' => false, 'message' => 'No se recibió ninguna imagen.']);
            
            Utils::sendResponse($adminService->updateAvatar($targetUuid, $_FILES['avatar'], $adminId));
            break;

        case 'delete_avatar':
            if (empty($data->target_uuid)) Utils::sendResponse(['success' => false, 'message' => 'UUID objetivo requerido.']);
            Utils::sendResponse($adminService->deleteAvatar($data->target_uuid, $adminId));
            break;

        case 'update_field':
            if (empty($data->target_uuid) || empty($data->field) || !isset($data->value)) {
                Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']);
            }
            Utils::sendResponse($adminService->updateField($data->target_uuid, $data->field, $data->value, $adminId));
            break;

        case 'update_preference':
            if (empty($data->target_uuid) || empty($data->field) || !isset($data->value)) {
                Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']);
            }
            Utils::sendResponse($adminService->updatePreference($data->target_uuid, $data->field, $data->value, $adminId));
            break;

        default:
            Utils::sendResponse(['success' => false, 'message' => 'Acción administrativa no válida.']);
            break;
    }
};
?>