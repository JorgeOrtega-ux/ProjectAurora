<?php
// api/services/admin_service.php

/**
 * Servicio de Administración
 * Maneja estadísticas del dashboard, configuración del servidor y gestión de usuarios.
 */

function get_dashboard_stats($pdo) {
    try {
        // 1. Total usuarios
        $stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
        $totalUsers = $stmtUsers->fetchColumn();

        // 2. Sesiones Activas
        $stmtSessions = $pdo->query("SELECT COUNT(*) FROM active_sessions");
        $activeSessions = $stmtSessions->fetchColumn();

        // 3. Estado Servidor
        $stmtConfig = $pdo->query("SELECT maintenance_mode FROM server_config WHERE id=1");
        $maintenanceMode = $stmtConfig->fetchColumn();

        // 4. Últimos logs de seguridad
        $stmtLogs = $pdo->query("SELECT user_identifier, action_type, ip_address, created_at FROM security_logs ORDER BY id DESC LIMIT 5");
        $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $data = [
            'total_users' => $totalUsers,
            'active_sessions' => $activeSessions,
            'maintenance_mode' => $maintenanceMode,
            'recent_logs' => $recentLogs
        ];

        return ['status' => 'success', 'message' => 'OK', 'data' => $data];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function get_server_config_data($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM server_config WHERE id=1");
        $data = $stmt->fetch();
        
        if(!$data) {
            $defaultDomains = 'gmail.com,outlook.com,hotmail.com,icloud.com,yahoo.com';
            $sql = "INSERT INTO server_config (id, maintenance_mode, allow_registrations, min_password_length, max_password_length, min_username_length, max_username_length, max_email_length, max_login_attempts, lockout_time_minutes, code_resend_cooldown, username_cooldown, email_cooldown, profile_picture_max_size, allowed_email_domains) VALUES (1, 0, 1, 8, 72, 6, 32, 255, 5, 5, 60, 30, 12, 2, '$defaultDomains')";
            $pdo->exec($sql);
            
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
                'profile_picture_max_size' => 2,
                'allowed_email_domains' => $defaultDomains
            ];
        }
        return ['status' => 'success', 'message' => 'OK', 'data' => $data];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function update_server_configuration($pdo, $userId, $input) {
    $maintenance = isset($input['maintenance_mode']) ? (int)$input['maintenance_mode'] : 0;
    $registrations = isset($input['allow_registrations']) ? (int)$input['allow_registrations'] : 1;
    $minPass = isset($input['min_password_length']) ? (int)$input['min_password_length'] : 8;
    $maxPass = isset($input['max_password_length']) ? (int)$input['max_password_length'] : 72;
    $minUser = isset($input['min_username_length']) ? (int)$input['min_username_length'] : 6;
    $maxUser = isset($input['max_username_length']) ? (int)$input['max_username_length'] : 32;
    $maxEmail = isset($input['max_email_length']) ? (int)$input['max_email_length'] : 255;
    $maxLoginAttempts = isset($input['max_login_attempts']) ? (int)$input['max_login_attempts'] : 5;
    $lockoutTime = isset($input['lockout_time_minutes']) ? (int)$input['lockout_time_minutes'] : 5;
    $codeResend = isset($input['code_resend_cooldown']) ? (int)$input['code_resend_cooldown'] : 60;
    $userCooldown = isset($input['username_cooldown']) ? (int)$input['username_cooldown'] : 30;
    $emailCooldown = isset($input['email_cooldown']) ? (int)$input['email_cooldown'] : 12;
    $profilePicSize = isset($input['profile_picture_max_size']) ? (int)$input['profile_picture_max_size'] : 2;
    $allowedDomains = isset($input['allowed_email_domains']) ? trim($input['allowed_email_domains']) : '';

    if ($minPass < 1) $minPass = 1;
    if ($maxPass < $minPass) $maxPass = $minPass;
    if ($minUser < 1) $minUser = 1;
    if ($maxUser < $minUser) $maxUser = $minUser;
    if ($maxEmail < 5) $maxEmail = 5;
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
                profile_picture_max_size = ?,
                allowed_email_domains = ?
                WHERE id = 1";

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([
            $maintenance, $registrations, 
            $minPass, $maxPass, $minUser, $maxUser, $maxEmail,
            $maxLoginAttempts, $lockoutTime, $codeResend, $userCooldown, $emailCooldown, $profilePicSize,
            $allowedDomains
        ])) {
            logSecurityEvent($pdo, "uid_".$userId, "server_config_update");
            return ['status' => 'success', 'message' => __('api.success.preferences_saved')];
        } else {
            return ['status' => 'error', 'message' => __('api.error.db_error')];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}

function get_all_users_list($pdo, $sortBy = 'newest', $search = '') {
    try {
        // Base query
        $sql = "SELECT id, username, email, uuid, account_status, created_at, role FROM users";
        $params = [];
        
        // 1. Aplicar búsqueda si existe
        if (!empty($search)) {
            $sql .= " WHERE username LIKE ? OR email LIKE ?";
            $term = "%" . $search . "%";
            $params[] = $term;
            $params[] = $term;
        }

        // 2. Aplicar ordenamiento
        switch ($sortBy) {
            case 'az':
                $sql .= " ORDER BY username ASC";
                break;
            case 'za':
                $sql .= " ORDER BY username DESC";
                break;
            case 'oldest':
                $sql .= " ORDER BY created_at ASC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY created_at DESC";
                break;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Procesamiento de avatares
        foreach ($users as &$user) {
            $uuid = $user['uuid'];
            $customPath = DIR_CUSTOM . $uuid . '.png';
            $defaultPath = DIR_DEFAULT . $uuid . '.png';
            
            // Determinar URL base
            // (En un entorno real, define esto globalmente o pásalo)
            $avatarUrl = URL_BASE_AVATARS . 'default/' . $uuid . '.png';

            if (file_exists($customPath)) {
                $avatarUrl = URL_BASE_AVATARS . 'custom/' . $uuid . '.png';
            }
            
            $user['avatar_url'] = $avatarUrl;
        }
        
        return ['status' => 'success', 'message' => 'OK', 'data' => $users];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => __('api.error.db_error')];
    }
}
?>