<?php
// includes/config/routes.php

return [
    '/' => 'home.php',
    '/explore' => 'explore.php',
    '/login' => 'login.php',
    '/register' => 'register.php',
    '/forgot-password' => 'forgot-password.php', // NUEVA RUTA
    '/reset-password' => 'reset-password.php',   // NUEVA RUTA
    
    // Rutas de configuración
    '/settings/your-account' => 'settings-account.php',
    '/settings/security' => 'settings-security.php',
    '/settings/accessibility' => 'settings-accessibility.php',
    '/settings/guest' => 'settings-guest.php'
];