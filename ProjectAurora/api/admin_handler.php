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
        
        // A. Datos principales
        $stmt = $pdo->prepare("SELECT id, username, email, avatar, role, account_status, suspension_reason, suspension_end_date FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) throw new Exception("Usuario no encontrado.");

        // B. Calcular días restantes si está suspendido actualmente
        $daysRemaining = 0;
        if ($user['account_status'] === 'suspended' && $user['suspension_end_date']) {
            $end = new DateTime($user['suspension_end_date']);
            $now = new DateTime();
            if ($end > $now) {
                $daysRemaining = $now->diff($end)->days + 1; 
            }
        }

        // C. Historial de suspensiones
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

    // --- ACTUALIZAR ESTADO ---
    } elseif ($action === 'update_user_status') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'active';
        $reason = $data['reason'] ?? null;
        // duration_days puede ser int (2,4,6) o string ('permanent')
        $durationInput = $data['duration_days'] ?? 0; 
        
        $currentAdminId = $_SESSION['user_id'];

        if ($targetId === $currentAdminId) {
            throw new Exception("No puedes cambiar tu propio estado.");
        }

        // Variables para DB
        $suspensionEnd = null;
        $finalReason = null;
        $dbDuration = 0; // Para el log histórico

        // Lógica según estado
        if ($newStatus === 'suspended') {
            if (empty($reason)) throw new Exception("Debes especificar una razón.");
            $finalReason = $reason;

            if ($durationInput === 'permanent') {
                // PERMANENTE: Fecha fin es NULL
                $suspensionEnd = null; 
                $dbDuration = -1; // Convención para permanente en logs si lo deseas, o 0
            } else {
                // TEMPORAL: Calcular fecha
                $days = (int)$durationInput;
                if ($days < 1) throw new Exception("Duración inválida.");
                
                $suspensionEnd = date('Y-m-d H:i:s', strtotime("+$days days"));
                $dbDuration = $days;
            }

            // Guardar en el log histórico
            $stmtLog = $pdo->prepare("INSERT INTO user_suspension_logs (user_id, admin_id, reason, duration_days, ends_at) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$targetId, $currentAdminId, $finalReason, $dbDuration, $suspensionEnd]);
        } 
        
        // Si reactivamos la cuenta, limpiamos los campos de suspensión
        if ($newStatus === 'active') {
            $finalReason = null;
            $suspensionEnd = null;
        }

        // Actualizar BD Principal
        $sql = "UPDATE users SET account_status = ?, suspension_reason = ?, suspension_end_date = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newStatus, $finalReason, $suspensionEnd, $targetId]);

        // ACCIONES POST-CAMBIO (Expulsión inmediata)
        if ($newStatus === 'suspended') {
            // 1. Borrar sesiones PHP
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            
            // 2. Enviar señal WebSocket para logout inmediato
            // Si es permanente, enviamos 'deleted' (o 'suspended' pero sin fecha fin en frontend status page)
            // Si es temporal, enviamos 'suspended'
            send_live_notification($targetId, 'force_logout', [
                'reason' => 'suspended'
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Estado actualizado y registrado.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>