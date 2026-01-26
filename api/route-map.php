<?php
// api/route-map.php

return [
    // === AUTH ===
    'auth.register_step_1'    => ['file' => 'handlers/auth-handler.php', 'action' => 'register_step_1'],
    'auth.initiate_verify'    => ['file' => 'handlers/auth-handler.php', 'action' => 'initiate_verification'],
    'auth.complete_register'  => ['file' => 'handlers/auth-handler.php', 'action' => 'complete_register'],
    'auth.resend_code'        => ['file' => 'handlers/auth-handler.php', 'action' => 'resend_code'],
    'auth.login'              => ['file' => 'handlers/auth-handler.php', 'action' => 'login'],
    'auth.verify_2fa'         => ['file' => 'handlers/auth-handler.php', 'action' => 'verify_2fa_login'],
    'auth.request_reset'      => ['file' => 'handlers/auth-handler.php', 'action' => 'request_reset'],
    'auth.reset_password'     => ['file' => 'handlers/auth-handler.php', 'action' => 'reset_password'],
    'auth.logout'             => ['file' => 'handlers/auth-handler.php', 'action' => 'logout'],
    'auth.get_ws_token'       => ['file' => 'handlers/auth-handler.php', 'action' => 'get_ws_token'],

    // === SETTINGS ===
    'settings.update_pref'    => ['file' => 'handlers/settings-handler.php', 'action' => 'update_preference'],
    'settings.upload_avatar'  => ['file' => 'handlers/settings-handler.php', 'action' => 'upload_avatar'],
    'settings.delete_avatar'  => ['file' => 'handlers/settings-handler.php', 'action' => 'delete_avatar'],
    'settings.email_status'   => ['file' => 'handlers/settings-handler.php', 'action' => 'get_email_edit_status'],
    'settings.req_email_code' => ['file' => 'handlers/settings-handler.php', 'action' => 'request_email_change_verification'],
    'settings.ver_email_code' => ['file' => 'handlers/settings-handler.php', 'action' => 'verify_email_change_code'],
    'settings.update_profile' => ['file' => 'handlers/settings-handler.php', 'action' => 'update_profile'],
    'settings.get_sessions'   => ['file' => 'handlers/settings-handler.php', 'action' => 'get_sessions'],
    'settings.revoke_session' => ['file' => 'handlers/settings-handler.php', 'action' => 'revoke_session'],
    'settings.revoke_all'     => ['file' => 'handlers/settings-handler.php', 'action' => 'revoke_all_sessions'],
    'settings.delete_acct'    => ['file' => 'handlers/settings-handler.php', 'action' => 'delete_account'],
    'settings.init_2fa'       => ['file' => 'handlers/settings-handler.php', 'action' => 'init_2fa'],
    'settings.enable_2fa'     => ['file' => 'handlers/settings-handler.php', 'action' => 'enable_2fa'],
    'settings.disable_2fa'    => ['file' => 'handlers/settings-handler.php', 'action' => 'disable_2fa'],
    'settings.get_rec_status' => ['file' => 'handlers/settings-handler.php', 'action' => 'get_recovery_status'],
    'settings.regen_codes'    => ['file' => 'handlers/settings-handler.php', 'action' => 'regenerate_recovery_codes'],
    'settings.val_password'   => ['file' => 'handlers/settings-handler.php', 'action' => 'validate_current_password'],
    'settings.change_pass'    => ['file' => 'handlers/settings-handler.php', 'action' => 'change_password'],

    // === ADMIN ===
    'admin.get_users'         => ['file' => 'handlers/admin-handler.php', 'action' => 'get_all_users'],
    'admin.get_details'       => ['file' => 'handlers/admin-handler.php', 'action' => 'get_user_details'],
    'admin.update_profile'    => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_profile'],
    'admin.update_role'       => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_role'],
    'admin.update_status'     => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_status'],
    'admin.update_pref'       => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_preference'],
    'admin.upload_avatar'     => ['file' => 'handlers/admin-handler.php', 'action' => 'upload_user_avatar'],
    'admin.delete_avatar'     => ['file' => 'handlers/admin-handler.php', 'action' => 'delete_user_avatar'],
    'admin.get_server_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'get_server_config'],
    'admin.update_server_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'update_server_config'],
    
    // BACKUPS ADMIN
    'admin.get_backups'       => ['file' => 'handlers/admin-handler.php', 'action' => 'get_backups'],
    'admin.create_backup'     => ['file' => 'handlers/admin-handler.php', 'action' => 'create_backup'],
    'admin.restore_backup'    => ['file' => 'handlers/admin-handler.php', 'action' => 'restore_backup'],
    'admin.delete_backup'     => ['file' => 'handlers/admin-handler.php', 'action' => 'delete_backup'],
    'admin.get_backup_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'get_backup_config'],
    'admin.upd_backup_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'update_backup_config'],

    // AUDITORÍA
    'admin.get_audit_logs'    => ['file' => 'handlers/admin-handler.php', 'action' => 'get_audit_logs'],

    // SYSTEM (PYTHON WORKER)
    'system.create_backup'    => ['file' => 'handlers/system-handler.php', 'action' => 'create_backup_auto'],

    // [NUEVO] GESTIÓN DE ARCHIVOS LOG
    'admin.get_log_files'     => ['file' => 'handlers/admin-handler.php', 'action' => 'get_log_files'],
    'admin.delete_log_files'  => ['file' => 'handlers/admin-handler.php', 'action' => 'delete_log_files'],
    'admin.download_log_file' => ['file' => 'handlers/admin-handler.php', 'action' => 'download_log_file'], // Opcional si se hace vía GET directo o handler
];
?>