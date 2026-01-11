<?php
// config/routes.php

return [
    // --- Contexto: APP ---
    'main'    => __DIR__ . '/../includes/sections/app/main.php',
    'trends'  => __DIR__ . '/../includes/sections/app/trends.php',
    
    // --- Contexto: AUTH ---
    'login'    => __DIR__ . '/../includes/sections/auth/login.php',
    
    // --- REGISTRO POR ETAPAS ---
    'register'                      => __DIR__ . '/../includes/sections/auth/register.php',
    'register/additional-data'      => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verification-account' => __DIR__ . '/../includes/sections/auth/register.php',
    
    // --- Contexto: SETTINGS ---
    'settings/preferences' => __DIR__ . '/../includes/sections/settings/preferences.php',
    'settings/profile'     => __DIR__ . '/../includes/sections/settings/profile.php',
    'settings/security'    => __DIR__ . '/../includes/sections/settings/security.php',

    // --- Contexto: HELP ---
    'help'     => __DIR__ . '/../includes/sections/help/main.php',
    'privacy'  => __DIR__ . '/../includes/sections/help/privacy.php',
    'terms'    => __DIR__ . '/../includes/sections/help/terms.php',
    'cookies'  => __DIR__ . '/../includes/sections/help/cookies.php',
    'feedback' => __DIR__ . '/../includes/sections/help/feedback.php',

    // --- Contexto: SYSTEM ---
    '404'     => __DIR__ . '/../includes/sections/system/404.php',
];
?>