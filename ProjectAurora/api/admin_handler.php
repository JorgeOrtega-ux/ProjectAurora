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

require_once '../config/database.php';
require_once '../config/utilities.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

// 1. SEGURIDAD: Verificar CSRF
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

// 2. SEGURIDAD: Verificar Rol de Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

try {
    // --- OBTENER DATOS DE USUARIO PARA EDICIÓN ---
    if ($action === 'get_user_details') {
        $targetId = $data['target_id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT id, username, email, avatar, role, account_status, suspension_reason, suspension_end_date, deletion_type, deletion_reason, admin_comments FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) throw new Exception("Usuario no encontrado.");

        // Calcular días restantes si está suspendido
        $daysRemaining = 0;
        if ($user['account_status'] === 'suspended' && $user['suspension_end_date']) {
            $end = new DateTime($user['suspension_end_date']);
            $now = new DateTime();
            if ($end > $now) {
                $daysRemaining = $now->diff($end)->days + 1; 
            }
        }

        $stmtLogs = $pdo->prepare("
            SELECT sl.*, u.username as admin_name 
            FROM user_suspension_logs sl
            LEFT JOIN users u ON sl.admin_id = u.id
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

    // --- ACTUALIZAR SANCIONES (SOLO SUSPENDIDO) ---
    } elseif ($action === 'update_user_status') {
        $targetId = (int)($data['target_id'] ?? 0);
        // status debe ser 'suspended' obligatoriamente aquí, 
        // a menos que se use para quitar sanción (reactivar).
        // Pero la UI de sanciones solo enviará suspended.
        $newStatus = $data['status'] ?? 'suspended'; 
        $reason = $data['reason'] ?? null;
        $durationInput = $data['duration_days'] ?? 0; 
        
        $currentAdminId = $_SESSION['user_id'];

        if ($targetId === $currentAdminId) throw new Exception("No puedes sancionarte a ti mismo.");

        $suspensionEnd = null;
        $finalReason = null;
        $dbDuration = 0;

        if ($newStatus === 'suspended') {
            if (empty($reason)) throw new Exception("Debes especificar una razón.");
            $finalReason = $reason;

            if ($durationInput === 'permanent') {
                $suspensionEnd = null; 
                $dbDuration = -1; 
            } else {
                $days = (int)$durationInput;
                if ($days < 1) throw new Exception("Duración inválida.");
                $suspensionEnd = date('Y-m-d H:i:s', strtotime("+$days days"));
                $dbDuration = $days;
            }

            $stmtLog = $pdo->prepare("INSERT INTO user_suspension_logs (user_id, admin_id, reason, duration_days, ends_at) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$targetId, $currentAdminId, $finalReason, $dbDuration, $suspensionEnd]);
        } else {
            // Si por alguna razón mandan 'active' desde aquí (levantar castigo)
            $finalReason = null;
            $suspensionEnd = null;
        }

        // Limpiamos campos de eliminación si existían, ya que ahora es una sanción
        $sql = "UPDATE users SET account_status = ?, suspension_reason = ?, suspension_end_date = ?, deletion_type = NULL, deletion_reason = NULL, admin_comments = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newStatus, $finalReason, $suspensionEnd, $targetId]);

        if ($newStatus === 'suspended') {
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'suspended']);
        }

        echo json_encode(['success' => true, 'message' => 'Sanción aplicada correctamente.']);

    // --- GESTIONAR USUARIO (ACTIVO / ELIMINADO) ---
    } elseif ($action === 'update_user_general') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'active'; // 'active' o 'deleted'
        $currentAdminId = $_SESSION['user_id'];

        if ($targetId === $currentAdminId) throw new Exception("No puedes modificar tu propia cuenta aquí.");

        if ($newStatus === 'deleted') {
            $delType = $data['deletion_type'] ?? 'admin_decision';
            $delReason = $data['deletion_reason'] ?? null; // Solo si es user_decision
            $adminComments = $data['admin_comments'] ?? null;

            if (empty($adminComments)) throw new Exception("Debes escribir un comentario administrativo.");
            
            if ($delType === 'user_decision' && empty($delReason)) {
                throw new Exception("Debes especificar la razón del usuario.");
            }

            // Actualizamos estado y guardamos los detalles
            $sql = "UPDATE users SET account_status = 'deleted', deletion_type = ?, deletion_reason = ?, admin_comments = ?, suspension_reason = NULL, suspension_end_date = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$delType, $delReason, $adminComments, $targetId]);

            // Expulsión inmediata
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            send_live_notification($targetId, 'force_logout', ['reason' => 'deleted']);

            echo json_encode(['success' => true, 'message' => 'Cuenta eliminada correctamente.']);

        } elseif ($newStatus === 'active') {
            // Reactivar cuenta: Limpiamos todo
            $sql = "UPDATE users SET account_status = 'active', suspension_reason = NULL, suspension_end_date = NULL, deletion_type = NULL, deletion_reason = NULL, admin_comments = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$targetId]);

            echo json_encode(['success' => true, 'message' => 'Cuenta reactivada correctamente.']);
        } else {
            throw new Exception("Estado no válido para esta sección.");
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>