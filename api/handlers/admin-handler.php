<?php
// api/handlers/admin-handler.php

$services = require_once __DIR__ . '/../../includes/bootstrap.php';
// [REFACTORIZADO] Asignación explícita de servicios
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

use Aurora\Services\AdminService;
use Aurora\Services\BackupService;
use Aurora\Services\LogFileService;
use Aurora\Services\RedisService;
use Aurora\Services\AlertService;
use Aurora\Libs\Utils;

// Inicializamos AlertService aquí para pasarlo si es necesario
$alertService = new AlertService($redis);

if (!isset($_SESSION['user_id'])) {
    Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.session_expired')]);
}

Utils::validateCsrf($i18n);

// [SEGURIDAD CENTRALIZADA]
// Reemplaza la verificación manual de rol. Ahora exige ROL (founder/admin) Y 2FA VERIFICADO.
$privCheck = Utils::checkUserPrivileges($pdo, $_SESSION['user_id'], ['founder', 'administrator'], true);

if (!$privCheck['allowed']) {
    $msg = $i18n->t('errors.access_denied');
    
    // Mensajes más específicos si es por falta de 2FA
    if ($privCheck['reason'] === '2fa_not_verified') {
        $msg = 'Verificación de seguridad (2FA) requerida para realizar esta acción.';
    } elseif ($privCheck['reason'] === '2fa_not_enabled') {
        $msg = 'Debes tener la autenticación en dos pasos activada para acceder al panel.';
    }
    
    Utils::jsonResponse(['success' => false, 'message' => $msg]);
}

// Inicialización de Servicios
$adminService = new AdminService($pdo, $i18n, $_SESSION['user_id'], $redis);

// [CORRECCIÓN 1]: Orden de parámetros corregido para coincidir con el constructor de BackupService
// Estructura correcta: ($pdo, $redis, $i18n, $userId)
$backupService = new BackupService($pdo, $redis, $i18n, $_SESSION['user_id']);

$logFileService = new LogFileService(); 
$redisService = new RedisService($redis);

$action = $_POST['action'] ?? '';

switch ($action) {
    // === DASHBOARD & GENERAL ===
    case 'get_dashboard_stats':
        Utils::jsonResponse($adminService->getDashboardStats());
        break;

    // === GESTIÓN DE DESCARGAS SEGURAS ===
    case 'request_download_token':
        $file = $_POST['file'] ?? '';
        $type = $_POST['type'] ?? ''; // 'backup' o 'log'
        Utils::jsonResponse($adminService->requestDownloadToken($file, $type));
        break;

    // === [NUEVO] MODO PÁNICO ===
    case 'toggle_panic_mode':
        // Convertimos el string '1'/'0' a booleano
        $shouldActivate = (isset($_POST['activate']) && $_POST['activate'] === '1');
        // Pasamos el servicio de alertas para que AdminService pueda orquestar todo
        Utils::jsonResponse($adminService->togglePanicMode($shouldActivate, $alertService));
        break;

    // === USUARIOS ===
    case 'get_all_users':
        $page = (int)($_POST['page'] ?? 1);
        $limit = (int)($_POST['limit'] ?? 20);
        $search = trim($_POST['search'] ?? '');
        Utils::jsonResponse($adminService->getAllUsers($page, $limit, $search));
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

    case 'disable_user_2fa':
        $targetId = $_POST['target_id'] ?? 0;
        Utils::jsonResponse($adminService->disableUser2FA($targetId));
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
        // [CORRECCIÓN 2]: Llamada al método correcto (getBackups en lugar de getAllBackups)
        Utils::jsonResponse($backupService->getBackups());
        break;

    case 'create_backup':
        Utils::jsonResponse($backupService->createBackup());
        break;

    case 'restore_backup':
        $filename = $_POST['filename'] ?? '';
        Utils::jsonResponse($backupService->restoreBackup($filename));
        break;

    case 'delete_backup':
        $filenames = $_POST['filenames'] ?? $_POST['filename'] ?? '';
        Utils::jsonResponse($backupService->deleteBackup($filenames));
        break;
    
    // NOTA: Estas funciones (getBackupContent, getAutoConfig, updateAutoConfig)
    // NO existen en el BackupService.php actual. Si intentas usarlas, fallarán.
    // Se recomienda comentar o implementar los métodos faltantes en el servicio.
    case 'get_backup_content':
        $filenames = $_POST['files'] ?? '';
        // Utils::jsonResponse($backupService->getBackupContent($filenames));
        Utils::jsonResponse(['success' => false, 'message' => 'Función no implementada en servicio.']);
        break;

    case 'get_backup_config':
        // Utils::jsonResponse($backupService->getAutoConfig());
        Utils::jsonResponse(['success' => false, 'message' => 'Función no implementada en servicio.']);
        break;

    case 'update_backup_config':
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1';
        $freq = $_POST['frequency'] ?? 24;
        $ret = $_POST['retention'] ?? 10;
        // Utils::jsonResponse($backupService->updateAutoConfig($enabled, $freq, $ret));
        Utils::jsonResponse(['success' => false, 'message' => 'Función no implementada en servicio.']);
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
        Utils::jsonResponse($logFileService->getFilesContent($pathsArray)); // Nota: Verificar si getFilesContent existe en LogFileService
        break;
// === GESTIÓN DE METADATOS (Nuevo) ===
    case 'get_metadata':
        $type = $_POST['type'] ?? 'category'; // 'category' o 'actor'
        Utils::jsonResponse($adminService->getMetadata($type));
        break;

    case 'create_metadata':
        $type = $_POST['type'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $extra = $_POST['extra'] ?? null; // Ejemplo: 'actor' o 'actress' si es persona
        Utils::jsonResponse($adminService->createMetadata($type, $name, $extra));
        break;

    case 'delete_metadata':
        $type = $_POST['type'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        Utils::jsonResponse($adminService->deleteMetadata($type, $id));
        break;
    // === REDIS ===
    case 'get_redis_stats':
        Utils::jsonResponse($redisService->getStats());
        break;

    case 'get_redis_keys':
        $pattern = $_POST['pattern'] ?? '*';
        if (empty($pattern)) $pattern = '*';
        Utils::jsonResponse($redisService->getKeys($pattern));
        break;

    case 'get_redis_value':
        $key = $_POST['key'] ?? '';
        Utils::jsonResponse($redisService->getValue($key));
        break;

    case 'delete_redis_key':
        $key = $_POST['key'] ?? '';
        Utils::jsonResponse($redisService->deleteKey($key));
        break;

    case 'flush_redis_db':
        Utils::jsonResponse($redisService->flushDB());
        break;

    // === GESTIÓN DE ALERTAS (Directas) ===
    case 'create_system_alert':
        $data = json_decode($_POST['alert_data'], true);
        Utils::jsonResponse($alertService->createAlert($data));
        break;

    case 'deactivate_system_alert':
        Utils::jsonResponse($alertService->deactivateAlert());
        break;
        
    case 'get_active_alert':
        Utils::jsonResponse($alertService->getActiveAlert()); // Nota: Verificar nombre método en AlertService (getActiveAlert vs getActiveAlerts)
        break;

    default:
        Utils::jsonResponse(['success' => false, 'message' => $i18n->t('api.unknown_action')]);
        break;
}
?>