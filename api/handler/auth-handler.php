<?php
// api/handler/auth-handler.php

use App\Api\Services\AuthService;
use App\Core\Utils;

// --- FUNCIONES DE VALIDACIÓN DE SEGURIDAD DINÁMICAS ---

function validateEmailAuth($email) {
    global $APP_CONFIG;
    if (strlen($email) > 254) return false;
    
    $parts = explode('@', $email);
    if (count($parts) !== 2) return false;
    
    $local = $parts[0];
    $domain = strtolower($parts[1]);
    
    // Configuración desde BD
    $minLocal = (int)($APP_CONFIG['min_email_local_length'] ?? 4);
    $maxLocal = (int)($APP_CONFIG['max_email_local_length'] ?? 64);
    
    if (strlen($local) < $minLocal || strlen($local) > $maxLocal) return false;
    
    // Validación de dominios dinámicos permitidos
    $allowedDomainsStr = $APP_CONFIG['allowed_email_domains'] ?? 'gmail.com,outlook.com,icloud.com,hotmail.com,yahoo.com';
    $allowedDomains = array_map('trim', explode(',', strtolower($allowedDomainsStr)));
    
    if (!in_array($domain, $allowedDomains)) return false;
    
    return true;
}

function validatePasswordAuth($password) {
    global $APP_CONFIG;
    $len = strlen($password);
    $min = (int)($APP_CONFIG['min_password_length'] ?? 12);
    $max = (int)($APP_CONFIG['max_password_length'] ?? 64);
    return $len >= $min && $len <= $max;
}

function validateUsernameAuth($username) {
    global $APP_CONFIG;
    $len = strlen(trim($username));
    $min = (int)($APP_CONFIG['min_username_length'] ?? 3);
    $max = (int)($APP_CONFIG['max_username_length'] ?? 32);
    return $len >= $min && $len <= $max;
}

// --------------------------------------------

// Retornamos una función anónima que recibe las dependencias inyectadas
return function($dbConnection, $action) {

    // Instanciamos el servicio pasando directamente la conexión segura inyectada
    $auth = new AuthService($dbConnection);

    // Obtener datos del cuerpo de la petición (JSON) usando Utils
    $data = Utils::getJsonInput();

    // --- VALIDACIÓN CSRF USANDO UTILS ---
    if (in_array($action, ['login', 'register', 'send_code', 'forgot_password', 'reset_password', 'verify_2fa'])) {
        // Si el token no es válido, se bloquea la petición
        if (!Utils::validateCSRF($data->csrf_token ?? '')) {
            Utils::sendResponse(['success' => false, 'message' => 'Error de seguridad (Token inválido). Recarga la página.']);
        }
    }
    // -----------------------

    switch ($action) {
        case 'check_email':
            if (!empty($data->email)) {
                if (!validateEmailAuth($data->email)) {
                    Utils::sendResponse(['success' => false, 'message' => 'js.auth.err_email_invalid']);
                }

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
                // Validación estricta antes de enviar código
                if (!validateEmailAuth($data->email)) {
                    Utils::sendResponse(['success' => false, 'message' => 'js.auth.err_email_invalid']);
                }
                if (!validatePasswordAuth($data->password)) {
                    Utils::sendResponse(['success' => false, 'message' => 'js.auth.err_pass_length']);
                }
                if (!validateUsernameAuth($data->username)) {
                    Utils::sendResponse(['success' => false, 'message' => 'js.auth.err_user_length']);
                }

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
            
        case 'verify_2fa':
            if (!empty($data->token) && !empty($data->code)) {
                Utils::sendResponse($auth->verify2FACode($data->token, $data->code));
            } else {
                Utils::sendResponse(['success' => false, 'message' => 'El token y el código son requeridos.']);
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
                if (!validatePasswordAuth($data->password)) {
                    Utils::sendResponse(['success' => false, 'message' => 'js.auth.err_pass_length']);
                }
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