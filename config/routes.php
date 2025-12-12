<?php
// config/routes.php

return [
    // --- Rutas Principales ---
    'main'     => __DIR__ . '/../includes/sections/app/main.php',
    'explorer' => __DIR__ . '/../includes/sections/app/explorer.php',
    '404'      => __DIR__ . '/../includes/sections/system/404.php',
    'maintenance' => __DIR__ . '/../includes/sections/system/maintenance.php',
    
    // --- Rutas de Autenticación ---
    'login'                  => __DIR__ . '/../includes/sections/auth/login.php',
    'recover-password'       => __DIR__ . '/../includes/sections/auth/recover-password.php',
    'recover-password-reset' => __DIR__ . '/../includes/sections/auth/recover-password-reset.php',
    
    'register'                => __DIR__ . '/../includes/sections/auth/register.php',
    'register/aditional-data' => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verify'         => __DIR__ . '/../includes/sections/auth/register.php',
    
    '2fa-challenge'      => __DIR__ . '/../includes/sections/auth/2fa-challenge.php',
    'account-status'     => __DIR__ . '/../includes/sections/auth/account-status.php',

    // --- Rutas de Configuración ---
    'settings/your-profile'       => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-and-security' => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/devices'            => __DIR__ . '/../includes/sections/settings/devices.php',
    'settings/accessibility'      => __DIR__ . '/../includes/sections/settings/accessibility.php',
    'settings/2fa-setup'          => __DIR__ . '/../includes/sections/settings/2fa-setup.php',
    'settings/delete-account'     => __DIR__ . '/../includes/sections/settings/delete-account.php',

    // --- Rutas de Administración ---
    'admin/dashboard' => __DIR__ . '/../includes/sections/admin/dashboard.php', // NUEVA RUTA
    'admin/users'     => __DIR__ . '/../includes/sections/admin/users.php',
    'admin/server'    => __DIR__ . '/../includes/sections/admin/server.php',
];
?>