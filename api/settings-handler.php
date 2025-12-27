<?php
// api/settings-handler.php

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
require_once __DIR__ . '/../includes/libs/Utils.php';
require_once __DIR__ . '/services/SettingsService.php';

// Inicializar I18n usando Utils
$i18n = Utils::initI18n();

// 1. Verificar Autenticación (Esto es específico de este handler, se queda aquí)
if (!isset($_SESSION['user_id'])) {
    Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.session_expired')]);
}

// 2. Validación de seguridad CSRF centralizada
Utils::validateCsrf($i18n);

// Inicializar Servicio
$userId = $_SESSION['user_id'];
$settingsService = new SettingsService($pdo, $i18n, $userId);
$action = $_POST['action'] ?? '';

// === DISPATCHER ===

switch ($action) {
    case 'upload_avatar':
        Utils::jsonResponse($settingsService->uploadAvatar($_FILES));
        break;

    case 'delete_avatar':
        Utils::jsonResponse($settingsService->deleteAvatar());
        break;

    case 'update_profile':
        $field = $_POST['field'] ?? '';
        $value = trim($_POST['value'] ?? '');
        Utils::jsonResponse($settingsService->updateProfile($field, $value));
        break;

    // --- VERIFICACIÓN CAMBIO DE EMAIL ---
    case 'get_email_edit_status':
        Utils::jsonResponse($settingsService->getEmailEditStatus());
        break;

    case 'request_email_change_verification':
        $forceResend = isset($_POST['force_resend']) && $_POST['force_resend'] === 'true';
        Utils::jsonResponse($settingsService->requestEmailChangeVerification($forceResend));
        break;

    case 'verify_email_change_code':
        $code = trim($_POST['code'] ?? '');
        Utils::jsonResponse($settingsService->verifyEmailChangeCode($code));
        break;
    // ------------------------------------

    case 'validate_current_password':
        $currentPass = $_POST['current_password'] ?? '';
        Utils::jsonResponse($settingsService->validateCurrentPassword($currentPass));
        break;

    case 'change_password':
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        Utils::jsonResponse($settingsService->changePassword($currentPass, $newPass));
        break;

    case 'update_preference':
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        Utils::jsonResponse($settingsService->updatePreference($key, $value));
        break;

    case 'init_2fa':
        Utils::jsonResponse($settingsService->init2fa());
        break;

    case 'enable_2fa':
        $code = $_POST['code'] ?? '';
        Utils::jsonResponse($settingsService->enable2fa($code));
        break;

    case 'disable_2fa':
        Utils::jsonResponse($settingsService->disable2fa());
        break;

    case 'get_recovery_status':
        Utils::jsonResponse($settingsService->getRecoveryStatus());
        break;
        
    case 'regenerate_recovery_codes':
        $password = $_POST['password'] ?? '';
        Utils::jsonResponse($settingsService->regenerateRecoveryCodes($password));
        break;

    case 'get_sessions':
        Utils::jsonResponse($settingsService->getSessions());
        break;

    case 'revoke_session':
        $tokenId = $_POST['token_id'] ?? '';
        Utils::jsonResponse($settingsService->revokeSession($tokenId));
        break;

    case 'revoke_all_sessions':
        Utils::jsonResponse($settingsService->revokeAllSessions());
        break;

    case 'delete_account':
        $password = $_POST['password'] ?? '';
        Utils::jsonResponse($settingsService->deleteAccount($password));
        break;

    default:
        Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>