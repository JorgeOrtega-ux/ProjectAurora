<?php
// includes/config/routes.php

return [
    // --- RUTAS PÚBLICAS ---
    '/' => ['view' => 'app/home.php', 'access' => 'public', 'layout' => 'main'],
    '/explore' => ['view' => 'app/explore.php', 'access' => 'public', 'layout' => 'main'],

    // --- RUTAS GUEST ---
    '/login' => ['view' => 'auth/login.php', 'access' => 'guest', 'layout' => 'auth'],
    '/login/verification-2fa' => ['view' => 'auth/login.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register' => ['view' => 'auth/register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register/aditional-data' => ['view' => 'auth/register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register/verification-account' => ['view' => 'auth/register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/forgot-password' => ['view' => 'auth/forgot-password.php', 'access' => 'guest', 'layout' => 'auth'],
    '/reset-password' => ['view' => 'auth/reset-password.php', 'access' => 'guest', 'layout' => 'auth'],

    // --- RUTAS AUTH ---
    '/settings/your-account' => ['view' => 'settings/settings-account.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/security' => ['view' => 'settings/settings-security.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/accessibility' => ['view' => 'settings/settings-accessibility.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/2fa-setup' => ['view' => 'settings/settings-2fa.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/devices' => ['view' => 'settings/settings-devices.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/delete-account' => ['view' => 'settings/settings-delete-account.php', 'access' => 'auth', 'layout' => 'main'],

    // Ruta especial
    '/settings/guest' => ['view' => 'settings/settings-guest.php', 'access' => 'guest', 'layout' => 'main'],

    // --- RUTAS ADMIN ---
    '/admin/dashboard' => ['view' => 'admin/admin-dashboard.php', 'access' => 'admin', 'layout' => 'main'],
    '/admin/users' => ['view' => 'admin/admin-users.php', 'access' => 'admin', 'layout' => 'main'],
    '/admin/users/manage-account' => ['view' => 'admin/admin-manage-user.php', 'access' => 'admin', 'layout' => 'main'],
    '/admin/backups' => ['view' => 'admin/admin-backups.php', 'access' => 'admin', 'layout' => 'main'],
    '/admin/server' => ['view' => 'admin/admin-server.php', 'access' => 'admin', 'layout' => 'main']
];