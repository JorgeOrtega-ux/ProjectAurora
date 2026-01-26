<?php
// api/auth-handler.php

// [CORRECCIÓN] Usamos bootstrap para asegurar que Redis maneje las sesiones
// Esto reemplaza todos los require manuales y la configuración de sesión manual
$services = require_once __DIR__ . '/../includes/bootstrap.php';

// Extraemos $pdo, $i18n y $redis
extract($services); 

// Cargamos el servicio (bootstrap no carga servicios automáticamente)
require_once __DIR__ . '/services/AuthService.php';

// Validación de seguridad CSRF centralizada
Utils::validateCsrf($i18n);

// [CORRECCIÓN] Inicializar Servicio pasando $redis
$authService = new AuthService($pdo, $i18n, $redis);

$action = $_POST['action'] ?? '';

// CAPTURAR EL TOKEN DE TURNSTILE
$turnstileToken = $_POST['cf-turnstile-response'] ?? '';

// === DISPATCHER ===

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