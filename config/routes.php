<?php
// config/routes.php

return [
    'main'     => __DIR__ . '/../includes/sections/main.php',
    'explore'  => __DIR__ . '/../includes/sections/explore.php',
    'login'    => __DIR__ . '/../includes/sections/login.php',
    
    // Rutas del flujo de registro (Todas cargan la misma vista base)
    'register'                      => __DIR__ . '/../includes/sections/register.php',
    'register/aditional-data'       => __DIR__ . '/../includes/sections/register.php',
    'register/verification-account' => __DIR__ . '/../includes/sections/register.php',
    
    '404'      => __DIR__ . '/../includes/sections/404.php',
];
?>