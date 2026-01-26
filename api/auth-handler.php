<?php
// api/auth-handler.php

// 1. CARGA AUTOMÁTICA
require_once __DIR__ . '/../vendor/autoload.php';

// 2. IMPORTACIONES (Namespaces)
use Aurora\Services\AuthService;
use Aurora\Libs\Utils;

// CONFIGURACIÓN DE SEGURIDAD PARA LA SESIÓN
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => '/',
    'domain' => $cookieParams['domain'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Nota: db.php se carga automáticamente gracias a composer "files",
// así que $pdo ya está disponible.

// Inicializar I18n usando Utils
$i18n = Utils::initI18n();

// Validación
Utils::validateCsrf($i18n);

// Inicializar Servicio
$authService = new AuthService($pdo, $i18n);
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