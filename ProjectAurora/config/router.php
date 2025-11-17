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
    // Si la URL no es 'main', 'login', o 'register',
    // puedes redirigir a 'main' o mostrar un 404.
    // Por ahora, la dejamos como 'main'.
    $CURRENT_SECTION = 'main';
    
    // Si quisieras que /ProjectAurora/ y /ProjectAurora/main fueran lo mismo,
    // pero cualquier otra cosa fuera un 404 (o 'main'),
    // la lógica de arriba ya lo maneja.
}

// Para que la URL /ProjectAurora/ se trate como la sección 'main'
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri)) {
     // Si la URL es algo como /ProjectAurora/otracosa, la forzamos a 'main'.
     // Si quieres un 404, cambia esto.
     $CURRENT_SECTION = 'main';
}


// --- INICIO DE LA MODIFICACIÓN ---
// Define la variable de navegación 
// Será 'true' para 'main' y 'false' para 'login' o 'register'
$showNavigation = ($CURRENT_SECTION !== 'login' && $CURRENT_SECTION !== 'register');
// --- FIN DE LA MODIFICACIÓN ---


?>