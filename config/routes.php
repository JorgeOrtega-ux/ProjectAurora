<?php
// config/routes.php

return [
    'main'     => __DIR__ . '/../includes/sections/app/main.php',
    'explorer' => __DIR__ . '/../includes/sections/app/explorer.php',
    '404'      => __DIR__ . '/../includes/sections/system/404.php',
    
    // Rutas de Settings
    'settings/your-profile'       => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-and-security' => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/accessibility'      => __DIR__ . '/../includes/sections/settings/accessibility.php',
    
    // NUEVA RUTA: Configuración de 2FA
    'settings/2fa-setup'          => __DIR__ . '/../includes/sections/settings/2fa-setup.php',
    
    // Rutas de Registro / Auth
    'recover-password'        => __DIR__ . '/../includes/sections/auth/recover-password.php',
    'register'                => __DIR__ . '/../includes/sections/auth/register.php',
    'register/aditional-data' => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verify'         => __DIR__ . '/../includes/sections/auth/register.php',
    
    // NUEVA RUTA: Pantalla de desafío 2FA al hacer login
    'auth/2fa-challenge'      => __DIR__ . '/../includes/sections/auth/2fa-challenge.php',
];
?>