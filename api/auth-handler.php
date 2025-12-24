<?php
// api/auth-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../includes/libs/TOTP.php'; 
require_once __DIR__ . '/../includes/libs/I18n.php';
// Incluimos el servicio
require_once __DIR__ . '/services/AuthService.php';

header('Content-Type: application/json');

// Inicializar I18n
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$i18n = new I18n($userLang);

function jsonResponse($data) {
    // Aseguramos que data tenga success y message como mínimo si el servicio los devuelve
    echo json_encode($data);
    exit;
}

// === SEGURIDAD CSRF ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => $i18n->t('api.security_error')]);
        exit;
    }
}

// Inicializar Servicio
$authService = new AuthService($pdo, $i18n);
$action = $_POST['action'] ?? '';

// === DISPATCHER ===

switch ($action) {
    case 'register_step_1':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        jsonResponse($authService->registerStep1($email, $password));
        break;

    case 'initiate_verification':
        $username = trim($_POST['username'] ?? '');
        jsonResponse($authService->initiateVerification($username));
        break;

    case 'resend_code':
        jsonResponse($authService->resendCode());
        break;

    case 'complete_register':
        $code = trim($_POST['code'] ?? '');
        jsonResponse($authService->completeRegister($code));
        break;

    case 'login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        jsonResponse($authService->login($email, $password));
        break;

    case 'verify_2fa_login':
        $code = trim($_POST['code'] ?? '');
        jsonResponse($authService->verify2faLogin($code));
        break;

    case 'request_reset':
        $email = trim($_POST['email'] ?? '');
        jsonResponse($authService->requestReset($email));
        break;

    case 'reset_password':
        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        jsonResponse($authService->resetPassword($token, $newPassword));
        break;

    case 'logout':
        jsonResponse($authService->logout());
        break;

    default:
        echo json_encode(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>