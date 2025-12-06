<?php
// includes/logic/admin/dashboard_service.php

function get_dashboard_stats($pdo) {
    // 1. Usuarios Totales
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM users WHERE account_status != 'deleted'");
    $totalUsers = $stmtTotal->fetchColumn();
    
    // 2. Usuarios Online (Actividad reciente en 5 min)
    $stmtOnline = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_activity > (NOW() - INTERVAL 5 MINUTE)");
    $onlineUsers = $stmtOnline->fetchColumn();
    
    // 3. Nuevos usuarios hoy
    $stmtNew = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
    $newUsersToday = $stmtNew->fetchColumn();
    
    // 4. Sesiones Activas totales
    $stmtSessions = $pdo->query("SELECT COUNT(*) FROM user_sessions");
    $activeSessions = $stmtSessions->fetchColumn();

    // 5. [NUEVO] Comunidades Totales
    $stmtComm = $pdo->query("SELECT COUNT(*) FROM communities");
    $totalCommunities = $stmtComm->fetchColumn();

    // 6. [NUEVO] Mensajes Hoy (Comunidad + Privados)
    $stmtMsgs = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM community_messages WHERE DATE(created_at) = CURDATE()) + 
            (SELECT COUNT(*) FROM private_messages WHERE DATE(created_at) = CURDATE())
    ");
    $messagesToday = $stmtMsgs->fetchColumn();

    // 7. [NUEVO] Reportes (Total en la tabla)
    // Nota: Si implementas estados (resuelto/pendiente), añade el WHERE aquí.
    $stmtReports = $pdo->query("SELECT COUNT(*) FROM community_message_reports");
    $pendingReports = $stmtReports->fetchColumn();

    // 8. [NUEVO] Archivos Totales
    $stmtFiles = $pdo->query("SELECT COUNT(*) FROM community_files");
    $totalFiles = $stmtFiles->fetchColumn();
    
    return ['success' => true, 'stats' => [
        'total_users' => $totalUsers, 
        'online_users' => $onlineUsers, 
        'new_users_today' => $newUsersToday, 
        'active_sessions' => $activeSessions,
        // Nuevos campos
        'total_communities' => $totalCommunities,
        'messages_today' => $messagesToday,
        'pending_reports' => $pendingReports,
        'total_files' => $totalFiles
    ]];
}

function get_alert_status($pdo) {
    $stmt = $pdo->query("SELECT type, instance_id, meta_data FROM system_alerts_history WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $activeAlert = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['success' => true, 'active_alert' => $activeAlert];
}

function activate_alert($pdo, $currentAdminId, $type, $metaData) {
    if (empty($type)) throw new Exception(translation('global.action_invalid'));
    
    $stmtCheck = $pdo->query("SELECT id FROM system_alerts_history WHERE status = 'active' LIMIT 1");
    if ($stmtCheck->rowCount() > 0) throw new Exception(translation('admin.error.alert_active'));
    
    $instanceId = generate_uuid(); 
    $metaDataJson = json_encode($metaData);
    
    $stmt = $pdo->prepare("INSERT INTO system_alerts_history (type, instance_id, status, admin_id, meta_data, started_at) VALUES (?, ?, 'active', ?, ?, NOW())");
    
    if ($stmt->execute([$type, $instanceId, $currentAdminId, $metaDataJson])) {
        send_live_notification('global', 'system_alert_update', [
            'status' => 'active', 
            'type' => $type, 
            'instance_id' => $instanceId, 
            'meta_data' => $metaData
        ]);
        return ['success' => true, 'message' => translation('admin.alerts.success_emit')];
    } else {
        throw new Exception(translation('global.error_connection'));
    }
}

function stop_alert($pdo) {
    $stmt = $pdo->prepare("UPDATE system_alerts_history SET status = 'stopped', stopped_at = NOW() WHERE status = 'active'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        send_live_notification('global', 'system_alert_update', ['status' => 'inactive']);
        return ['success' => true, 'message' => translation('admin.alerts.success_stop')];
    } else {
        return ['success' => true, 'message' => 'No había alertas activas.'];
    }
}
?>