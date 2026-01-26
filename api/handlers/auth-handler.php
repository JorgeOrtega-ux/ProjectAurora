<?php
// api/handlers/auth-handler.php

// 1. BOOTSTRAP: Subimos dos niveles (../../) para llegar a includes
$services = require_once __DIR__ . '/../../includes/bootstrap.php';
extract($services); // $pdo, $i18n, $redis

// 2. Cargar Servicio: Subimos un nivel (../) para llegar a services
require_once __DIR__ . '/../services/AuthService.php';

// 3. Validar CSRF
Utils::validateCsrf($i18n);

// 4. Inicializar Servicio (Inyectamos Redis)
$authService = new AuthService($pdo, $i18n, $redis);

$action = $_POST['action'] ?? '';
$turnstileToken = $_POST['cf-turnstile-response'] ?? '';

switch ($action) {
    case 'register_step_1':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        Utils::jsonResponse($authService->registerStep1($email, $password, $turnstileToken));
        break;

    case 'initiate_verification':
        $username = trim($_POST['username'] ?? '');
        Utils::jsonResponse($authService->initiateVerification($username));
        break;

    case 'resend_code':
        Utils::jsonResponse($authService->resendCode());
        break;

    case 'complete_register':
        $code = trim($_POST['code'] ?? '');
        Utils::jsonResponse($authService->completeRegister($code));
        break;

    case 'login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        Utils::jsonResponse($authService->login($email, $password, $turnstileToken));
        break;

    case 'verify_2fa_login':
        $code = trim($_POST['code'] ?? '');
        Utils::jsonResponse($authService->verify2faLogin($code));
        break;

    case 'request_reset':
        $email = trim($_POST['email'] ?? '');
        Utils::jsonResponse($authService->requestReset($email));
        break;

    case 'reset_password':
        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        Utils::jsonResponse($authService->resetPassword($token, $newPassword));
        break;

    case 'logout':
        Utils::jsonResponse($authService->logout());
        break;

    case 'get_ws_token':
        Utils::jsonResponse($authService->generateWebSocketToken());
        break;

    default:
        Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>