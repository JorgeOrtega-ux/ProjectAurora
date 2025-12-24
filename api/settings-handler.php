<?php
// api/settings-handler.php
session_start();
require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../includes/libs/TOTP.php'; 
require_once __DIR__ . '/../includes/libs/I18n.php';
// Incluimos el servicio
require_once __DIR__ . '/services/SettingsService.php';

header('Content-Type: application/json');

// Inicializar I18n
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$i18n = new I18n($userLang);

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

// 1. Verificar Autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => $i18n->t('api.session_expired')]);
    exit;
}

// 2. Verificar CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => $i18n->t('api.security_error')]);
        exit;
    }
}

// Inicializar Servicio
$userId = $_SESSION['user_id'];
$settingsService = new SettingsService($pdo, $i18n, $userId);
$action = $_POST['action'] ?? '';

// === DISPATCHER ===

switch ($action) {
    case 'upload_avatar':
        jsonResponse($settingsService->uploadAvatar($_FILES));
        break;

    case 'delete_avatar':
        jsonResponse($settingsService->deleteAvatar());
        break;

    case 'update_profile':
        $field = $_POST['field'] ?? '';
        $value = trim($_POST['value'] ?? '');
        jsonResponse($settingsService->updateProfile($field, $value));
        break;

    case 'validate_current_password':
        $currentPass = $_POST['current_password'] ?? '';
        jsonResponse($settingsService->validateCurrentPassword($currentPass));
        break;

    case 'change_password':
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        jsonResponse($settingsService->changePassword($currentPass, $newPass));
        break;

    case 'update_preference':
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        jsonResponse($settingsService->updatePreference($key, $value));
        break;

    case 'init_2fa':
        jsonResponse($settingsService->init2fa());
        break;

    case 'enable_2fa':
        $code = $_POST['code'] ?? '';
        jsonResponse($settingsService->enable2fa($code));
        break;

    case 'disable_2fa':
        jsonResponse($settingsService->disable2fa());
        break;

    case 'get_sessions':
        jsonResponse($settingsService->getSessions());
        break;

    case 'revoke_session':
        $tokenId = $_POST['token_id'] ?? '';
        jsonResponse($settingsService->revokeSession($tokenId));
        break;

    case 'revoke_all_sessions':
        jsonResponse($settingsService->revokeAllSessions());
        break;

    case 'delete_account':
        $password = $_POST['password'] ?? '';
        jsonResponse($settingsService->deleteAccount($password));
        break;

    default:
        echo json_encode(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>