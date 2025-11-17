<?php

// Define la sección por defecto
$DEFAULT_SECTION = 'main';

// Obtiene la URI de la solicitud
$requestUri = $_SERVER['REQUEST_URI'];

// Define el path base de tu proyecto
$basePath = '/ProjectAurora/';

// Remueve el path base de la URI
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Remueve query strings (ej. ?foo=bar)
$requestUri = strtok($requestUri, '?');

// Limpia slashes al final
$requestUri = rtrim($requestUri, '/');

// Secciones permitidas
$allowedSections = ['main', 'login', 'register'];

// Determina la sección actual
$CURRENT_SECTION = $DEFAULT_SECTION; // Por defecto es 'main'

if (empty($requestUri)) {
    $CURRENT_SECTION = 'main';
} elseif (in_array($requestUri, $allowedSections)) {
    $CURRENT_SECTION = $requestUri;
} else {
    // --- MODIFICACIÓN 1 ---
    // Si la URL no está en las permitidas, es '404'
    $CURRENT_SECTION = '404';
}

// Para que la URL /ProjectAurora/ se trate como la sección 'main'
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri)) {
     // Si la URL es algo como /ProjectAurora/otracosa, la forzamos a 'main'.
     // (Nota: Con la modificación de arriba, esto solo se ejecutaría si '404' falla,
     // pero lo dejamos para consistencia con tu archivo original.)
     $CURRENT_SECTION = 'main';
}


// --- INICIO DE LA MODIFICACIÓN ---
// Define la variable de navegación 
// --- MODIFICACIÓN 2 ---
// Será 'true' para 'main' y 'false' para 'login', 'register' y '404'
$showNavigation = !in_array($CURRENT_SECTION, ['login', 'register', '404']);
// --- FIN DE LA MODIFICACIÓN ---


?>