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
        
        $stmt = $pdo->prepare("SELECT id, username, email, avatar, role, account_status, suspension_reason, suspension_end_date FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) throw new Exception("Usuario no encontrado.");

        // Calcular días restantes si está suspendido
        $daysRemaining = 0;
        if ($user['suspension_end_date']) {
            $end = new DateTime($user['suspension_end_date']);
            $now = new DateTime();
            if ($end > $now) {
                $daysRemaining = $now->diff($end)->days + 1; // +1 para redondear hacia arriba
            }
        }

        echo json_encode(['success' => true, 'user' => $user, 'days_remaining' => $daysRemaining]);

    // --- ACTUALIZAR ESTADO ---
    } elseif ($action === 'update_user_status') {
        $targetId = (int)($data['target_id'] ?? 0);
        $newStatus = $data['status'] ?? 'active';
        $reason = $data['reason'] ?? null;
        $durationDays = (int)($data['days'] ?? 0);

        if ($targetId === $_SESSION['user_id']) {
            throw new Exception("No puedes cambiar tu propio estado.");
        }

        // Lógica según estado
        $suspensionEnd = null;
        $finalReason = null;

        if ($newStatus === 'suspended') {
            if ($durationDays < 1) throw new Exception("Debes especificar una duración válida.");
            if (empty($reason)) throw new Exception("Debes especificar una razón.");
            
            $finalReason = $reason;
            $suspensionEnd = date('Y-m-d H:i:s', strtotime("+$durationDays days"));
        } elseif ($newStatus === 'deleted') {
            $finalReason = "Cuenta eliminada por administración.";
        }

        // Actualizar BD
        $sql = "UPDATE users SET account_status = ?, suspension_reason = ?, suspension_end_date = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newStatus, $finalReason, $suspensionEnd, $targetId]);

        // ACCIONES POST-CAMBIO (Expulsión)
        if ($newStatus !== 'active') {
            // 1. Borrar sesiones PHP
            $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$targetId]);
            
            // 2. Enviar señal WebSocket para logout inmediato en frontend
            send_live_notification($targetId, 'force_logout', [
                'reason' => $newStatus
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Estado del usuario actualizado correctamente.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>