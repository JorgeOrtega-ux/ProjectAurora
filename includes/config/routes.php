<?php
// includes/config/routes.php

return [
    // --- RUTAS PÚBLICAS ---
    '/' => ['view' => 'home.php', 'access' => 'public', 'layout' => 'main'],
    '/explore' => ['view' => 'explore.php', 'access' => 'public', 'layout' => 'main'],

    // --- RUTAS GUEST ---
    '/login' => ['view' => 'login.php', 'access' => 'guest', 'layout' => 'auth'],
    '/login/verification-2fa' => ['view' => 'login.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register' => ['view' => 'register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register/aditional-data' => ['view' => 'register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register/verification-account' => ['view' => 'register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/forgot-password' => ['view' => 'forgot-password.php', 'access' => 'guest', 'layout' => 'auth'],
    '/reset-password' => ['view' => 'reset-password.php', 'access' => 'guest', 'layout' => 'auth'],

    // --- RUTAS AUTH ---
    '/settings/your-account' => ['view' => 'settings-account.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/security' => ['view' => 'settings-security.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/accessibility' => ['view' => 'settings-accessibility.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/2fa-setup' => ['view' => 'settings-2fa.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/devices' => ['view' => 'settings-devices.php', 'access' => 'auth', 'layout' => 'main'],

    // Ruta especial
    '/settings/guest' => ['view' => 'settings-guest.php', 'access' => 'guest', 'layout' => 'main'],

    // --- RUTAS ADMIN ---
    '/admin/dashboard' => ['view' => 'admin-dashboard.php', 'access' => 'admin', 'layout' => 'main'],
    '/admin/users' => ['view' => 'admin-users.php', 'access' => 'admin', 'layout' => 'main'],
    '/admin/backups' => ['view' => 'admin-backups.php', 'access' => 'admin', 'layout' => 'main'],
    '/admin/server' => ['view' => 'admin-server.php', 'access' => 'admin', 'layout' => 'main']
];