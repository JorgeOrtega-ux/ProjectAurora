<?php
// includes/logic/admin/users_service.php

function admin_audit_log($pdo, $targetUserId, $type, $oldVal, $newVal, $adminId) {
    $ip = get_client_ip();
    $stmt = $pdo->prepare("INSERT INTO user_audit_logs (user_id, performed_by, change_type, old_value, new_value, changed_by_ip, changed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$targetUserId, $adminId, $type, $oldVal, $newVal, $ip]);
    
    // Ajuste de ruta para logs desde la carpeta includes/logic/admin/
    $logDir = __DIR__ . '/../../../logs';
    if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
    $logFile = $logDir . '/admin_actions.log';
    
    $msg = sprintf(
        "[%s] Admin(ID:%d) changed %s for User(ID:%d). Old: %s -> New: %s",
        date('Y-m-d H:i:s'), $adminId, $type, $targetUserId, $oldVal, $newVal
    );
    file_put_contents($logFile, $msg . PHP_EOL, FILE_APPEND);
}

function get_user_details($pdo, $targetId) {
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
    
    return ['success' => true, 'user' => $user, 'days_remaining' => $daysRemaining, 'history' => $history];
}

function update_user_status($pdo, $currentAdminId, $targetId, $newStatus, $reason, $durationInput) {
    if ($targetId === $currentAdminId) throw new Exception(translation('admin.error.self_sanction'));
    
    $stmtCheck = $pdo->prepare("SELECT account_status FROM users WHERE id = ?"); 
    $stmtCheck->execute([$targetId]);
    if (!$stmtCheck->fetch()) throw new Exception(translation('admin.error.user_not_exist'));
    
    $suspensionEnd = null; 
    $finalReason = null; 
    $dbDuration = 0;
    
    if ($newStatus === 'suspended') {
        if (empty($reason)) throw new Exception(translation('admin.error.reason_required'));
        $finalReason = $reason;
        
        if ($durationInput === 'permanent') { 
            $suspensionEnd = null; 
            $dbDuration = -1; 
        } else { 
            $days = (int)$durationInput; 
            if ($days < 1) throw new Exception(translation('global.action_invalid')); 
            $suspensionEnd = date('Y-m-d H:i:s', strtotime("+$days days")); 
            $dbDuration = $days; 
        }
        
        $stmtLog = $pdo->prepare("INSERT INTO user_suspension_logs (user_id, admin_id, reason, duration_days, ends_at) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([$targetId, $currentAdminId, $finalReason, $dbDuration, $suspensionEnd]);
    } else {
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
    
    return ['success' => true, 'message' => ($newStatus === 'active') ? translation('admin.success.ban_lifted') : translation('admin.success.ban_applied')];
}

function update_user_general($pdo, $currentAdminId, $targetId, $newStatus, $data) {
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
        
        return ['success' => true, 'message' => translation('admin.success.account_deleted')];
    } elseif ($newStatus === 'active') {
        $pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")->execute([$targetId]);
        return ['success' => true, 'message' => translation('global.save_status')];
    }
}

function update_user_role($pdo, $currentAdminId, $currentAdminRole, $targetId, $newRole) {
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
        return ['success' => true, 'message' => translation('global.save_status')];
    } else { 
        throw new Exception(translation('global.error_connection')); 
    }
}

function admin_update_profile_picture($pdo, $currentAdminId, $currentAdminRole, $targetId, $fileData) {
    if (!$targetId) throw new Exception(translation('global.action_invalid'));
    if ($targetId === $currentAdminId) throw new Exception("Usa Configuración para editar tu perfil.");

    $stmtTarget = $pdo->prepare("SELECT role, profile_picture FROM users WHERE id = ?"); 
    $stmtTarget->execute([$targetId]); 
    $targetData = $stmtTarget->fetch();
    if (!$targetData) throw new Exception(translation('admin.error.user_not_exist'));
    
    $targetRole = $targetData['role'];
    if ($targetRole === 'founder') throw new Exception("No puedes editar al Fundador.");
    if ($currentAdminRole === 'administrator' && $targetRole === 'administrator') throw new Exception("No puedes editar a otro Administrador.");

    if (!isset($fileData['profile_picture']) || $fileData['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(translation('settings.profile.error_format'));
    }
    
    $file = $fileData['profile_picture'];
    $uploadDir = __DIR__ . '/../../../public/assets/uploads/profile_pictures/custom/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    // [SECURITY PATCH] Lista blanca
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeType, $allowedMimes)) {
        throw new Exception(translation('settings.profile.error_format'));
    }

    $safeExtension = $allowedMimes[$mimeType];
    $newFileName = generate_uuid() . '.' . $safeExtension;
    
    $destination = $uploadDir . $newFileName;
    $dbPath = 'assets/uploads/profile_pictures/custom/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) throw new Exception(translation('global.error_connection'));

    $oldPic = $targetData['profile_picture'];
    $publicDir = __DIR__ . '/../../../public/';
    if ($oldPic && file_exists($publicDir . $oldPic) && strpos($oldPic, 'custom/') !== false) {
        @unlink($publicDir . $oldPic);
    }

    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    if ($stmt->execute([$dbPath, $targetId])) {
        admin_audit_log($pdo, $targetId, 'profile_picture', $oldPic, $dbPath, $currentAdminId);
        $msg = translation('notifications.admin_update_pfp');
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'admin_alert', ?, NOW())")->execute([$targetId, $msg]);
        send_live_notification($targetId, 'admin_notification', ['message' => $msg]);
        return ['success' => true, 'message' => translation('global.save_status'), 'path' => $dbPath];
    } else throw new Exception(translation('global.error_connection'));
}

function admin_remove_profile_picture($pdo, $currentAdminId, $targetId) {
    if (!$targetId) throw new Exception(translation('global.action_invalid'));
    $stmt = $pdo->prepare("SELECT username, role, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $user = $stmt->fetch();
    
    if (!$user) throw new Exception(translation('admin.error.user_not_exist'));
    if ($user['role'] === 'founder' && $currentAdminId !== $targetId) throw new Exception("No puedes editar al Fundador.");
    
    $oldPic = $user['profile_picture'];
    $color = get_random_color();
    $uuid = generate_uuid();
    $apiUrl = "https://ui-avatars.com/api/?name={$user['username']}&size=256&background={$color}&color=ffffff&bold=true&length=1";
    $newFileName = $uuid . '.png';
    
    $uploadDir = __DIR__ . '/../../../public/assets/uploads/profile_pictures/default/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $destPath = $uploadDir . $newFileName;
    $dbPath = 'assets/uploads/profile_pictures/default/' . $newFileName;
    
    $imageContent = @file_get_contents($apiUrl);
    if ($imageContent !== false) file_put_contents($destPath, $imageContent);

    $publicDir = __DIR__ . '/../../../public/';
    if ($oldPic && file_exists($publicDir . $oldPic) && strpos($oldPic, 'custom/') !== false) {
        @unlink($publicDir . $oldPic);
    }

    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    if ($stmt->execute([$dbPath, $targetId])) {
        admin_audit_log($pdo, $targetId, 'profile_picture', $oldPic, 'default_reset', $currentAdminId);
        $msg = translation('notifications.admin_reset_pfp');
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'admin_alert', ?, NOW())")->execute([$targetId, $msg]);
        send_live_notification($targetId, 'admin_notification', ['message' => $msg]);
        return ['success' => true, 'message' => translation('settings.profile.reset'), 'path' => $dbPath];
    } else throw new Exception(translation('global.error_connection'));
}

function admin_update_username($pdo, $currentAdminId, $targetId, $newUsername) {
    if ($targetId === $currentAdminId) throw new Exception("Usa Configuración para editar tu perfil.");
    $newUsername = trim($newUsername);
    if (empty($newUsername)) throw new Exception("Nombre de usuario vacío");
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) throw new Exception("Formato inválido (letras, números, _)");

    $stmtUser = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmtUser->execute([$targetId]);
    $user = $stmtUser->fetch();
    
    if (!$user) throw new Exception(translation('admin.error.user_not_exist'));
    if ($user['role'] === 'founder' && $currentAdminId !== $targetId) throw new Exception("No puedes editar al Fundador.");
    if ($user['username'] === $newUsername) return ['success' => true, 'message' => translation('global.save_status')];

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmtCheck->execute([$newUsername, $targetId]);
    if ($stmtCheck->rowCount() > 0) throw new Exception(translation('settings.username.taken'));

    $stmtUpd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
    if ($stmtUpd->execute([$newUsername, $targetId])) {
        admin_audit_log($pdo, $targetId, 'username', $user['username'], $newUsername, $currentAdminId);
        $msg = translation('notifications.admin_update_username', ['username' => htmlspecialchars($newUsername)]);
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'admin_alert', ?, NOW())")->execute([$targetId, $msg]);
        send_live_notification($targetId, 'admin_notification', ['message' => $msg]);
        return ['success' => true, 'message' => translation('global.save_status')];
    } else throw new Exception(translation('global.error_connection'));
}

function admin_update_email($pdo, $currentAdminId, $targetId, $newEmail) {
    if ($targetId === $currentAdminId) throw new Exception("Usa Configuración para editar tu perfil.");
    $newEmail = strtolower(trim($newEmail));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) throw new Exception(translation('auth.errors.email_invalid_domain'));
    if (!is_allowed_domain($newEmail, $pdo)) throw new Exception(translation('auth.errors.email_domain_restricted'));

    $stmtUser = $pdo->prepare("SELECT email, role FROM users WHERE id = ?");
    $stmtUser->execute([$targetId]);
    $user = $stmtUser->fetch();
    if (!$user) throw new Exception(translation('admin.error.user_not_exist'));
    if ($user['role'] === 'founder' && $currentAdminId !== $targetId) throw new Exception("No puedes editar al Fundador.");
    if ($user['email'] === $newEmail) return ['success' => true, 'message' => translation('global.save_status')];

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmtCheck->execute([$newEmail, $targetId]);
    if ($stmtCheck->rowCount() > 0) throw new Exception(translation('auth.errors.email_exists'));

    $stmtUpd = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    if ($stmtUpd->execute([$newEmail, $targetId])) {
        admin_audit_log($pdo, $targetId, 'email', $user['email'], $newEmail, $currentAdminId);
        $msg = translation('notifications.admin_update_email', ['email' => htmlspecialchars($newEmail)]);
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'admin_alert', ?, NOW())")->execute([$targetId, $msg]);
        send_live_notification($targetId, 'admin_notification', ['message' => $msg]);
        return ['success' => true, 'message' => translation('global.save_status')];
    } else throw new Exception(translation('global.error_connection'));
}
?>