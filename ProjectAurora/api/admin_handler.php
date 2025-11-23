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
require_once '../includes/logic/i18n_server.php'; // [NUEVO]

// [NUEVO] Cargar idioma
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

// 2. SEGURIDAD: Verificar Rol de Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => trans('admin.error.access_denied')]);
    exit;
}

try {
    // --- OBTENER DATOS DE USUARIO PARA EDICIÓN ---
    if ($action === 'get_user_details') {
        $targetId = $data['target_id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT id, username, email, avatar, role, account_status, suspension_reason, suspension_end_date, deletion_type, deletion_reason, admin_comments FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) throw new Exception(trans('admin.error.user_not_found'));

        $daysRemaining = 0;
        if ($user['account_status'] === 'suspended' && $user['suspension_end_date']) {
            $end = new DateTime($user['suspension_end_date']);
            $now = new DateTime();
            if ($end > $now) {
                $daysRemaining = $now->diff($end)->days + 1; 
            }
        }

        $stmtLogs = $pdo->prepare("
            SELECT sl.*, 
                   u_admin.username as admin_name,
                   u_lifter.username as lifter_name
            FROM user_suspension_logs sl
            LEFT JOIN users u_admin ON sl.admin_id = u_admin.id
            LEFT JOIN users u_lifter ON sl.lifted_by = u_lifter.id
            WHERE sl.user_id = ? 
            ORDER BY sl.started_at DESC
        ");
        $stmtLogs->execute([$targetId]);
        $history = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'user' => $user, 
            'days_remaining' => $daysRemaining,
            'history' => $history
        ]);

    // --- ACTUALIZAR SANCIONES ---
    } elseif ($action === 'update_user_status') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'suspended'; 
        $reason = $data['reason'] ?? null;
        $durationInput = $data['duration_days'] ?? 0; 
        
        $currentAdminId = $_SESSION['user_id'];

        if ($targetId === $currentAdminId) throw new Exception(trans('admin.error.self_sanction'));

        // OBTENER ESTADO ACTUAL
        $stmtCheck = $pdo->prepare("SELECT account_status, suspension_reason, suspension_end_date FROM users WHERE id = ?");
        $stmtCheck->execute([$targetId]);
        $currentUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) throw new Exception(trans('admin.error.user_not_exist'));

        $suspensionEnd = null;
        $finalReason = null;
        $dbDuration = 0;

        // LÓGICA DE NUEVA SUSPENSIÓN
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

            // VALIDACIÓN ANTI-DUPLICADOS
            if ($currentUser['account_status'] === 'suspended') {
                $isReasonSame = ($currentUser['suspension_reason'] === $finalReason);
                
                $isDurationSame = false;
                if ($currentUser['suspension_end_date'] === null && $suspensionEnd === null) {
                    $isDurationSame = true; 
                } elseif ($currentUser['suspension_end_date'] !== null && $suspensionEnd !== null) {
                    $currentEnd = new DateTime($currentUser['suspension_end_date']);
                    $newEnd = new DateTime($suspensionEnd);
                    $diff = abs($currentEnd->getTimestamp() - $newEnd->getTimestamp());
                    if ($diff < 43200) $isDurationSame = true; 
                }

                if ($isReasonSame && $isDurationSame) {
                    throw new Exception(trans('admin.status.already_suspended'));
                }
            }

            $stmtLog = $pdo->prepare("INSERT INTO user_suspension_logs (user_id, admin_id, reason, duration_days, ends_at) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$targetId, $currentAdminId, $finalReason, $dbDuration, $suspensionEnd]);

        } else {
            // LEVANTAR SANCIÓN
            $finalReason = null;
            $suspensionEnd = null;

            $stmtFindLog = $pdo->prepare("SELECT id FROM user_suspension_logs WHERE user_id = ? AND lifted_at IS NULL ORDER BY id DESC LIMIT 1");
            $stmtFindLog->execute([$targetId]);
            $activeLogId = $stmtFindLog->fetchColumn();

            if ($activeLogId) {
                $stmtUpdateLog = $pdo->prepare("UPDATE user_suspension_logs SET lifted_by = ?, lifted_at = NOW() WHERE id = ?");
                $stmtUpdateLog->execute([$currentAdminId, $activeLogId]);
            }
        }

        $sql = "UPDATE users SET account_status = ?, suspension_reason = ?, suspension_end_date = ?, deletion_type = NULL, deletion_reason = NULL, admin_comments = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newStatus, $finalReason, $suspensionEnd, $targetId]);

        if ($newStatus === 'suspended') {
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'suspended']);
        }

        $msg = ($newStatus === 'active') ? trans('admin.success.ban_lifted') : trans('admin.success.ban_applied');
        echo json_encode(['success' => true, 'message' => $msg]);

    // --- GESTIONAR USUARIO ---
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
            if ($delType === 'user_decision' && empty($delReason)) throw new Exception(trans('admin.error.reason_required'));

            $sql = "UPDATE users SET account_status = 'deleted', deletion_type = ?, deletion_reason = ?, admin_comments = ?, suspension_reason = NULL, suspension_end_date = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$delType, $delReason, $adminComments, $targetId]);

            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'deleted']);

            echo json_encode(['success' => true, 'message' => trans('admin.success.account_deleted')]);

        } elseif ($newStatus === 'active') {
            $sql = "UPDATE users SET account_status = 'active', suspension_reason = NULL, suspension_end_date = NULL, deletion_type = NULL, deletion_reason = NULL, admin_comments = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$targetId]);

            echo json_encode(['success' => true, 'message' => trans('global.save_status')]);
        } else {
            throw new Exception(trans('global.action_invalid'));
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>