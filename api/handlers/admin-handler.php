<?php
// api/handlers/admin-handler.php

$services = require_once __DIR__ . '/../../includes/bootstrap.php';
extract($services); // $pdo, $i18n, $redis

require_once __DIR__ . '/../services/AdminService.php';
require_once __DIR__ . '/../services/BackupService.php';
require_once __DIR__ . '/../services/LogFileService.php';

if (!isset($_SESSION['user_id'])) {
    Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.session_expired')]);
}

Utils::validateCsrf($i18n);

$role = $_SESSION['role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator'])) {
    Utils::jsonResponse(['success' => false, 'message' => $i18n->t('errors.access_denied')]);
}

$adminService = new AdminService($pdo, $i18n, $_SESSION['user_id']);
$backupService = new BackupService($pdo, $i18n, $_SESSION['user_id']);
$logFileService = new LogFileService(); 

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
        // Soporte para array o string
        $filenames = $_POST['filenames'] ?? $_POST['filename'] ?? '';
        Utils::jsonResponse($backupService->deleteBackup($filenames));
        break;
    
    case 'get_backup_content':
        $filenames = $_POST['files'] ?? '';
        Utils::jsonResponse($backupService->getBackupContent($filenames));
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

    // === AUDITORÍA ===
    case 'get_audit_logs':
        $page = (int)($_POST['page'] ?? 1);
        $limit = (int)($_POST['limit'] ?? 50);
        $filters = [];
        if (!empty($_POST['target_type'])) $filters['target_type'] = $_POST['target_type'];
        if (!empty($_POST['target_id'])) $filters['target_id'] = $_POST['target_id'];
        
        Utils::jsonResponse($adminService->getAuditLogs($page, $limit, $filters));
        break;

    // === LOGS DE ARCHIVO ===
    case 'get_log_files':
        Utils::jsonResponse($logFileService->getAllLogFiles());
        break;

    case 'delete_log_files':
        $paths = $_POST['paths'] ?? '';
        $pathsArray = is_array($paths) ? $paths : explode(',', $paths);
        Utils::jsonResponse($logFileService->deleteLogFiles($pathsArray));
        break;

    case 'get_log_content':
        $paths = $_POST['files'] ?? '';
        $pathsArray = is_array($paths) ? $paths : explode(',', $paths);
        Utils::jsonResponse($logFileService->getFilesContent($pathsArray));
        break;

    default:
        Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>