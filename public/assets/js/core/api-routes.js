/**
 * public/assets/js/core/api-routes.js
 */

export const ApiRoutes = {
    // ... (Auth y Settings sin cambios) ...
    Auth: {
        RegisterStep1:    { route: 'auth.register_step_1' },
        RegisterStep2:    { route: 'auth.initiate_verify' },
        RegisterComplete: { route: 'auth.complete_register' },
        ResendCode:       { route: 'auth.resend_code' },
        Login:            { route: 'auth.login' },
        Verify2FA:        { route: 'auth.verify_2fa' },
        RequestReset:     { route: 'auth.request_reset' },
        ResetPassword:    { route: 'auth.reset_password' },
        Logout:           { route: 'auth.logout' },
        GetStatus:        { route: 'auth.get_status' },
        GetWsToken:       { route: 'auth.get_ws_token' }
    },
    
    Settings: {
        UpdatePreference:         { route: 'settings.update_pref' },
        UploadAvatar:             { route: 'settings.upload_avatar' },
        DeleteAvatar:             { route: 'settings.delete_avatar' },
        GetEmailStatus:           { route: 'settings.email_status' },
        RequestEmailVerification: { route: 'settings.req_email_code' },
        VerifyEmailCode:          { route: 'settings.ver_email_code' },
        UpdateProfile:            { route: 'settings.update_profile' },
        GetSessions:              { route: 'settings.get_sessions' },
        RevokeSession:            { route: 'settings.revoke_session' },
        RevokeAllSessions:        { route: 'settings.revoke_all' },
        DeleteAccount:            { route: 'settings.delete_acct' },
        Init2FA:                  { route: 'settings.init_2fa' },
        Enable2FA:                { route: 'settings.enable_2fa' },
        Disable2FA:               { route: 'settings.disable_2fa' },
        GetRecoveryStatus:        { route: 'settings.get_rec_status' },
        RegenerateRecoveryCodes:  { route: 'settings.regen_codes' },
        ValidatePassword:         { route: 'settings.val_password' },
        ChangePassword:           { route: 'settings.change_pass' }
    },

    Admin: {
        // [NUEVO]
        GetDashboardStats:  { route: 'admin.dashboard_stats' },

        GetUsers:           { route: 'admin.get_users' },
        GetDetails:         { route: 'admin.get_details' },
        UpdateProfile:      { route: 'admin.update_profile' },
        UpdateRole:         { route: 'admin.update_role' },
        UpdateStatus:       { route: 'admin.update_status' },
        UpdatePreference:   { route: 'admin.update_pref' },
        UploadAvatar:       { route: 'admin.upload_avatar' },
        DeleteAvatar:       { route: 'admin.delete_avatar' },
        Disable2FA:         { route: 'admin.disable_2fa' }, 
        GetServerConfig:    { route: 'admin.get_server_config' },
        UpdateServerConfig: { route: 'admin.update_server_config' },
        
        Backups: {
            Get:          { route: 'admin.get_backups' },
            Create:       { route: 'admin.create_backup' },
            Restore:      { route: 'admin.restore_backup' },
            Delete:       { route: 'admin.delete_backup' },
            GetContent:   { route: 'admin.get_backup_content' },
            GetConfig:    { route: 'admin.get_backup_config' },
            UpdateConfig: { route: 'admin.upd_backup_config' }
        },

        // Auditoría
        GetAuditLogs:     { route: 'admin.get_audit_logs' },

        // Logs de Archivo
        GetLogFiles:      { route: 'admin.get_log_files' },
        DeleteLogFiles:   { route: 'admin.delete_log_files' },
        
        // Visor
        GetFileContent:   { route: 'admin.get_log_content' },

        // REDIS MANAGER
        Redis: {
            GetStats:     { route: 'admin.redis_stats' },
            GetKeys:      { route: 'admin.redis_keys' },
            GetValue:     { route: 'admin.redis_get' },
            DeleteKey:    { route: 'admin.redis_del' },
            FlushDB:      { route: 'admin.redis_flush' }
        }
    }
};