<?php
// api/admin_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/admin_actions.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/i18n_server.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

// 1. SEGURIDAD: Verificar CSRF
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => trans('global.error_csrf')]);
    exit;
}

// 2. SEGURIDAD: Verificar Rol de Acceso (Solo Admins y Founders entran aquí)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => trans('admin.error.access_denied')]);
    exit;
}

try {
    // ======================================================
    // 1. OBTENER DATOS E HISTORIAL (COMBINADO)
    // ======================================================
    if ($action === 'get_user_details') {
        $targetId = $data['target_id'] ?? 0;
        
        // Info básica
        $stmt = $pdo->prepare("SELECT id, username, email, avatar, role, account_status, suspension_reason, suspension_end_date, deletion_type, deletion_reason, admin_comments FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) throw new Exception(trans('admin.error.user_not_found'));

        // Días restantes de suspensión
        $daysRemaining = 0;
        if ($user['account_status'] === 'suspended' && $user['suspension_end_date']) {
            $end = new DateTime($user['suspension_end_date']);
            $now = new DateTime();
            if ($end > $now) {
                $daysRemaining = $now->diff($end)->days + 1; 
            }
        }

        // 3. Obtener historial SOLO DE SANCIONES
        $sqlHistory = "
            SELECT 
                'suspension' as log_type,
                s.started_at as event_date,
                s.reason as reason,
                s.duration_days,
                s.ends_at,
                s.lifted_at,
                u_admin.username as admin_name,
                u_lifter.username as lifter_name,
                NULL as old_role,
                NULL as new_role
            FROM user_suspension_logs s
            LEFT JOIN users u_admin ON s.admin_id = u_admin.id
            LEFT JOIN users u_lifter ON s.lifted_by = u_lifter.id
            WHERE s.user_id = ?
            ORDER BY s.started_at DESC
        ";

        $stmtLogs = $pdo->prepare($sqlHistory);
        $stmtLogs->execute([$targetId]);
        $history = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'user' => $user, 
            'days_remaining' => $daysRemaining,
            'history' => $history
        ]);

    // ======================================================
    // 2. ACTUALIZAR SANCIONES (BAN/UNBAN)
    // ======================================================
    } elseif ($action === 'update_user_status') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'suspended'; 
        $reason = $data['reason'] ?? null;
        $durationInput = $data['duration_days'] ?? 0; 
        
        $currentAdminId = $_SESSION['user_id'];
        
        if ($targetId === $currentAdminId) throw new Exception(trans('admin.error.self_sanction'));

        // Validar existencia
        $stmtCheck = $pdo->prepare("SELECT account_status, suspension_reason, suspension_end_date FROM users WHERE id = ?");
        $stmtCheck->execute([$targetId]);
        $currentUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$currentUser) throw new Exception(trans('admin.error.user_not_exist'));

        $suspensionEnd = null;
        $finalReason = null;
        $dbDuration = 0;

        if ($newStatus === 'suspended') {
            if (empty($reason)) throw new Exception(trans('admin.error.reason_required'));
            $finalReason = $reason;
            if ($durationInput === 'permanent') {
                $suspensionEnd = null; 
                $dbDuration = -1; 
            } else {
                $days = (int)$durationInput;
                if ($days < 1) throw new Exception(trans('global.action_invalid'));
                $suspensionEnd = date('Y-m-d H:i:s', strtotime("+$days days"));
                $dbDuration = $days;
            }
            // Loguear sanción
            $stmtLog = $pdo->prepare("INSERT INTO user_suspension_logs (user_id, admin_id, reason, duration_days, ends_at) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$targetId, $currentAdminId, $finalReason, $dbDuration, $suspensionEnd]);
        } else {
            // Levantar sanción
            $stmtFindLog = $pdo->prepare("SELECT id FROM user_suspension_logs WHERE user_id = ? AND lifted_at IS NULL ORDER BY id DESC LIMIT 1");
            $stmtFindLog->execute([$targetId]);
            $activeLogId = $stmtFindLog->fetchColumn();
            if ($activeLogId) {
                $stmtUpdateLog = $pdo->prepare("UPDATE user_suspension_logs SET lifted_by = ?, lifted_at = NOW() WHERE id = ?");
                $stmtUpdateLog->execute([$currentAdminId, $activeLogId]);
            }
        }

        $sql = "UPDATE users SET account_status = ?, suspension_reason = ?, suspension_end_date = ?, deletion_type = NULL, deletion_reason = NULL, admin_comments = NULL WHERE id = ?";
        $pdo->prepare($sql)->execute([$newStatus, $finalReason, $suspensionEnd, $targetId]);

        if ($newStatus === 'suspended') {
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'suspended']);
        }

        echo json_encode(['success' => true, 'message' => ($newStatus === 'active') ? trans('admin.success.ban_lifted') : trans('admin.success.ban_applied')]);

    // ======================================================
    // 3. GESTIONAR USUARIO (ELIMINAR/ACTIVAR)
    // ======================================================
    } elseif ($action === 'update_user_general') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'active';
        $currentAdminId = $_SESSION['user_id'];

        if ($targetId === $currentAdminId) throw new Exception(trans('admin.error.self_sanction'));

        if ($newStatus === 'deleted') {
            $delType = $data['deletion_type'] ?? 'admin_decision';
            $delReason = $data['deletion_reason'] ?? null; 
            $adminComments = $data['admin_comments'] ?? null;

            if (empty($adminComments)) throw new Exception(trans('admin.error.reason_required'));
            
            $sql = "UPDATE users SET account_status = 'deleted', deletion_type = ?, deletion_reason = ?, admin_comments = ?, suspension_reason = NULL, suspension_end_date = NULL WHERE id = ?";
            $pdo->prepare($sql)->execute([$delType, $delReason, $adminComments, $targetId]);
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'deleted']);

            echo json_encode(['success' => true, 'message' => trans('admin.success.account_deleted')]);
        } elseif ($newStatus === 'active') {
            $pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")->execute([$targetId]);
            echo json_encode(['success' => true, 'message' => trans('global.save_status')]);
        }

    // ======================================================
    // 4. GESTIONAR ROL DE USUARIO
    // ======================================================
    } elseif ($action === 'update_user_role') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newRole = $data['role'] ?? 'user';
        $currentAdminId = $_SESSION['user_id'];
        $currentAdminRole = $_SESSION['user_role']; 

        if ($targetId === $currentAdminId) throw new Exception("No puedes cambiar tu propio rol.");

        $allowedRoles = ['user', 'moderator', 'administrator'];
        if (!in_array($newRole, $allowedRoles)) throw new Exception(trans('global.action_invalid'));

        $stmtTarget = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtTarget->execute([$targetId]);
        $oldRole = $stmtTarget->fetchColumn();

        // Reglas de jerarquía
        if ($oldRole === 'founder') throw new Exception("No tienes permisos para modificar a un Fundador.");
        if ($newRole === 'founder') throw new Exception("No se puede asignar el rol de Fundador.");

        if ($currentAdminRole === 'administrator') {
            if ($oldRole === 'administrator') throw new Exception("No tienes permisos para modificar a otro Administrador.");
            if ($newRole === 'administrator') throw new Exception("Solo el Fundador puede asignar el rol de Administrador.");
        }

        if ($oldRole === $newRole) throw new Exception("El usuario ya tiene ese rol.");

        $sql = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$newRole, $targetId])) {
            $ip = get_client_ip();
            $stmtAudit = $pdo->prepare("INSERT INTO user_role_logs (user_id, admin_id, old_role, new_role, ip_address, changed_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmtAudit->execute([$targetId, $currentAdminId, $oldRole, $newRole, $ip]);

            send_live_notification($targetId, 'force_logout', ['reason' => 'role_change']);
            echo json_encode(['success' => true, 'message' => trans('global.save_status')]);
        } else {
            throw new Exception(trans('global.error_connection'));
        }

    // ======================================================
    // 5. CONFIGURACIÓN DEL SERVIDOR (CON BROADCAST)
    // ======================================================
    } elseif ($action === 'update_server_config') {
        
        $key = $data['key'] ?? '';
        $value = $data['value'] ?? 0;

        $allowedKeys = ['maintenance_mode', 'allow_registrations', 'max_concurrent_users'];
        if (!in_array($key, $allowedKeys)) {
            throw new Exception(trans('global.action_invalid'));
        }

        // 1. Activar Mantenimiento
        if ($key === 'maintenance_mode' && (int)$value === 1) {
            $sql = "UPDATE server_config SET maintenance_mode = 1, allow_registrations = 0 WHERE id = 1";
            $pdo->exec($sql);
            
            // ENVIAR SEÑAL GLOBAL: Mantenimiento Activado
            send_live_notification('global', 'system_status_update', ['maintenance' => true]);
        
        // 2. Desactivar Mantenimiento
        } elseif ($key === 'maintenance_mode' && (int)$value === 0) {
            $sql = "UPDATE server_config SET maintenance_mode = 0 WHERE id = 1";
            $pdo->exec($sql);

            // ENVIAR SEÑAL GLOBAL: Mantenimiento Desactivado
            // Esto hará que todos recarguen la página. El router.php verificará si hay cupo.
            // Si ActiveSessions > MaxUsers, el router redirigirá a status-page?status=server_full.
            send_live_notification('global', 'system_status_update', ['maintenance' => false]);

        // 3. Activar Registros
        } elseif ($key === 'allow_registrations' && (int)$value === 1) {
            $curr = getServerConfig($pdo);
            if ((int)$curr['maintenance_mode'] === 1) {
                throw new Exception("No puedes activar registros durante el mantenimiento.");
            }
            $sql = "UPDATE server_config SET allow_registrations = 1 WHERE id = 1";
            $pdo->exec($sql);
            
        } else {
            // Actualización genérica (ej: max_concurrent_users)
            $sql = "UPDATE server_config SET $key = ? WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$value]);
        }

        echo json_encode(['success' => true, 'message' => trans('global.save_status')]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>