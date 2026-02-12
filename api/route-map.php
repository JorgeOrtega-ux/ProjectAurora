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
    'auth.get_status'         => ['file' => 'handlers/auth-handler.php', 'action' => 'get_registration_status'],

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
    'admin.dashboard_stats'   => ['file' => 'handlers/admin-handler.php', 'action' => 'get_dashboard_stats'],
    'admin.create_system_alert'     => ['file' => 'handlers/admin-handler.php', 'action' => 'create_system_alert'],
    'admin.deactivate_system_alert' => ['file' => 'handlers/admin-handler.php', 'action' => 'deactivate_system_alert'],
    'admin.get_active_alert'        => ['file' => 'handlers/admin-handler.php', 'action' => 'get_active_alert'],

    'admin.get_users'         => ['file' => 'handlers/admin-handler.php', 'action' => 'get_all_users'],
    'admin.get_details'       => ['file' => 'handlers/admin-handler.php', 'action' => 'get_user_details'],
    'admin.update_profile'    => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_profile'],
    'admin.update_role'       => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_role'],
    'admin.update_status'     => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_status'],
    'admin.disable_2fa'       => ['file' => 'handlers/admin-handler.php', 'action' => 'disable_user_2fa'], 
    'admin.update_pref'       => ['file' => 'handlers/admin-handler.php', 'action' => 'update_user_preference'],
    'admin.upload_avatar'     => ['file' => 'handlers/admin-handler.php', 'action' => 'upload_user_avatar'],
    'admin.delete_avatar'     => ['file' => 'handlers/admin-handler.php', 'action' => 'delete_user_avatar'],
    'admin.get_server_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'get_server_config'],
    'admin.update_server_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'update_server_config'],
    
    // [NUEVO] Toggle Panic Mode
    'admin.toggle_panic'      => ['file' => 'handlers/admin-handler.php', 'action' => 'toggle_panic_mode'],

    'admin.request_download'  => ['file' => 'handlers/admin-handler.php', 'action' => 'request_download_token'],

    // BACKUPS ADMIN
    'admin.get_backups'       => ['file' => 'handlers/admin-handler.php', 'action' => 'get_backups'],
    'admin.create_backup'     => ['file' => 'handlers/admin-handler.php', 'action' => 'create_backup'],
    'admin.restore_backup'    => ['file' => 'handlers/admin-handler.php', 'action' => 'restore_backup'],
    'admin.delete_backup'     => ['file' => 'handlers/admin-handler.php', 'action' => 'delete_backup'],
    'admin.get_backup_content'=> ['file' => 'handlers/admin-handler.php', 'action' => 'get_backup_content'],
    'admin.get_backup_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'get_backup_config'],
    'admin.upd_backup_config' => ['file' => 'handlers/admin-handler.php', 'action' => 'update_backup_config'],

    // AUDITORÍA
    'admin.get_audit_logs'    => ['file' => 'handlers/admin-handler.php', 'action' => 'get_audit_logs'],

    // LOGS DE ARCHIVO Y VISOR
    'admin.get_log_files'     => ['file' => 'handlers/admin-handler.php', 'action' => 'get_log_files'],
    'admin.delete_log_files'  => ['file' => 'handlers/admin-handler.php', 'action' => 'delete_log_files'],
    'admin.get_log_content'   => ['file' => 'handlers/admin-handler.php', 'action' => 'get_log_content'],

    // REDIS MANAGER
    'admin.redis_stats'       => ['file' => 'handlers/admin-handler.php', 'action' => 'get_redis_stats'],
    'admin.redis_keys'        => ['file' => 'handlers/admin-handler.php', 'action' => 'get_redis_keys'],
    'admin.redis_get'         => ['file' => 'handlers/admin-handler.php', 'action' => 'get_redis_value'],
    'admin.redis_del'         => ['file' => 'handlers/admin-handler.php', 'action' => 'delete_redis_key'],
    'admin.redis_flush'       => ['file' => 'handlers/admin-handler.php', 'action' => 'flush_redis_db'],
    
    'system.create_backup'    => ['file' => 'handlers/system-handler.php', 'action' => 'create_backup_auto'],
];