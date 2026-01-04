<?php
// config/routes.php

return [
    // === APP (General) ===
    'main'     => __DIR__ . '/../includes/sections/app/main.php',
    'explore'  => __DIR__ . '/../includes/sections/app/explore.php',
    
    // Ruta dinámica para pizarrones (El router deberá manejar los parámetros adicionales)
    'whiteboard' => __DIR__ . '/../includes/sections/app/whiteboard.php',
    
    // === AUTH (Autenticación) ===
    'login'    => __DIR__ . '/../includes/sections/auth/login.php',
    
    // Rutas de registro
    'register'                      => __DIR__ . '/../includes/sections/auth/register.php',
    'register/aditional-data'       => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verification-account' => __DIR__ . '/../includes/sections/auth/register.php',
    
    // Recuperación
    'recover-password' => __DIR__ . '/../includes/sections/auth/recover-password.php',
    'reset-password'   => __DIR__ . '/../includes/sections/auth/reset-password.php',
    
    // === SETTINGS (Configuración) ===
    'settings/your-profile'    => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-security'  => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/accessibility'   => __DIR__ . '/../includes/sections/settings/accessibility.php',
    'settings/2fa-setup'       => __DIR__ . '/../includes/sections/settings/2fa-setup.php',
    'settings/devices'         => __DIR__ . '/../includes/sections/settings/devices.php',
    'settings/delete-account'  => __DIR__ . '/../includes/sections/settings/delete-account.php',
    
    // === SYSTEM (Errores y Sistema) ===
    '404'      => __DIR__ . '/../includes/sections/system/404.php',
];
?>