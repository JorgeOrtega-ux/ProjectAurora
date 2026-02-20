<?php
// api/route-map.php
return [
    'auth.check_email'  => ['file' => 'handler/auth-handler.php', 'action' => 'check_email'],
    'auth.send_code'    => ['file' => 'handler/auth-handler.php', 'action' => 'send_code'],
    'auth.register'     => ['file' => 'handler/auth-handler.php', 'action' => 'register'],
    'auth.login'        => ['file' => 'handler/auth-handler.php', 'action' => 'login'],
    'auth.logout'       => ['file' => 'handler/auth-handler.php', 'action' => 'logout'],
    'auth.check_session'=> ['file' => 'handler/auth-handler.php', 'action' => 'check_session'],
    'auth.forgot_password' => ['file' => 'handler/auth-handler.php', 'action' => 'forgot_password'],
    'auth.reset_password'  => ['file' => 'handler/auth-handler.php', 'action' => 'reset_password'],
    
    // --- RUTAS DE SETTINGS ---
    'settings.upload_avatar' => ['file' => 'handler/settings-handler.php', 'action' => 'upload_avatar'],
    'settings.delete_avatar' => ['file' => 'handler/settings-handler.php', 'action' => 'delete_avatar'],
    
    // NUEVO: Ruta genérica para modificar textos
    'settings.update_field'  => ['file' => 'handler/settings-handler.php', 'action' => 'update_field']
];
?>