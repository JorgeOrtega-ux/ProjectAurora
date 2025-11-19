<?php
// config/router.php

// [SEGURIDAD] Configuración de Cookies de Sesión
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 30; 
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_httponly', 1);
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/database.php';

$DEFAULT_SECTION = 'main';
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/ProjectAurora/'; 

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
    'register/additional-data', 
    'register/verification-account',
    'forgot-password',
    'status-page',
    'login/verification-additional',
    // [NUEVO] Secciones de Settings
    'settings',
    'settings/your-profile',
    'settings/login-security',
    'settings/accessibility'
];

$CURRENT_SECTION = empty($requestUri) ? 'main' : $requestUri;
if (!in_array($CURRENT_SECTION, $allowedSections)) $CURRENT_SECTION = '404';
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri)) $CURRENT_SECTION = 'main';

// [NUEVO] Redirección automática de /settings a /settings/your-profile
if ($CURRENT_SECTION === 'settings') {
    header("Location: " . $basePath . "settings/your-profile");
    exit;
}

// ==========================================
// VERIFICACIÓN DE ESTADO
// ==========================================
$isLoggedIn = isset($_SESSION['user_id']);

if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT account_status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();

        if ($status && $status !== 'active') {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: " . $basePath . "status-page?status=" . $status);
            exit;
        }
    } catch (Exception $e) {}
}

// --- MAPEO DE ARCHIVOS ---
$appSections = ['main', 'explorer'];
$systemSections = ['status-page', '404', 'error-missing-data'];

// Casos especiales de Autenticación
if ($CURRENT_SECTION === 'login/verification-additional') {
    $SECTION_FILE_NAME = 'auth/login';
} elseif (strpos($CURRENT_SECTION, 'register/') === 0 || $CURRENT_SECTION === 'register') {
    $SECTION_FILE_NAME = 'auth/register'; 
} elseif ($CURRENT_SECTION === 'forgot-password') {
    $SECTION_FILE_NAME = 'auth/forgot-password';
} elseif ($CURRENT_SECTION === 'login') {
    $SECTION_FILE_NAME = 'auth/login';

// [NUEVO] Casos de Settings
} elseif (strpos($CURRENT_SECTION, 'settings/') === 0) {
    // Mapea directamente 'settings/your-profile' a 'settings/your-profile.php'
    // Aseguramos que el archivo exista en la estructura
    $SECTION_FILE_NAME = $CURRENT_SECTION; 

// Casos de App (sesión iniciada)
} elseif (in_array($CURRENT_SECTION, $appSections)) {
    $SECTION_FILE_NAME = 'app/' . $CURRENT_SECTION;

// Casos de Sistema (errores, estado)
} elseif (in_array($CURRENT_SECTION, $systemSections)) {
    $SECTION_FILE_NAME = 'system/' . $CURRENT_SECTION;

} else {
    $SECTION_FILE_NAME = 'system/404';
}

// ==========================================
// SEGURIDAD DE ACCESO
// ==========================================
$publicSections = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'forgot-password', 
    'status-page',
    'login/verification-additional'
];

if (!$isLoggedIn && !in_array($CURRENT_SECTION, $publicSections) && $CURRENT_SECTION !== '404') {
    header("Location: " . $basePath . "login"); exit;
}
if ($isLoggedIn && in_array($CURRENT_SECTION, $publicSections)) {
    header("Location: " . $basePath); exit;
}

// Requisitos previos
$requirements = [
    'register/additional-data'      => ['key' => 'temp_register', 'sub' => 'email'],
    'register/verification-account' => ['key' => 'temp_register', 'sub' => 'username'],
    'login/verification-additional' => ['key' => 'temp_login_2fa', 'sub' => 'user_id']
];

if (array_key_exists($CURRENT_SECTION, $requirements)) {
    $req = $requirements[$CURRENT_SECTION];
    $hasData = isset($_SESSION[$req['key']][$req['sub']]) && !empty($_SESSION[$req['key']][$req['sub']]);

    if (!$hasData) {
        if ($CURRENT_SECTION === 'login/verification-additional') {
            header("Location: " . $basePath . "login");
            exit;
        }
        $SECTION_FILE_NAME = 'system/error-missing-data';
        $missingDataMessage = "No has completado el paso anterior para acceder a <strong>$CURRENT_SECTION</strong>.";
    }
}

$showNavigation = !($SECTION_FILE_NAME === 'system/error-missing-data' || $CURRENT_SECTION === '404') && !in_array($CURRENT_SECTION, $publicSections);
?>