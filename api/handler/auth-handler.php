<?php
// api/handler/auth-handler.php

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../services/AuthService.php';

$database = new Database();
$db = $database->getConnection();
$auth = new AuthService($db);

// Obtener datos del cuerpo de la petición (JSON) usando Utils
$data = Utils::getJsonInput();

// --- VALIDACIÓN CSRF USANDO UTILS ---
if (in_array($action, ['login', 'register', 'send_code'])) {
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

    default:
        Utils::sendResponse(['success' => false, 'message' => 'Acción no válida.']);
        break;
}
?>