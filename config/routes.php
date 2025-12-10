<?php
// config/routes.php

// Retornamos un array asociativo.
// Usamos __DIR__ para generar rutas absolutas del sistema, evitando problemas de rutas relativas.
return [
    'main'     => __DIR__ . '/../includes/sections/app/main.php',
    'explorer' => __DIR__ . '/../includes/sections/app/explorer.php',
    '404'      => __DIR__ . '/../includes/sections/system/404.php',
    
    // Rutas de Settings
    'settings/your-profile'       => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-and-security' => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/accessibility'      => __DIR__ . '/../includes/sections/settings/accessibility.php',
    
    // Rutas de Registro / Auth
    'recover-password'        => __DIR__ . '/../includes/sections/auth/recover-password.php',
    'register'                => __DIR__ . '/../includes/sections/auth/register.php',
    'register/aditional-data' => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verify'         => __DIR__ . '/../includes/sections/auth/register.php',
];
?>