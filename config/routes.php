<?php
// config/routes.php

return [
    // --- Rutas Principales ---
    'main'     => __DIR__ . '/../includes/sections/app/main.php',
    'explorer' => __DIR__ . '/../includes/sections/app/explorer.php',
    '404'      => __DIR__ . '/../includes/sections/system/404.php',
    
    // --- Rutas de Autenticación (CORREGIDAS) ---
    // Se agregó 'login' y 'recover-password-reset' que faltaban
    'login'                  => __DIR__ . '/../includes/sections/auth/login.php',
    'recover-password'       => __DIR__ . '/../includes/sections/auth/recover-password.php',
    'recover-password-reset' => __DIR__ . '/../includes/sections/auth/recover-password-reset.php',
    
    'register'                => __DIR__ . '/../includes/sections/auth/register.php',
    'register/aditional-data' => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verify'         => __DIR__ . '/../includes/sections/auth/register.php',
    
    // Pantalla de desafío 2FA (CORREGIDO: llave simplificada)
    '2fa-challenge'      => __DIR__ . '/../includes/sections/auth/2fa-challenge.php',

    // --- Rutas de Configuración (Settings) ---
    'settings/your-profile'       => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-and-security' => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/accessibility'      => __DIR__ . '/../includes/sections/settings/accessibility.php',
    'settings/2fa-setup'          => __DIR__ . '/../includes/sections/settings/2fa-setup.php',
];
?>