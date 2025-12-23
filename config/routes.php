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
    
    // === NUEVAS RUTAS ===
    'recover-password' => __DIR__ . '/../includes/sections/recover-password.php', // Paso 1: Poner Email
    'reset-password'   => __DIR__ . '/../includes/sections/reset-password.php',   // Paso 2: Poner nueva clave
    
    '404'      => __DIR__ . '/../includes/sections/404.php',
];
?>