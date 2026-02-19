<?php
// includes/config/routes.php

return [
    '/' => 'home.php',
    '/explore' => 'explore.php',
    '/login' => 'login.php',
    '/register' => 'register.php',
    '/register/aditional-data' => 'register.php', // Apunta al mismo archivo
    '/register/verification-account' => 'register.php', // Apunta al mismo archivo
    '/forgot-password' => 'forgot-password.php',
    '/reset-password' => 'reset-password.php',
    
    // Rutas de configuración
    '/settings/your-account' => 'settings-account.php',
    '/settings/security' => 'settings-security.php',
    '/settings/accessibility' => 'settings-accessibility.php',
    '/settings/guest' => 'settings-guest.php'
];