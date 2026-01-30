<?php
// config/security.php

return [
    // Rutas estrictamente de autenticación 
    'auth_routes' => [
        'login', 
        'login/verification-aditional', // [NUEVO] Permitir acceso público (validado por sesión)
        'register', 
        'register/aditional-data', 
        'register/verification-account', 
        'recover-password', 
        'reset-password'
    ],

    // Rutas que REQUIEREN estar logueado (Blacklist / Protección explícita)
    'protected_routes' => [
        'settings/your-profile',
        'settings/login-security',
        'settings/devices',
        'settings/delete-account',
        'settings/2fa-setup',
        'settings/accessibility', 
        'logout'
    ]
];
?>