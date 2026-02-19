<?php
// api/handler/auth-handler.php
session_start();
header("Content-Type: application/json; charset=UTF-8");

include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../services/AuthService.php';

$database = new Database();
$db = $database->getConnection();
$auth = new AuthService($db);

// Obtener datos del cuerpo de la petición (JSON)
$data = json_decode(file_get_contents("php://input"));
$action = isset($data->action) ? $data->action : '';

// --- VALIDACIÓN CSRF ---
// Validamos acciones críticas
if ($action === 'login' || $action === 'register' || $action === 'send_code') {
    if (empty($data->csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $data->csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Error de seguridad (Token inválido). Recarga la página.']);
        exit;
    }
}
// -----------------------

switch ($action) {
    case 'check_email':
        // Etapa 1: Validar que el correo no exista antes de continuar
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
        // Etapa 2: Guardar payload temporal y enviar código
        if (!empty($data->username) && !empty($data->email) && !empty($data->password)) {
            echo json_encode($auth->requestRegistrationCode($data));
        } else {
            echo json_encode(['success' => false, 'message' => 'Faltan datos para procesar el registro.']);
        }
        break;

    case 'register':
        // Etapa 3: Validar código y completar registro en BD
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