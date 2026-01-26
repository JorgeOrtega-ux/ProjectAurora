<?php
// api/route-map.php

return [
    // === AUTH ===
    'auth.register_step_1'    => ['file' => 'auth-handler.php', 'action' => 'register_step_1'],
    'auth.initiate_verify'    => ['file' => 'auth-handler.php', 'action' => 'initiate_verification'],
    'auth.complete_register'  => ['file' => 'auth-handler.php', 'action' => 'complete_register'],
    'auth.resend_code'        => ['file' => 'auth-handler.php', 'action' => 'resend_code'],
    'auth.login'              => ['file' => 'auth-handler.php', 'action' => 'login'],
    'auth.verify_2fa'         => ['file' => 'auth-handler.php', 'action' => 'verify_2fa_login'],
    'auth.request_reset'      => ['file' => 'auth-handler.php', 'action' => 'request_reset'],
    'auth.reset_password'     => ['file' => 'auth-handler.php', 'action' => 'reset_password'],
    'auth.logout'             => ['file' => 'auth-handler.php', 'action' => 'logout'],
    'auth.get_ws_token'       => ['file' => 'auth-handler.php', 'action' => 'get_ws_token'],

    // === SETTINGS ===
    'settings.update_pref'    => ['file' => 'settings-handler.php', 'action' => 'update_preference'],
    'settings.upload_avatar'  => ['file' => 'settings-handler.php', 'action' => 'upload_avatar'],
    'settings.delete_avatar'  => ['file' => 'settings-handler.php', 'action' => 'delete_avatar'],
    'settings.email_status'   => ['file' => 'settings-handler.php', 'action' => 'get_email_edit_status'],
    'settings.req_email_code' => ['file' => 'settings-handler.php', 'action' => 'request_email_change_verification'],
    'settings.ver_email_code' => ['file' => 'settings-handler.php', 'action' => 'verify_email_change_code'],
    'settings.update_profile' => ['file' => 'settings-handler.php', 'action' => 'update_profile'],
    'settings.get_sessions'   => ['file' => 'settings-handler.php', 'action' => 'get_sessions'],
    'settings.revoke_session' => ['file' => 'settings-handler.php', 'action' => 'revoke_session'],
    'settings.revoke_all'     => ['file' => 'settings-handler.php', 'action' => 'revoke_all_sessions'],
    'settings.delete_acct'    => ['file' => 'settings-handler.php', 'action' => 'delete_account'],
    'settings.init_2fa'       => ['file' => 'settings-handler.php', 'action' => 'init_2fa'],
    'settings.enable_2fa'     => ['file' => 'settings-handler.php', 'action' => 'enable_2fa'],
    'settings.disable_2fa'    => ['file' => 'settings-handler.php', 'action' => 'disable_2fa'],
    'settings.get_rec_status' => ['file' => 'settings-handler.php', 'action' => 'get_recovery_status'],
    'settings.regen_codes'    => ['file' => 'settings-handler.php', 'action' => 'regenerate_recovery_codes'],
    'settings.val_password'   => ['file' => 'settings-handler.php', 'action' => 'validate_current_password'],
    'settings.change_pass'    => ['file' => 'settings-handler.php', 'action' => 'change_password'],

    // === ADMIN ===
    'admin.get_users'         => ['file' => 'admin-handler.php', 'action' => 'get_all_users'],
    'admin.get_details'       => ['file' => 'admin-handler.php', 'action' => 'get_user_details'],
    'admin.update_profile'    => ['file' => 'admin-handler.php', 'action' => 'update_user_profile'],
    'admin.update_role'       => ['file' => 'admin-handler.php', 'action' => 'update_user_role'],
    'admin.update_status'     => ['file' => 'admin-handler.php', 'action' => 'update_user_status'],
    'admin.update_pref'       => ['file' => 'admin-handler.php', 'action' => 'update_user_preference'],
    'admin.upload_avatar'     => ['file' => 'admin-handler.php', 'action' => 'upload_user_avatar'],
    'admin.delete_avatar'     => ['file' => 'admin-handler.php', 'action' => 'delete_user_avatar'],
    'admin.get_server_config' => ['file' => 'admin-handler.php', 'action' => 'get_server_config'],
    'admin.update_server_config' => ['file' => 'admin-handler.php', 'action' => 'update_server_config'],
    
    // BACKUPS ADMIN
    'admin.get_backups'       => ['file' => 'admin-handler.php', 'action' => 'get_backups'],
    'admin.create_backup'     => ['file' => 'admin-handler.php', 'action' => 'create_backup'],
    'admin.restore_backup'    => ['file' => 'admin-handler.php', 'action' => 'restore_backup'],
    'admin.delete_backup'     => ['file' => 'admin-handler.php', 'action' => 'delete_backup'],
    'admin.get_backup_config' => ['file' => 'admin-handler.php', 'action' => 'get_backup_config'],
    'admin.upd_backup_config' => ['file' => 'admin-handler.php', 'action' => 'update_backup_config'],

    // [NUEVO] AUDITORÍA
    'admin.get_audit_logs'    => ['file' => 'admin-handler.php', 'action' => 'get_audit_logs'],

    // SYSTEM (PYTHON WORKER)
    'system.create_backup'    => ['file' => 'system-handler.php', 'action' => 'create_backup_auto'],
];
?>