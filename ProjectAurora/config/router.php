<?php
// --- INICIO DE SESIÓN OBLIGATORIO ---
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

// --- API ROUTING ---
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

// --- SECCIONES PERMITIDAS (RUTAS) ---
$allowedSections = [
    'main', 
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'explorer'
];

// Determina la sección actual (URL)
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

// --- LÓGICA DE MAPEO DE ARCHIVOS ---
// Convierte 'register/additional-data' en 'register-additional-data' para buscar el archivo .php
$SECTION_FILE_NAME = str_replace('/', '-', $CURRENT_SECTION);


// --- LÓGICA DE PROTECCIÓN (EL GUARDIA) ---

$isLoggedIn = isset($_SESSION['user_id']);

// Secciones públicas donde NO se requiere login
$publicSections = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account'
];

// CASO 1: Usuario NO logueado intenta entrar a una zona privada
if (!$isLoggedIn && !in_array($CURRENT_SECTION, $publicSections)) {
    header("Location: " . $basePath . "login");
    exit;
}

// CASO 2: Usuario YA logueado intenta entrar a zonas de auth
if ($isLoggedIn && in_array($CURRENT_SECTION, $publicSections)) {
    header("Location: " . $basePath); 
    exit;
}

// --- FIN LÓGICA PROTECCIÓN ---

// Mostrar navegación solo si no estamos en login/register/404
$showNavigation = !in_array($CURRENT_SECTION, array_merge($publicSections, ['404']));

?>