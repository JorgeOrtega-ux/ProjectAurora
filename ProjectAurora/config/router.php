<?php
// config/router.php
if (session_status() === PHP_SESSION_NONE) session_start();

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
    // [NUEVO] URL para el segundo paso del login
    'login/verification-additional'
];

$CURRENT_SECTION = empty($requestUri) ? 'main' : $requestUri;
if (!in_array($CURRENT_SECTION, $allowedSections)) $CURRENT_SECTION = '404';
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri)) $CURRENT_SECTION = 'main';

// ==========================================
// VERIFICACIÓN DE ESTADO Y AUTODESTRUCCIÓN
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

// --- MAPEO INTELIGENTE DE ARCHIVOS ---
if (strpos($CURRENT_SECTION, 'register/') === 0 || $CURRENT_SECTION === 'register') {
    $SECTION_FILE_NAME = 'register'; 
} else {
    // Esto convertirá 'login/verification-additional' en 'login-verification-additional.php'
    $SECTION_FILE_NAME = str_replace('/', '-', $CURRENT_SECTION);
}

// ==========================================
// EL GUARDIA (Seguridad de Pasos)
// ==========================================
$isLoggedIn = isset($_SESSION['user_id']);

// [MODIFICADO] Agregamos la nueva ruta a las secciones públicas
$publicSections = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'forgot-password', 
    'status-page',
    'login/verification-additional' // <-- IMPORTANTE
];

if (!$isLoggedIn && !in_array($CURRENT_SECTION, $publicSections) && $CURRENT_SECTION !== '404') {
    header("Location: " . $basePath . "login"); exit;
}
if ($isLoggedIn && in_array($CURRENT_SECTION, $publicSections)) {
    header("Location: " . $basePath); exit;
}

// Definir REQUISITOS para entrar a cada URL
$requirements = [
    'register/additional-data'      => ['key' => 'temp_register', 'sub' => 'email'],
    'register/verification-account' => ['key' => 'temp_register', 'sub' => 'username'],
    // [NUEVO] Requisito: Debe haber un ID de usuario temporal esperando 2FA
    'login/verification-additional' => ['key' => 'temp_login_2fa', 'sub' => 'user_id']
];

// Verificar requisitos
if (array_key_exists($CURRENT_SECTION, $requirements)) {
    $req = $requirements[$CURRENT_SECTION];
    $hasData = isset($_SESSION[$req['key']][$req['sub']]) && !empty($_SESSION[$req['key']][$req['sub']]);

    if (!$hasData) {
        // Si intentan entrar directo sin pasar por login, los mandamos al login
        if ($CURRENT_SECTION === 'login/verification-additional') {
             header("Location: " . $basePath . "login"); 
             exit;
        }
        
        $SECTION_FILE_NAME = 'error-missing-data';
        $missingDataMessage = "No has completado el paso anterior para acceder a <strong>$CURRENT_SECTION</strong>.";
    }
}

$showNavigation = !($SECTION_FILE_NAME === 'error-missing-data' || $CURRENT_SECTION === '404') && !in_array($CURRENT_SECTION, $publicSections);
?>