<?php
// api/handler/auth-handler.php

use App\Api\Services\AuthService;
use App\Core\Utils;

// Retornamos una función anónima que recibe las dependencias inyectadas
return function($dbConnection, $action) {

    // Instanciamos el servicio pasando directamente la conexión segura inyectada
    $auth = new AuthService($dbConnection);

    // Obtener datos del cuerpo de la petición (JSON) usando Utils
    $data = Utils::getJsonInput();

    // --- VALIDACIÓN CSRF USANDO UTILS ---
    if (in_array($action, ['login', 'register', 'send_code', 'forgot_password', 'reset_password'])) {
        // Si el token no es válido, se bloquea la petición
        if (!Utils::validateCSRF($data->csrf_token ?? '')) {
            Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido). Recarga la página.']);
        }
    }
    // -----------------------

    switch ($action) {
        case 'check_email':
            if (!empty($data->email)) {
                if ($auth->checkEmail($data->email)) {
                    Utils::sendResponse(['success' => false, 'message' => 'El correo ya está registrado.']);
                } else {
                    Utils::sendResponse(['success' => true]);
                }
            } else {
                Utils::sendResponse(['success' => false, 'message' => 'El correo es requerido.']);
            }
            break;

        case 'send_code':
            if (!empty($data->username) && !empty($data->email) && !empty($data->password)) {
                Utils::sendResponse($auth->requestRegistrationCode($data));
            } else {
                Utils::sendResponse(['success' => false, 'message' => 'Faltan datos para procesar el registro.']);
            }
            break;

        case 'register':
            if (!empty($data->email) && !empty($data->code)) {
                Utils::sendResponse($auth->register($data));
            } else {
                Utils::sendResponse(['success' => false, 'message' => 'El código de verificación es requerido.']);
            }
            break;

        case 'login':
            if (!empty($data->email) && !empty($data->password)) {
                Utils::sendResponse($auth->login($data->email, $data->password));
            } else {
                Utils::sendResponse(['success' => false, 'message' => 'Datos incompletos.']);
            }
            break;

        case 'logout':
            Utils::sendResponse($auth->logout());
            break;

        case 'check_session':
            if (isset($_SESSION['user_id'])) {
                Utils::sendResponse([
                    'success' => true,
                    'user' => [
                        'name' => $_SESSION['user_name'],
                        'avatar' => $_SESSION['user_avatar']
                    ]
                ]);
            } else {
                Utils::sendResponse(['success' => false]);
            }
            break;

        case 'forgot_password':
            if (!empty($data->email)) {
                Utils::sendResponse($auth->requestPasswordReset($data->email));
            } else {
                Utils::sendResponse(['success' => false, 'message' => 'El correo es requerido.']);
            }
            break;

        case 'reset_password':
            if (!empty($data->token) && !empty($data->password)) {
                Utils::sendResponse($auth->resetPassword($data->token, $data->password));
            } else {
                Utils::sendResponse(['success' => false, 'message' => 'El token o la contraseña están vacíos.']);
            }
            break;

        default:
            Utils::sendResponse(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
};
?>