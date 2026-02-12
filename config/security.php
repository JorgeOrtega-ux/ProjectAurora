<?php
// config/security.php

return [
    // Definición de Roles y Permisos
    'roles' => [
        // Roles que pueden ignorar el modo mantenimiento
        'maintenance_bypass' => ['founder', 'administrator', 'moderator'],
        
        // Roles que tienen acceso al panel de administración
        'admin_access' => ['founder', 'administrator']
    ],

    // Rutas estrictamente de autenticación 
    'auth_routes' => [
        'login', 
        'login/verification-aditional',
        'register', 
        'register/aditional-data', 
        'register/verification-account', 
        'recover-password', 
        'reset-password'
    ],

    // Rutas que REQUIEREN estar logueado
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