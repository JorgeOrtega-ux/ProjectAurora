<?php
// config/routes.php

return [
    // === APP ===
    'main'     => __DIR__ . '/../includes/sections/app/main.php',
    'explore'  => __DIR__ . '/../includes/sections/app/explore.php',
    
    // === STUDIO ===
    'studio/layout'          => __DIR__ . '/../includes/sections/studio/layout.php',
    // [NUEVO] Rutas internas para la navegación AJAX/SPA
    'studio/parts/dashboard' => __DIR__ . '/../includes/sections/studio/dashboard.php',
    'studio/parts/content'   => __DIR__ . '/../includes/sections/studio/content.php',
    
    // === AUTH ===
    'login'                         => __DIR__ . '/../includes/sections/auth/login.php',
    'login/verification-aditional'  => __DIR__ . '/../includes/sections/auth/login.php',
    'register'                      => __DIR__ . '/../includes/sections/auth/register.php',
    'register/aditional-data'       => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verification-account' => __DIR__ . '/../includes/sections/auth/register.php',
    'recover-password'              => __DIR__ . '/../includes/sections/auth/recover-password.php',
    'reset-password'                => __DIR__ . '/../includes/sections/auth/reset-password.php',
    'account-status'                => __DIR__ . '/../includes/sections/system/status-screen.php',

    // === SETTINGS ===
    'settings/your-profile'    => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-security'  => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/accessibility'   => __DIR__ . '/../includes/sections/settings/accessibility.php',
    'settings/2fa-setup'       => __DIR__ . '/../includes/sections/settings/2fa-setup.php',
    'settings/devices'         => __DIR__ . '/../includes/sections/settings/devices.php',
    'settings/delete-account'  => __DIR__ . '/../includes/sections/settings/delete-account.php',
    'settings/preferences'     => __DIR__ . '/../includes/sections/settings/guest-preferences.php',
    
    // === SITE POLICY ===
    'site-policy'                  => __DIR__ . '/../includes/sections/help/main.php',
    'site-policy/privacy-policy'   => __DIR__ . '/../includes/sections/help/privacy-policy.php',
    'site-policy/terms-of-service' => __DIR__ . '/../includes/sections/help/terms-and-conditions.php',
    'site-policy/cookie-policy'    => __DIR__ . '/../includes/sections/help/cookie-policy.php',
    'site-policy/send-feedback'    => __DIR__ . '/../includes/sections/help/send-feedback.php',
    
    // === ADMIN PANEL ===
    'admin/dashboard'              => __DIR__ . '/../includes/sections/admin/dashboard.php',
    'admin/users'                  => __DIR__ . '/../includes/sections/admin/users.php',
    'admin/user-details'           => __DIR__ . '/../includes/sections/admin/user-details.php',
    'admin/user-role'              => __DIR__ . '/../includes/sections/admin/user-role.php',
    'admin/user-status'            => __DIR__ . '/../includes/sections/admin/user-status.php',
    'admin/backups'                => __DIR__ . '/../includes/sections/admin/backups.php',
    'admin/backups/config'         => __DIR__ . '/../includes/sections/admin/backup-config.php',
    'admin/server'                 => __DIR__ . '/../includes/sections/admin/server-config.php',
    'admin/alerts'                 => __DIR__ . '/../includes/sections/admin/system-alerts.php',
    'admin/redis'                  => __DIR__ . '/../includes/sections/admin/redis-manager.php',
    'admin/audit-log'              => __DIR__ . '/../includes/sections/admin/audit-log.php',
    'admin/log-files'              => __DIR__ . '/../includes/sections/admin/log-files.php',
    'admin/file-viewer'            => __DIR__ . '/../includes/sections/admin/file-viewer.php',

    // === SYSTEM ===
    '404'      => __DIR__ . '/../includes/sections/system/404.php',
];
?>