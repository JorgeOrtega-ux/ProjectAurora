<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$DEFAULT_SECTION = 'main';
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/ProjectAurora/'; // Asegúrate que coincida con tu carpeta

if (strpos($requestUri, $basePath) === 0) $requestUri = substr($requestUri, strlen($basePath));
$requestUri = strtok($requestUri, '?');
$requestUri = rtrim($requestUri, '/');

// --- API ROUTING ---
if (strpos($requestUri, 'api/') === 0) {
    $apiFilePath = __DIR__ . '/../' . $requestUri;
    if (file_exists($apiFilePath)) { require_once $apiFilePath; exit; }
    else { http_response_code(404); echo json_encode(['error'=>'API not found']); exit; }
}

// --- LISTA BLANCA DE URLS ---
$allowedSections = [
    'main', 'login', 'register', 'explorer',
    // Habilitamos las URLs para que el router las reconozca
    'register/additional-data', 
    'register/verification-account',
    // NUEVO: Ruta de recuperación
    'forgot-password'
];

$CURRENT_SECTION = empty($requestUri) ? 'main' : $requestUri;
if (!in_array($CURRENT_SECTION, $allowedSections)) $CURRENT_SECTION = '404';
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri)) $CURRENT_SECTION = 'main';

// --- MAPEO INTELIGENTE DE ARCHIVOS ---
// Si la URL empieza por "register/", forzamos que cargue SIEMPRE "register.php"
if (strpos($CURRENT_SECTION, 'register/') === 0 || $CURRENT_SECTION === 'register') {
    $SECTION_FILE_NAME = 'register'; 
} else {
    $SECTION_FILE_NAME = str_replace('/', '-', $CURRENT_SECTION);
}

// ==========================================
// EL GUARDIA (Seguridad de Pasos)
// ==========================================
$isLoggedIn = isset($_SESSION['user_id']);
// NUEVO: Añadido 'forgot-password' para que sea accesible sin login
$publicSections = ['login', 'register', 'register/additional-data', 'register/verification-account', 'forgot-password'];

if (!$isLoggedIn && !in_array($CURRENT_SECTION, $publicSections) && $CURRENT_SECTION !== '404') {
    header("Location: " . $basePath . "login"); exit;
}
if ($isLoggedIn && in_array($CURRENT_SECTION, $publicSections)) {
    header("Location: " . $basePath); exit;
}

// Definir REQUISITOS para entrar a cada URL
$requirements = [
    'register/additional-data'      => ['key' => 'temp_register', 'sub' => 'email'],
    'register/verification-account' => ['key' => 'temp_register', 'sub' => 'username']
];

// Verificar requisitos
if (array_key_exists($CURRENT_SECTION, $requirements)) {
    $req = $requirements[$CURRENT_SECTION];
    $hasData = isset($_SESSION[$req['key']][$req['sub']]) && !empty($_SESSION[$req['key']][$req['sub']]);

    if (!$hasData) {
        // ¡BLOQUEO! Mostramos la pantalla de error
        $SECTION_FILE_NAME = 'error-missing-data';
        $missingDataMessage = "No has completado el paso anterior para acceder a <strong>$CURRENT_SECTION</strong>.";
        $redirectTarget = 'register';
    }
}

$showNavigation = !($SECTION_FILE_NAME === 'error-missing-data' || $CURRENT_SECTION === '404') && !in_array($CURRENT_SECTION, $publicSections);
?>