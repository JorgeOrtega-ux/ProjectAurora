<?php
// api/route-map.php
return [
    'auth.check_email'  => ['file' => 'handler/auth-handler.php', 'action' => 'check_email'],
    'auth.send_code'    => ['file' => 'handler/auth-handler.php', 'action' => 'send_code'],
    'auth.register'     => ['file' => 'handler/auth-handler.php', 'action' => 'register'],
    'auth.login'        => ['file' => 'handler/auth-handler.php', 'action' => 'login'],
    'auth.verify_2fa'   => ['file' => 'handler/auth-handler.php', 'action' => 'verify_2fa'],
    'auth.logout'       => ['file' => 'handler/auth-handler.php', 'action' => 'logout'],
    'auth.check_session'=> ['file' => 'handler/auth-handler.php', 'action' => 'check_session'],
    'auth.forgot_password' => ['file' => 'handler/auth-handler.php', 'action' => 'forgot_password'],
    'auth.reset_password'  => ['file' => 'handler/auth-handler.php', 'action' => 'reset_password'],
    
    // --- RUTAS DE SETTINGS ---
    'settings.upload_avatar' => ['file' => 'handler/settings-handler.php', 'action' => 'upload_avatar'],
    'settings.delete_avatar' => ['file' => 'handler/settings-handler.php', 'action' => 'delete_avatar'],
    'settings.update_field'  => ['file' => 'handler/settings-handler.php', 'action' => 'update_field'],
    'settings.request_email_change' => ['file' => 'handler/settings-handler.php', 'action' => 'request_email_change'],
    'settings.confirm_email_change' => ['file' => 'handler/settings-handler.php', 'action' => 'confirm_email_change'],
    'settings.delete_account' => ['file' => 'handler/settings-handler.php', 'action' => 'delete_account'],
    
    // --- RUTAS DE PREFERENCIAS ---
    'settings.get_preferences'    => ['file' => 'handler/settings-handler.php', 'action' => 'get_preferences'],
    'settings.update_preference'  => ['file' => 'handler/settings-handler.php', 'action' => 'update_preference'],

    // --- RUTAS DE SEGURIDAD ---
    'settings.verify_password'    => ['file' => 'handler/settings-handler.php', 'action' => 'verify_password'],
    'settings.update_password'    => ['file' => 'handler/settings-handler.php', 'action' => 'update_password'],

    // --- RUTAS 2FA ---
    'settings.2fa_init'           => ['file' => 'handler/settings-handler.php', 'action' => '2fa_init'],
    'settings.2fa_enable'         => ['file' => 'handler/settings-handler.php', 'action' => '2fa_enable'],
    'settings.2fa_disable'        => ['file' => 'handler/settings-handler.php', 'action' => '2fa_disable'],
    'settings.2fa_regen'          => ['file' => 'handler/settings-handler.php', 'action' => '2fa_regen'],

    // --- RUTAS DE GESTIÓN DE DISPOSITIVOS (SESIONES) ---
    'settings.get_devices'        => ['file' => 'handler/settings-handler.php', 'action' => 'get_devices'],
    'settings.revoke_device'      => ['file' => 'handler/settings-handler.php', 'action' => 'revoke_device'],
    'settings.revoke_all_devices' => ['file' => 'handler/settings-handler.php', 'action' => 'revoke_all_devices'],

    // --- RUTAS DE ADMINISTRACIÓN ---
    'admin.update_avatar'         => ['file' => 'handler/admin-handler.php', 'action' => 'update_avatar'],
    'admin.delete_avatar'         => ['file' => 'handler/admin-handler.php', 'action' => 'delete_avatar'],
    'admin.update_field'          => ['file' => 'handler/admin-handler.php', 'action' => 'update_field'],
    'admin.update_preference'     => ['file' => 'handler/admin-handler.php', 'action' => 'update_preference']
];
?>