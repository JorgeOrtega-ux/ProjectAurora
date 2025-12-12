<?php
// api/admin_handler.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database/db.php'; 
require_once __DIR__ . '/../config/helpers/i18n.php'; 
require_once __DIR__ . '/utils.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar Admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => __('api.error.no_auth')]);
    exit;
}
$allowedRoles = ['founder', 'administrator'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
    echo json_encode(['status' => 'error', 'message' => __('global.access_denied')]);
    exit;
}

// Cargar idioma
$userId = $_SESSION['user_id'];
$lang = null;
try {
    $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lang = $stmt->fetchColumn();
} catch(Exception $e){}
if (!$lang) $lang = detect_browser_language(); 
load_translations($lang);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $input = $json;
    }

    $incomingToken = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($incomingToken) || empty($sessionToken) || !hash_equals($sessionToken, $incomingToken)) {
        sendJsonResponse('error', __('api.error.csrf'));
    }

    $action = $input['action'] ?? '';
    
    if ($action === 'get_server_config') {
        try {
            $stmt = $pdo->query("SELECT * FROM server_config WHERE id=1");
            $data = $stmt->fetch();
            if(!$data) {
                // Si no existe, crear por defecto (Valores actualizados)
                $pdo->exec("INSERT INTO server_config (id, maintenance_mode, allow_registrations, min_password_length, max_password_length, min_username_length, max_username_length, max_email_length, max_login_attempts, lockout_time_minutes, code_resend_cooldown, username_cooldown, email_cooldown, profile_picture_max_size) VALUES (1, 0, 1, 8, 72, 6, 32, 255, 5, 5, 60, 30, 12, 2)");
                $data = [
                    'maintenance_mode' => 0, 
                    'allow_registrations' => 1,
                    'min_password_length' => 8,
                    'max_password_length' => 72,
                    'min_username_length' => 6,
                    'max_username_length' => 32,
                    'max_email_length' => 255,
                    'max_login_attempts' => 5,
                    'lockout_time_minutes' => 5,
                    'code_resend_cooldown' => 60,
                    'username_cooldown' => 30,
                    'email_cooldown' => 12,
                    'profile_picture_max_size' => 2
                ];
            }
            sendJsonResponse('success', 'OK', null, $data);
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    if ($action === 'update_server_config') {
        // Recibir valores existentes
        $maintenance = isset($input['maintenance_mode']) ? (int)$input['maintenance_mode'] : 0;
        $registrations = isset($input['allow_registrations']) ? (int)$input['allow_registrations'] : 1;
        
        $minPass = isset($input['min_password_length']) ? (int)$input['min_password_length'] : 8;
        $maxPass = isset($input['max_password_length']) ? (int)$input['max_password_length'] : 72;
        $minUser = isset($input['min_username_length']) ? (int)$input['min_username_length'] : 6;
        $maxUser = isset($input['max_username_length']) ? (int)$input['max_username_length'] : 32;
        $maxEmail = isset($input['max_email_length']) ? (int)$input['max_email_length'] : 255;

        // Recibir NUEVOS valores
        $maxLoginAttempts = isset($input['max_login_attempts']) ? (int)$input['max_login_attempts'] : 5;
        $lockoutTime = isset($input['lockout_time_minutes']) ? (int)$input['lockout_time_minutes'] : 5;
        $codeResend = isset($input['code_resend_cooldown']) ? (int)$input['code_resend_cooldown'] : 60;
        $userCooldown = isset($input['username_cooldown']) ? (int)$input['username_cooldown'] : 30;
        $emailCooldown = isset($input['email_cooldown']) ? (int)$input['email_cooldown'] : 12;
        $profilePicSize = isset($input['profile_picture_max_size']) ? (int)$input['profile_picture_max_size'] : 2;

        // Validaciones lógicas básicas
        if ($minPass < 1) $minPass = 1;
        if ($maxPass < $minPass) $maxPass = $minPass;
        if ($minUser < 1) $minUser = 1;
        if ($maxUser < $minUser) $maxUser = $minUser;
        if ($maxEmail < 5) $maxEmail = 5;

        // Validaciones para nuevos valores
        if ($maxLoginAttempts < 1) $maxLoginAttempts = 1;
        if ($lockoutTime < 1) $lockoutTime = 1;
        if ($codeResend < 0) $codeResend = 0;
        if ($userCooldown < 0) $userCooldown = 0;
        if ($emailCooldown < 0) $emailCooldown = 0;
        if ($profilePicSize < 1) $profilePicSize = 1;

        try {
            $sql = "UPDATE server_config SET 
                    maintenance_mode = ?, 
                    allow_registrations = ?,
                    min_password_length = ?,
                    max_password_length = ?,
                    min_username_length = ?,
                    max_username_length = ?,
                    max_email_length = ?,
                    max_login_attempts = ?,
                    lockout_time_minutes = ?,
                    code_resend_cooldown = ?,
                    username_cooldown = ?,
                    email_cooldown = ?,
                    profile_picture_max_size = ?
                    WHERE id = 1";

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([
                $maintenance, $registrations, 
                $minPass, $maxPass, $minUser, $maxUser, $maxEmail,
                $maxLoginAttempts, $lockoutTime, $codeResend, $userCooldown, $emailCooldown, $profilePicSize
            ])) {
                logSecurityEvent($pdo, "uid_".$userId, "server_config_update");
                sendJsonResponse('success', __('api.success.preferences_saved'));
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }
}
?>