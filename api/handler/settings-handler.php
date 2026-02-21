<?php
// api/handler/settings-handler.php

use App\Api\Services\SettingsService;
use App\Core\Utils;
use App\Core\Logger;

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
                Logger::system("Fallo de validación CSRF en upload_avatar para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (!isset($_FILES['avatar'])) { Utils::sendResponse(['success' => false, 'message' => 'No se recibió ninguna imagen.']); }
            Utils::sendResponse($settings->uploadAvatar($userId, $_FILES['avatar']));
            break;

        case 'delete_avatar':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en delete_avatar para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            Utils::sendResponse($settings->deleteAvatar($userId));
            break;

        case 'update_field':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en update_field para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->field) || !isset($data->value)) { Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']); }
            Utils::sendResponse($settings->updateField($userId, $data->field, $data->value));
            break;
            
        case 'request_email_change':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en request_email_change para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->new_email)) { Utils::sendResponse(['success' => false, 'message' => 'El nuevo correo es requerido.']); }
            Utils::sendResponse($settings->requestEmailChange($userId, $data->new_email));
            break;

        case 'confirm_email_change':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en confirm_email_change para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->code)) { Utils::sendResponse(['success' => false, 'message' => 'El código es requerido.']); }
            Utils::sendResponse($settings->confirmEmailChange($userId, $data->code));
            break;

        case 'get_preferences':
            Utils::sendResponse(['success' => true, 'preferences' => $settings->getPreferences($userId)]);
            break;

        case 'update_preference':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en update_preference para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->field) || !isset($data->value)) { Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']); }
            Utils::sendResponse($settings->updatePreference($userId, $data->field, $data->value));
            break;

        case 'verify_password':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en verify_password para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->password)) { Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']); }
            Utils::sendResponse($settings->verifyPassword($userId, $data->password));
            break;

        case 'update_password':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en update_password para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->current_password) || empty($data->new_password)) { Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']); }
            Utils::sendResponse($settings->updatePassword($userId, $data->current_password, $data->new_password));
            break;

        // --- MANEJADORES DE 2FA ---
        case '2fa_init':
            Utils::sendResponse($settings->init2FA($userId));
            break;

        case '2fa_enable':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en 2fa_enable para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->code)) { Utils::sendResponse(['success' => false, 'message' => 'Código requerido.']); }
            Utils::sendResponse($settings->enable2FA($userId, $data->code));
            break;

        case '2fa_disable':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en 2fa_disable para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->password)) { Utils::sendResponse(['success' => false, 'message' => 'Contraseña requerida.']); }
            Utils::sendResponse($settings->disable2FA($userId, $data->password));
            break;

        case '2fa_regen':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en 2fa_regen para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->password)) { Utils::sendResponse(['success' => false, 'message' => 'Contraseña requerida.']); }
            Utils::sendResponse($settings->regenerate2FACodes($userId, $data->password));
            break;

        // --- MANEJADORES DE DISPOSITIVOS (SESIONES) ---
        case 'get_devices':
            Utils::sendResponse($settings->getDevices($userId, session_id()));
            break;

        case 'revoke_device':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en revoke_device para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            if (empty($data->session_id)) { Utils::sendResponse(['success' => false, 'message' => 'ID de sesión requerido.']); }
            Utils::sendResponse($settings->revokeDevice($userId, $data->session_id));
            break;

        case 'revoke_all_devices':
            $data = Utils::getJsonInput();
            if (!Utils::validateCSRF($data->csrf_token ?? '')) { 
                Logger::system("Fallo de validación CSRF en revoke_all_devices para User ID: $userId", Logger::LEVEL_WARNING);
                Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad.']); 
            }
            Utils::sendResponse($settings->revokeAllOtherDevices($userId, session_id()));
            break;

        default:
            Utils::sendResponse(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
};
?>