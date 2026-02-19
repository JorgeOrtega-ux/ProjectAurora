<?php
// includes/config/routes.php

return [
    // --- RUTAS PÚBLICAS ---
    '/' => ['view' => 'home.php', 'access' => 'public', 'layout' => 'main'],
    '/explore' => ['view' => 'explore.php', 'access' => 'public', 'layout' => 'main'],

    // --- RUTAS GUEST (Solo para usuarios no logueados) ---
    // layout = 'auth' hará que no se renderice el header ni el panel lateral
    '/login' => ['view' => 'login.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register' => ['view' => 'register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register/aditional-data' => ['view' => 'register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/register/verification-account' => ['view' => 'register.php', 'access' => 'guest', 'layout' => 'auth'],
    '/forgot-password' => ['view' => 'forgot-password.php', 'access' => 'guest', 'layout' => 'auth'],
    '/reset-password' => ['view' => 'reset-password.php', 'access' => 'guest', 'layout' => 'auth'],

    // --- RUTAS AUTH (Solo para usuarios logueados) ---
    '/settings/your-account' => ['view' => 'settings-account.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/security' => ['view' => 'settings-security.php', 'access' => 'auth', 'layout' => 'main'],
    '/settings/accessibility' => ['view' => 'settings-accessibility.php', 'access' => 'auth', 'layout' => 'main'],

    // Ruta especial de configuración para no logueados
    '/settings/guest' => ['view' => 'settings-guest.php', 'access' => 'guest', 'layout' => 'main']
];
