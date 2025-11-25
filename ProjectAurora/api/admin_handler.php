<?php
// api/admin_handler.php

// CONFIGURACIÓN DE LOGS
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

// 1. SEGURIDAD
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

// CONFIGURACIÓN DE BACKUPS
$backupDir = __DIR__ . '/../backups';
if (!file_exists($backupDir)) { mkdir($backupDir, 0777, true); }

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

// HELPER PARA OBTENER RUTA DEL BINARIO
function getDbBinary($binaryName) {
    $customPath = $_ENV['DB_BIN_PATH'] ?? getenv('DB_BIN_PATH');
    
    if (!empty($customPath)) {
        $customPath = rtrim(str_replace('\\', '/', $customPath), '/');
        $binary = $customPath . '/' . $binaryName;
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            if (!str_ends_with(strtolower($binary), '.exe')) {
                $binary .= '.exe';
            }
        }
        
        if (!file_exists($binary)) {
            throw new Exception("El archivo no existe en la ruta configurada: $binary. Verifica DB_BIN_PATH en .env");
        }
        
        return '"' . $binary . '"'; 
    }
    return $binaryName;
}

try {

    // === DASHBOARD STATS (NUEVO) ===
    if ($action === 'get_dashboard_stats') {
        
        // 1. Total Usuarios (excluyendo eliminados)
        $stmtTotal = $pdo->query("SELECT COUNT(*) FROM users WHERE account_status != 'deleted'");
        $totalUsers = $stmtTotal->fetchColumn();

        // 2. Usuarios Conectados (Basado en actividad reciente < 5 min en DB como respaldo)
        $stmtOnline = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_activity > (NOW() - INTERVAL 5 MINUTE)");
        $onlineUsers = $stmtOnline->fetchColumn();

        // 3. Nuevos Hoy
        $stmtNew = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
        $newUsersToday = $stmtNew->fetchColumn();

        // 4. Sesiones Totales Activas
        $stmtSessions = $pdo->query("SELECT COUNT(*) FROM user_sessions");
        $activeSessions = $stmtSessions->fetchColumn();

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_users' => $totalUsers,
                'online_users' => $onlineUsers,
                'new_users_today' => $newUsersToday,
                'active_sessions' => $activeSessions
            ]
        ]);

    } elseif ($action === 'get_user_details') {
        $targetId = $data['target_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, username, email, profile_picture, role, account_status, suspension_reason, suspension_end_date, deletion_type, deletion_reason, admin_comments FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new Exception(translation('admin.error.user_not_found'));
        $daysRemaining = 0;
        if ($user['account_status'] === 'suspended' && $user['suspension_end_date']) {
            $end = new DateTime($user['suspension_end_date']);
            $now = new DateTime();
            if ($end > $now) $daysRemaining = $now->diff($end)->days + 1; 
        }
        $sqlHistory = "SELECT 'suspension' as log_type, s.started_at as event_date, s.reason as reason, s.duration_days, s.ends_at, s.lifted_at, u_admin.username as admin_name, u_lifter.username as lifter_name FROM user_suspension_logs s LEFT JOIN users u_admin ON s.admin_id = u_admin.id LEFT JOIN users u_lifter ON s.lifted_by = u_lifter.id WHERE s.user_id = ? ORDER BY s.started_at DESC";
        $stmtLogs = $pdo->prepare($sqlHistory);
        $stmtLogs->execute([$targetId]);
        $history = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'user' => $user, 'days_remaining' => $daysRemaining, 'history' => $history]);

    } elseif ($action === 'update_user_status') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'suspended'; 
        $reason = $data['reason'] ?? null;
        $durationInput = $data['duration_days'] ?? 0; 
        $currentAdminId = $_SESSION['user_id'];
        if ($targetId === $currentAdminId) throw new Exception(translation('admin.error.self_sanction'));
        $stmtCheck = $pdo->prepare("SELECT account_status FROM users WHERE id = ?");
        $stmtCheck->execute([$targetId]);
        if (!$stmtCheck->fetch()) throw new Exception(translation('admin.error.user_not_exist'));
        $suspensionEnd = null; $finalReason = null; $dbDuration = 0;
        if ($newStatus === 'suspended') {
            if (empty($reason)) throw new Exception(translation('admin.error.reason_required'));
            $finalReason = $reason;
            if ($durationInput === 'permanent') { $suspensionEnd = null; $dbDuration = -1; } 
            else { $days = (int)$durationInput; if ($days < 1) throw new Exception(translation('global.action_invalid')); $suspensionEnd = date('Y-m-d H:i:s', strtotime("+$days days")); $dbDuration = $days; }
            $stmtLog = $pdo->prepare("INSERT INTO user_suspension_logs (user_id, admin_id, reason, duration_days, ends_at) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$targetId, $currentAdminId, $finalReason, $dbDuration, $suspensionEnd]);
        } else {
            $stmtFindLog = $pdo->prepare("SELECT id FROM user_suspension_logs WHERE user_id = ? AND lifted_at IS NULL ORDER BY id DESC LIMIT 1");
            $stmtFindLog->execute([$targetId]);
            $activeLogId = $stmtFindLog->fetchColumn();
            if ($activeLogId) { $stmtUpdateLog = $pdo->prepare("UPDATE user_suspension_logs SET lifted_by = ?, lifted_at = NOW() WHERE id = ?"); $stmtUpdateLog->execute([$currentAdminId, $activeLogId]); }
        }
        $sql = "UPDATE users SET account_status = ?, suspension_reason = ?, suspension_end_date = ?, deletion_type = NULL, deletion_reason = NULL, admin_comments = NULL WHERE id = ?";
        $pdo->prepare($sql)->execute([$newStatus, $finalReason, $suspensionEnd, $targetId]);
        if ($newStatus === 'suspended') {
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'suspended']);
        }
        echo json_encode(['success' => true, 'message' => ($newStatus === 'active') ? translation('admin.success.ban_lifted') : translation('admin.success.ban_applied')]);

    } elseif ($action === 'update_user_general') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'active';
        $currentAdminId = $_SESSION['user_id'];
        if ($targetId === $currentAdminId) throw new Exception(translation('admin.error.self_sanction'));
        if ($newStatus === 'deleted') {
            $delType = $data['deletion_type'] ?? 'admin_decision';
            $delReason = $data['deletion_reason'] ?? null; 
            $adminComments = $data['admin_comments'] ?? null;
            if (empty($adminComments)) throw new Exception(translation('admin.error.reason_required'));
            $sql = "UPDATE users SET account_status = 'deleted', deletion_type = ?, deletion_reason = ?, admin_comments = ?, suspension_reason = NULL, suspension_end_date = NULL WHERE id = ?";
            $pdo->prepare($sql)->execute([$delType, $delReason, $adminComments, $targetId]);
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'deleted']);
            echo json_encode(['success' => true, 'message' => translation('admin.success.account_deleted')]);
        } elseif ($newStatus === 'active') {
            $pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")->execute([$targetId]);
            echo json_encode(['success' => true, 'message' => translation('global.save_status')]);
        }

    } elseif ($action === 'update_user_role') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newRole = $data['role'] ?? 'user';
        $currentAdminId = $_SESSION['user_id'];
        $currentAdminRole = $_SESSION['user_role']; 
        if ($targetId === $currentAdminId) throw new Exception("No puedes cambiar tu propio rol.");
        $allowedRoles = ['user', 'moderator', 'administrator'];
        if (!in_array($newRole, $allowedRoles)) throw new Exception(translation('global.action_invalid'));
        $stmtTarget = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtTarget->execute([$targetId]);
        $oldRole = $stmtTarget->fetchColumn();
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
            echo json_encode(['success' => true, 'message' => translation('global.save_status')]);
        } else {
            throw new Exception(translation('global.error_connection'));
        }

    } elseif ($action === 'update_server_config') {
        $key = $data['key'] ?? '';
        $value = $data['value'] ?? 0;
        $allowedKeys = ['maintenance_mode', 'allow_registrations', 'min_password_length', 'max_password_length', 'min_username_length', 'max_username_length', 'max_email_length', 'max_login_attempts', 'lockout_time_minutes', 'code_resend_cooldown', 'username_cooldown', 'email_cooldown', 'profile_picture_max_size', 'allowed_email_domains'];
        if (!in_array($key, $allowedKeys)) throw new Exception(translation('global.action_invalid'));
        if ($key === 'maintenance_mode') {
            $intVal = (int)$value;
            $sql = "UPDATE server_config SET maintenance_mode = ? WHERE id = 1";
            $pdo->prepare($sql)->execute([$intVal === 1 ? 1 : 0]);
            if ($intVal === 1) $pdo->exec("UPDATE server_config SET allow_registrations = 0 WHERE id = 1");
            send_live_notification('global', 'system_status_update', ['maintenance' => ($intVal === 1)]);
        } elseif ($key === 'allow_registrations') {
            $intVal = (int)$value;
            $curr = getServerConfig($pdo);
            if ($intVal === 1 && (int)$curr['maintenance_mode'] === 1) throw new Exception("No puedes activar registros durante el mantenimiento.");
            $pdo->prepare("UPDATE server_config SET allow_registrations = ? WHERE id = 1")->execute([$intVal === 1 ? 1 : 0]);
        } else {
            $sql = "UPDATE server_config SET $key = ? WHERE id = 1";
            if ($key === 'allowed_email_domains') $finalVal = (!empty($value) && is_array($value)) ? json_encode($value) : NULL;
            else $finalVal = (int)$value;
            $pdo->prepare($sql)->execute([$finalVal]);
        }
        echo json_encode(['success' => true, 'message' => translation('global.save_status')]);

    } elseif ($action === 'list_backups') {
        $files = array_diff(scandir($backupDir), ['.', '..']);
        $backups = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $path = $backupDir . '/' . $file;
                $backups[] = [
                    'filename' => $file,
                    'size' => formatSize(filesize($path)),
                    'created_at' => date("Y-m-d H:i:s", filemtime($path)),
                    'timestamp' => filemtime($path)
                ];
            }
        }
        usort($backups, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });
        echo json_encode(['success' => true, 'backups' => $backups]);

    } elseif ($action === 'create_backup') {
        if (!is_writable($backupDir)) {
            throw new Exception("Permiso denegado en carpeta 'backups'.");
        }

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db   = $_ENV['DB_NAME'] ?? 'project_aurora_db';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        $passArg = !empty($pass) ? "-p\"$pass\"" : "";
        
        try {
            $mysqldump = getDbBinary('mysqldump');
        } catch (Exception $e) {
            throw $e; 
        }
        
        $command = "$mysqldump --opt -h $host -u $user $passArg $db > \"$filepath\" 2>&1";
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($filepath) && filesize($filepath) > 0) {
            echo json_encode(['success' => true, 'message' => translation('admin.backups.created_success')]);
        } else {
            if (file_exists($filepath)) @unlink($filepath);
            $debugCmd = str_replace($pass, '*****', $command); 
            $outStr = implode(" | ", $output);
            throw new Exception("Error (Código $returnVar). CMD: $debugCmd. SALIDA: $outStr");
        }

    } elseif ($action === 'delete_backup') {
        $filename = $data['filename'] ?? '';
        if (empty($filename) || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            throw new Exception(translation('global.action_invalid'));
        }
        $filepath = $backupDir . '/' . $filename;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                echo json_encode(['success' => true, 'message' => translation('admin.backups.delete_success')]);
            } else {
                throw new Exception("Error al eliminar archivo.");
            }
        } else {
            throw new Exception("Archivo no encontrado.");
        }

    } elseif ($action === 'restore_backup') {
        $filename = $data['filename'] ?? '';
        if (empty($filename) || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            throw new Exception(translation('global.action_invalid'));
        }
        $filepath = $backupDir . '/' . $filename;
        if (!file_exists($filepath)) throw new Exception("Archivo no encontrado.");

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db   = $_ENV['DB_NAME'] ?? 'project_aurora_db';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        $passArg = !empty($pass) ? "-p\"$pass\"" : "";
        
        try {
            $mysql = getDbBinary('mysql');
        } catch (Exception $e) {
            throw $e;
        }
        
        $command = "$mysql -h $host -u $user $passArg $db < \"$filepath\" 2>&1";
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $pdo->exec("DELETE FROM user_sessions");
            send_live_notification('global', 'force_logout', ['reason' => 'system_restore']);
            echo json_encode(['success' => true, 'message' => translation('admin.backups.restore_success')]);
        } else {
            $debugCmd = str_replace($pass, '*****', $command);
            $outStr = implode(" | ", $output);
            throw new Exception("Error restaurando (Código $returnVar). CMD: $debugCmd. SALIDA: $outStr");
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>