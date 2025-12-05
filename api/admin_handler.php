<?php
// api/admin_handler.php

// Setup inicial
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/admin_actions.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

// Inclusiones de Configuración
require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/i18n_server.php';

// Inclusiones de Servicios Admin
require_once '../includes/logic/admin/dashboard_service.php';
require_once '../includes/logic/admin/users_service.php';
require_once '../includes/logic/admin/communities_service.php';
require_once '../includes/logic/admin/moderation_service.php';
require_once '../includes/logic/admin/backups_service.php';
require_once '../includes/logic/admin/system_service.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, "application/json") !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => translation('global.error_csrf')]);
    exit;
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => translation('admin.error.access_denied')]);
    exit;
}

$currentAdminId = $_SESSION['user_id'];
$currentAdminRole = $_SESSION['user_role'];
$backupDir = __DIR__ . '/../backups';
if (!file_exists($backupDir)) { mkdir($backupDir, 0777, true); }

try {
    $response = ['success' => false, 'message' => translation('global.action_invalid')];

    // --- DASHBOARD ---
    if ($action === 'get_dashboard_stats') {
        $response = get_dashboard_stats($pdo);
    } elseif ($action === 'get_alert_status') {
        $response = get_alert_status($pdo);
    } elseif ($action === 'activate_alert') {
        $response = activate_alert($pdo, $currentAdminId, $data['type'] ?? '', $data['meta_data'] ?? []);
    } elseif ($action === 'stop_alert') {
        $response = stop_alert($pdo);
    
    // --- USUARIOS ---
    } elseif ($action === 'get_user_details') {
        $response = get_user_details($pdo, $data['target_id'] ?? 0);
    } elseif ($action === 'update_user_status') {
        $response = update_user_status($pdo, $currentAdminId, (int)($data['target_id']??0), $data['status']??'', $data['reason']??null, $data['duration_days']??0);
    } elseif ($action === 'update_user_general') {
        $response = update_user_general($pdo, $currentAdminId, (int)($data['target_id']??0), $data['status']??'active', $data);
    } elseif ($action === 'update_user_role') {
        $response = update_user_role($pdo, $currentAdminId, $currentAdminRole, (int)($data['target_id']??0), $data['role']??'user');
    } elseif ($action === 'admin_update_profile_picture') {
        $response = admin_update_profile_picture($pdo, $currentAdminId, $currentAdminRole, (int)($data['target_id']??0), $_FILES);
    } elseif ($action === 'admin_remove_profile_picture') {
        $response = admin_remove_profile_picture($pdo, $currentAdminId, (int)($data['target_id']??0));
    } elseif ($action === 'admin_update_username') {
        $response = admin_update_username($pdo, $currentAdminId, (int)($data['target_id']??0), $data['username']??'');
    } elseif ($action === 'admin_update_email') {
        $response = admin_update_email($pdo, $currentAdminId, (int)($data['target_id']??0), $data['email']??'');

    // --- BACKUPS ---
    } elseif ($action === 'list_backups') {
        $response = list_backups($backupDir);
    } elseif ($action === 'create_backup') {
        $response = create_backup($backupDir);
    } elseif ($action === 'delete_backup') {
        $response = delete_backup($backupDir, $data['filename'] ?? '');
    } elseif ($action === 'restore_backup') {
        $response = restore_backup($pdo, $backupDir, $data['filename'] ?? '');

    // --- SYSTEM ---
    } elseif ($action === 'update_server_config') {
        $response = update_server_config($pdo, $data['key'] ?? '', $data['value'] ?? 0);
    } elseif ($action === 'get_redis_status') {
        $response = get_redis_status();
    } elseif ($action === 'clear_redis') {
        $response = clear_redis();
    } elseif ($action === 'test_bridge') {
        $response = test_bridge();

    // --- COMUNIDADES ---
    } elseif ($action === 'list_communities') {
        $response = list_communities($pdo, $data['q'] ?? '');
    } elseif ($action === 'get_admin_community_details') {
        $response = get_admin_community_details($pdo, (int)($data['id'] ?? 0));
    } elseif ($action === 'save_community') {
        $response = save_community($pdo, $data);
    } elseif ($action === 'delete_community') {
        $response = delete_community($pdo, (int)($data['id'] ?? 0));

    // --- MODERACIÓN ---
    } elseif ($action === 'get_community_members') {
        $response = get_community_members($pdo, (int)($data['community_id'] ?? 0));
    } elseif ($action === 'get_community_banned_users') {
        $response = get_community_banned_users($pdo, (int)($data['community_id'] ?? 0));
    } elseif ($action === 'kick_member') {
        $response = kick_member($pdo, $currentAdminId, (int)($data['community_id'] ?? 0), (int)($data['user_id'] ?? 0));
    } elseif ($action === 'ban_member') {
        $response = ban_member($pdo, $currentAdminId, (int)($data['community_id'] ?? 0), (int)($data['user_id'] ?? 0), $data['reason'] ?? null, $data['duration'] ?? null);
    } elseif ($action === 'mute_member') {
        $response = mute_member($pdo, (int)($data['community_id'] ?? 0), (int)($data['user_id'] ?? 0), $data['duration'] ?? null);
    } elseif ($action === 'unban_member') {
        $response = unban_member($pdo, (int)($data['community_id'] ?? 0), (int)($data['user_id'] ?? 0));
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>