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

$basePath = '/ProjectAurora/'; 

// =======================================================================
// 1. ACTUALIZAR SESIÓN (REFRESH ROLE & PREFS) - AL INICIO
// =======================================================================
if (isset($_SESSION['user_id'])) {
    try {
        // [MODIFICADO] Obtenemos TODAS las preferencias necesarias
        $stmt = $pdo->prepare("
            SELECT u.role, u.avatar, u.account_status, 
                   p.language, p.theme, p.extended_message_time, p.open_links_in_new_tab
            FROM users u 
            LEFT JOIN user_preferences p ON u.id = p.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            $_SESSION['user_role'] = $freshUser['role'] ?? 'user';
            $_SESSION['user_avatar'] = $freshUser['avatar'];
            
            // Guardar preferencias en sesión para SSR e inyección a JS
            $_SESSION['user_lang'] = $freshUser['language'] ?? 'es-latam';
            $_SESSION['user_theme'] = $freshUser['theme'] ?? 'system';
            $_SESSION['user_extended_msg'] = $freshUser['extended_message_time'] ?? 0;
            $_SESSION['user_new_tab'] = $freshUser['open_links_in_new_tab'] ?? 1;
            
            if ($freshUser['account_status'] !== 'active') {
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();
                header("Location: " . $basePath . "status-page?status=" . $freshUser['account_status']);
                exit;
            }

        } else {
            $_SESSION = [];
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {
        // Fallback silencioso
    }
}

$DEFAULT_SECTION = 'main';
$requestUri = $_SERVER['REQUEST_URI'];

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
    'reset-password',
    'status-page',
    'login/verification-additional',
    'search', 
    // Settings
    'settings',
    'settings/your-profile',
    'settings/login-security',
    'settings/accessibility',
    // Admin
    'admin',
    'admin/dashboard',
    'admin/users',
    'admin/backups',
    'admin/server'
];

$CURRENT_SECTION = empty($requestUri) ? 'main' : $requestUri;
if (!in_array($CURRENT_SECTION, $allowedSections)) $CURRENT_SECTION = '404';
if ($CURRENT_SECTION === 'main' && $requestUri !== 'main' && !empty($requestUri)) $CURRENT_SECTION = 'main';

// Redirecciones automáticas
if ($CURRENT_SECTION === 'settings') {
    header("Location: " . $basePath . "settings/your-profile");
    exit;
}
if ($CURRENT_SECTION === 'admin') {
    header("Location: " . $basePath . "admin/dashboard");
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);

// ==========================================
// SEGURIDAD ROLES ADMIN
// ==========================================
if (strpos($CURRENT_SECTION, 'admin/') === 0) {
    $userRole = $_SESSION['user_role'] ?? 'user';
    $allowedAdminRoles = ['founder', 'administrator', 'admin'];

    if (!in_array($userRole, $allowedAdminRoles)) {
        $CURRENT_SECTION = '404'; 
    }
}

// ==========================================
// DEFINICIÓN DE ARRAYS DE SECCIONES
// ==========================================
$appSections = ['main', 'explorer'];
$systemSections = ['status-page', '404', 'error-missing-data'];

// ==========================================
// MAPEO DE ARCHIVOS
// ==========================================

// Casos especiales de Autenticación
if ($CURRENT_SECTION === 'login/verification-additional') {
    $SECTION_FILE_NAME = 'auth/login';

} elseif ($CURRENT_SECTION === 'search') {
    $SECTION_FILE_NAME = 'app/search-results';

} elseif (strpos($CURRENT_SECTION, 'register/') === 0 || $CURRENT_SECTION === 'register') {
    $SECTION_FILE_NAME = 'auth/register'; 
} elseif ($CURRENT_SECTION === 'forgot-password') {
    $SECTION_FILE_NAME = 'auth/forgot-password';
} elseif ($CURRENT_SECTION === 'reset-password') {
    $SECTION_FILE_NAME = 'auth/reset-password';
} elseif ($CURRENT_SECTION === 'login') {
    $SECTION_FILE_NAME = 'auth/login';

// Casos de Settings
} elseif (strpos($CURRENT_SECTION, 'settings/') === 0) {
    $SECTION_FILE_NAME = $CURRENT_SECTION; 

// Casos de Admin
} elseif (strpos($CURRENT_SECTION, 'admin/') === 0) {
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
// SEGURIDAD DE ACCESO (General)
// ==========================================
$publicSections = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'forgot-password', 
    'reset-password',
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

// ==========================================
// LÓGICA DE NAVEGACIÓN (SHOWHEADER)
// ==========================================
if ($isLoggedIn) {
    $showNavigation = ($SECTION_FILE_NAME !== 'system/error-missing-data');
} else {
    $showNavigation = !($SECTION_FILE_NAME === 'system/error-missing-data' || $CURRENT_SECTION === '404') && !in_array($CURRENT_SECTION, $publicSections);
}
?>