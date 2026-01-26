<?php
// api/admin-handler.php

require_once __DIR__ . '/../vendor/autoload.php';

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
require_once __DIR__ . '/services/AdminService.php';
require_once __DIR__ . '/services/BackupService.php';

$i18n = Utils::initI18n();

if (!isset($_SESSION['user_id'])) {
    Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.session_expired')]);
}

Utils::validateCsrf($i18n);

$role = $_SESSION['role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator'])) {
    Utils::jsonResponse(['success' => false, 'message' => $i18n->t('errors.access_denied')]);
}

// Servicios
$adminService = new AdminService($pdo, $i18n, $_SESSION['user_id']);
$backupService = new BackupService($pdo, $i18n, $_SESSION['user_id']);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_all_users':
        Utils::jsonResponse($adminService->getAllUsers());
        break;

    case 'get_user_details':
        $targetId = $_POST['target_id'] ?? 0;
        Utils::jsonResponse($adminService->getUserDetails($targetId));
        break;

    case 'update_user_profile':
        $targetId = $_POST['target_id'] ?? 0;
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        Utils::jsonResponse($adminService->updateUserProfile($targetId, $field, $value));
        break;
    
    case 'update_user_role':
        $targetId = $_POST['target_id'] ?? 0;
        $newRole = $_POST['new_role'] ?? '';
        Utils::jsonResponse($adminService->updateUserRole($targetId, $newRole));
        break;

    case 'update_user_status':
        $targetId = $_POST['target_id'] ?? 0;
        Utils::jsonResponse($adminService->updateUserStatus($targetId, $_POST));
        break;

    case 'update_user_preference':
        $targetId = $_POST['target_id'] ?? 0;
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        Utils::jsonResponse($adminService->updateUserPreference($targetId, $key, $value));
        break;

    case 'upload_user_avatar':
        $targetId = $_POST['target_id'] ?? 0;
        Utils::jsonResponse($adminService->uploadUserAvatar($targetId, $_FILES));
        break;

    case 'delete_user_avatar':
        $targetId = $_POST['target_id'] ?? 0;
        Utils::jsonResponse($adminService->deleteUserAvatar($targetId));
        break;

    case 'get_server_config':
        Utils::jsonResponse($adminService->getServerConfigAll());
        break;

    case 'update_server_config':
        Utils::jsonResponse($adminService->updateServerConfig($_POST));
        break;

    // === BACKUPS ===
    case 'get_backups':
        Utils::jsonResponse($backupService->getAllBackups());
        break;

    case 'create_backup':
        Utils::jsonResponse($backupService->createBackup());
        break;

    case 'restore_backup':
        $filename = $_POST['filename'] ?? '';
        Utils::jsonResponse($backupService->restoreBackup($filename));
        break;

    case 'delete_backup':
        $filename = $_POST['filename'] ?? '';
        Utils::jsonResponse($backupService->deleteBackup($filename));
        break;

    case 'get_backup_config':
        Utils::jsonResponse($backupService->getAutoConfig());
        break;

    case 'update_backup_config':
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1';
        $freq = $_POST['frequency'] ?? 24;
        $ret = $_POST['retention'] ?? 10;
        Utils::jsonResponse($backupService->updateAutoConfig($enabled, $freq, $ret));
        break;

    // === [NUEVO] AUDITORÍA ===
    case 'get_audit_logs':
        $page = (int)($_POST['page'] ?? 1);
        $limit = (int)($_POST['limit'] ?? 50);
        
        $filters = [];
        if (!empty($_POST['target_type'])) $filters['target_type'] = $_POST['target_type'];
        if (!empty($_POST['target_id'])) $filters['target_id'] = $_POST['target_id'];
        if (!empty($_POST['admin_id'])) $filters['admin_id'] = $_POST['admin_id'];

        Utils::jsonResponse($adminService->getAuditLogs($page, $limit, $filters));
        break;

    default:
        Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>