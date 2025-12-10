<?php
/**
 * config/routers/router.php
 */

$basePath = '/ProjectAurora/'; 

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../helpers/i18n.php'; 

// ==========================================
// 1. AUTENTICACIÓN Y SESIÓN
// ==========================================
$isLoggedIn = isset($_SESSION['user_id']);
$userLang = null;

// Lógica para usuarios logueados completamente
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.role, u.username, u.uuid, p.language 
            FROM users u
            LEFT JOIN user_preferences p ON u.id = p.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['uuid'] = $freshUser['uuid'];
            $userLang = $freshUser['language']; 
        } else {
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {}
}

// ==========================================
// 2. CONFIGURACIÓN DE IDIOMA
// ==========================================
if ($userLang) {
    load_translations($userLang);
} else {
    load_translations(detect_browser_language());
}

// ==========================================
// 3. ANÁLISIS DE URL (ROUTING)
// ==========================================
$requestUri = $_SERVER['REQUEST_URI'];

if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

$path = strtok($path, '?');
$currentSection = rtrim($path, '/');

if (strpos($currentSection, 'api/') === 0) {
    $apiTarget = __DIR__ . '/../../' . $currentSection;
    if (file_exists($apiTarget)) {
        require_once $apiTarget;
        exit;
    }
}

// Redirecciones
if ($currentSection === 'settings') {
    header("Location: " . $basePath . "settings/your-profile");
    exit;
}

$resetToken = null;
if (strpos($currentSection, 'recover-password/') === 0) {
    $parts = explode('/', $currentSection);
    if (isset($parts[1]) && !empty($parts[1])) {
        $resetToken = $parts[1]; 
        $currentSection = 'recover-password-reset'; 
    }
}

if ($currentSection === '') { 
    $currentSection = 'main'; 
}

// ==========================================
// 4. VALIDACIÓN DE RUTAS Y SEGURIDAD
// ==========================================

$routes = require __DIR__ . '/../routes.php';
$validRoutes = array_keys($routes); 

// Rutas permitidas para GUEST (sin sesión completa)
$guestRoutes = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verify', 
    'recover-password', 
    'recover-password-reset',
    'auth/2fa-challenge' // IMPORTANTE: Permitir acceso para verificar el código
];

// Comprobación de estado intermedio (2FA Pendiente)
$is2faPending = isset($_SESSION['temp_2fa_user_id']);

if (!$isLoggedIn) {
    // Si no está logueado:
    
    // Caso especial: Está intentando validar 2FA y tiene la sesión temporal
    if ($currentSection === 'auth/2fa-challenge' && $is2faPending) {
        // Permitir acceso
    } 
    // Caso normal: Si intenta ir a una ruta que no es de guest, mandar a login
    elseif (!in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    // Si YA está logueado:
    // No debe ver login, register ni el challenge de 2fa.
    if (in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

if (!in_array($currentSection, $validRoutes)) {
    $currentSection = '404'; 
}

$userRole = ($isLoggedIn && isset($_SESSION['role'])) ? $_SESSION['role'] : 'user';
?>