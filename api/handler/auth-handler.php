<?php
// api/handler/auth-handler.php

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../services/AuthService.php';

$database = new Database();
$db = $database->getConnection();
$auth = new AuthService($db);

// Obtener datos del cuerpo de la petición (JSON)
$data = json_decode(file_get_contents("php://input"));

// NOTA: La variable $action YA EXISTE aquí porque fue definida en api/index.php

// --- VALIDACIÓN CSRF ---
if ($action === 'login' || $action === 'register' || $action === 'send_code') {
    if (empty($data->csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $data->csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Error de seguridad (Token inválido). Recarga la página.']);
        exit;
    }
}
// -----------------------

switch ($action) {
    case 'check_email':
        if (!empty($data->email)) {
            if ($auth->checkEmail($data->email)) {
                echo json_encode(['success' => false, 'message' => 'El correo ya está registrado.']);
            } else {
                echo json_encode(['success' => true]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'El correo es requerido.']);
        }
        break;

    case 'send_code':
        if (!empty($data->username) && !empty($data->email) && !empty($data->password)) {
            echo json_encode($auth->requestRegistrationCode($data));
        } else {
            echo json_encode(['success' => false, 'message' => 'Faltan datos para procesar el registro.']);
        }
        break;

    case 'register':
        if (!empty($data->email) && !empty($data->code)) {
            echo json_encode($auth->register($data));
        } else {
            echo json_encode(['success' => false, 'message' => 'El código de verificación es requerido.']);
        }
        break;

    case 'login':
        if (!empty($data->email) && !empty($data->password)) {
            echo json_encode($auth->login($data->email, $data->password));
        } else {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        }
        break;

    case 'logout':
        echo json_encode($auth->logout());
        break;

    case 'check_session':
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'name' => $_SESSION['user_name'],
                    'avatar' => $_SESSION['user_avatar']
                ]
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
        break;
}
?>