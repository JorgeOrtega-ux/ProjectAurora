<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// [IMPORTANTE] Requerido para poder verificar el estado en la BD
require_once __DIR__ . '/database.php';

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
    'register/additional-data', 
    'register/verification-account',
    'forgot-password',
    // [NUEVO] Agregamos la página de status a las permitidas
    'status-page'
];

$CURRENT_SECTION = empty($requestUri) ? 'main' : $requestUri;
if (!in_array($CURRENT_SECTION, $allowedSections)) $CURRENT_SECTION = '404';
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri)) $CURRENT_SECTION = 'main';

// ==========================================
// [NUEVO] VERIFICACIÓN DE ESTADO Y AUTODESTRUCCIÓN DE SESIÓN
// ==========================================
$isLoggedIn = isset($_SESSION['user_id']);

if ($isLoggedIn) {
    try {
        // Verificamos el estado en tiempo real
        $stmt = $pdo->prepare("SELECT account_status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();

        // Si encontramos estado y NO es active
        if ($status && $status !== 'active') {
            // 1. Destruimos la sesión completamente
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            
            // 2. Redirigimos a la página de status (ahora como invitado) pasando el motivo
            header("Location: " . $basePath . "status-page?status=" . $status);
            exit;
        }
    } catch (Exception $e) {
        // Si falla la BD, permitimos continuar (o podrías manejar error 500)
    }
}

// --- MAPEO INTELIGENTE DE ARCHIVOS ---
if (strpos($CURRENT_SECTION, 'register/') === 0 || $CURRENT_SECTION === 'register') {
    $SECTION_FILE_NAME = 'register'; 
} else {
    $SECTION_FILE_NAME = str_replace('/', '-', $CURRENT_SECTION);
}

// ==========================================
// EL GUARDIA (Seguridad de Pasos)
// ==========================================
// Actualizamos variable isLoggedIn por si la sesión se destruyó arriba (aunque el exit lo previene)
$isLoggedIn = isset($_SESSION['user_id']);

// [NUEVO] Añadido 'status-page' a secciones públicas
$publicSections = ['login', 'register', 'register/additional-data', 'register/verification-account', 'forgot-password', 'status-page'];

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

// El header NO se muestra en status-page porque es pública
$showNavigation = !($SECTION_FILE_NAME === 'error-missing-data' || $CURRENT_SECTION === '404') && !in_array($CURRENT_SECTION, $publicSections);
?>