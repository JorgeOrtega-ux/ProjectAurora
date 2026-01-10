<?php
// config/routes.php

return [
    // --- Contexto: APP ---
    'main'    => __DIR__ . '/../includes/sections/app/main.php',
    'trends'  => __DIR__ . '/../includes/sections/app/trends.php',
    
    // --- Contexto: HELP (Ayuda y Legales) ---
    'help'     => __DIR__ . '/../includes/sections/help/main.php',      // Landing de ayuda
    'privacy'  => __DIR__ . '/../includes/sections/help/privacy.php',   // Política de privacidad
    'terms'    => __DIR__ . '/../includes/sections/help/terms.php',     // Términos y condiciones
    'cookies'  => __DIR__ . '/../includes/sections/help/cookies.php',   // Cookies
    'feedback' => __DIR__ . '/../includes/sections/help/feedback.php',  // Enviar comentarios

    // --- Contexto: SYSTEM ---
    '404'     => __DIR__ . '/../includes/sections/system/404.php',
];
?>