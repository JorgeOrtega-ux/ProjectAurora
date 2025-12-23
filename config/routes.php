<?php
// config/routes.php

return [
    'main'     => __DIR__ . '/../includes/sections/main.php',
    'explore'  => __DIR__ . '/../includes/sections/explore.php',
    'login'    => __DIR__ . '/../includes/sections/login.php',
    
    // Rutas de registro
    'register'                      => __DIR__ . '/../includes/sections/register.php',
    'register/aditional-data'       => __DIR__ . '/../includes/sections/register.php',
    'register/verification-account' => __DIR__ . '/../includes/sections/register.php',
    
    // Recuperación
    'recover-password' => __DIR__ . '/../includes/sections/recover-password.php',
    'reset-password'   => __DIR__ . '/../includes/sections/reset-password.php',
    
    // === NUEVAS RUTAS DE SETTINGS ===
    // Asegúrate de crear la carpeta includes/sections/settings/
    'settings/your-profile'   => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-security' => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/accessibility'  => __DIR__ . '/../includes/sections/settings/accessibility.php',
    
    '404'      => __DIR__ . '/../includes/sections/404.php',
];
?>