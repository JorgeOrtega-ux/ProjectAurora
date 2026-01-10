<?php
// config/routes.php

return [
    // --- Contexto: APP ---
    'main'    => __DIR__ . '/../includes/sections/app/main.php',
    'trends'  => __DIR__ . '/../includes/sections/app/trends.php',
    
    // --- Contexto: SETTINGS ---
    // Accesibilidad eliminada, solo queda preferencias
    'settings/preferences'   => __DIR__ . '/../includes/sections/settings/preferences.php',

    // --- Contexto: HELP (Ayuda y Legales) ---
    'help'     => __DIR__ . '/../includes/sections/help/main.php',
    'privacy'  => __DIR__ . '/../includes/sections/help/privacy.php',
    'terms'    => __DIR__ . '/../includes/sections/help/terms.php',
    'cookies'  => __DIR__ . '/../includes/sections/help/cookies.php',
    'feedback' => __DIR__ . '/../includes/sections/help/feedback.php',

    // --- Contexto: SYSTEM ---
    '404'     => __DIR__ . '/../includes/sections/system/404.php',
];
?>