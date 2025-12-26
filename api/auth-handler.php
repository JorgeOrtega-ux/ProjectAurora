<?php
// api/auth-handler.php

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

require_once __DIR__ . '/../config/database/db.php';
// Utils carga I18n internamente, pero el servicio AuthService necesita I18n
require_once __DIR__ . '/../includes/libs/Utils.php';
require_once __DIR__ . '/services/AuthService.php';

// Inicializar I18n usando Utils
$i18n = Utils::initI18n();

// Validación de seguridad CSRF centralizada
Utils::validateCsrf($i18n);

// Inicializar Servicio
$authService = new AuthService($pdo, $i18n);
$action = $_POST['action'] ?? '';

// === DISPATCHER ===

switch ($action) {
    case 'register_step_1':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        Utils::jsonResponse($authService->registerStep1($email, $password));
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
        Utils::jsonResponse($authService->login($email, $password));
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

    default:
        Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>