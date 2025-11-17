<?php
// --- INICIO DE SESIÓN OBLIGATORIO ---
// Iniciamos la sesión aquí para poder leer $_SESSION en todo el sitio
session_start();

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

// Remueve query strings
$requestUri = strtok($requestUri, '?');
// Limpia slashes al final
$requestUri = rtrim($requestUri, '/');

// --- API ROUTING (Del paso anterior) ---
if (strpos($requestUri, 'api/') === 0) {
    $apiFilePath = __DIR__ . '/../' . $requestUri;
    if (file_exists($apiFilePath)) {
        require_once $apiFilePath;
        exit; 
    } else {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'API endpoint not found']);
        exit;
    }
}

// Secciones permitidas
$allowedSections = ['main', 'login', 'register'];

// Determina la sección actual
$CURRENT_SECTION = $DEFAULT_SECTION; 

if (empty($requestUri)) {
    $CURRENT_SECTION = 'main';
} elseif (in_array($requestUri, $allowedSections)) {
    $CURRENT_SECTION = $requestUri;
} else {
    $CURRENT_SECTION = '404';
}

if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri) && $requestUri !== '404') {
     $CURRENT_SECTION = 'main';
}

// --- LÓGICA DE PROTECCIÓN (EL GUARDIA) ---

$isLoggedIn = isset($_SESSION['user_id']);
$publicSections = ['login', 'register'];

// CASO 1: Usuario NO logueado intenta entrar a una zona privada (como 'main')
if (!$isLoggedIn && !in_array($CURRENT_SECTION, $publicSections)) {
    // Lo forzamos a ir al login
    header("Location: " . $basePath . "login");
    exit;
}

// CASO 2: Usuario YA logueado intenta entrar a 'login' o 'register'
if ($isLoggedIn && in_array($CURRENT_SECTION, $publicSections)) {
    // Lo mandamos directo al main (ya estás dentro, no necesitas loguearte)
    header("Location: " . $basePath); 
    exit;
}

// --- FIN LÓGICA PROTECCIÓN ---

$showNavigation = !in_array($CURRENT_SECTION, ['login', 'register', '404']);

?>