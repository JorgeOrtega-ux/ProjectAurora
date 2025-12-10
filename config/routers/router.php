<?php
/**
 * config/routers/router.php
 */

$basePath = '/ProjectAurora/'; 

// CORRECCIÓN: Rutas relativas desde config/routers/ hacia config/database y config/helpers
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../helpers/i18n.php'; 

// 1. Lógica de Autenticación y Sesión
$isLoggedIn = isset($_SESSION['user_id']);
$userLang = null;

if ($isLoggedIn) {
    try {
        // Obtenemos rol y preferencias de idioma en una sola consulta
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
            $userLang = $freshUser['language']; // Preferencia de usuario
        } else {
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {}
}

// 2. Determinar Idioma a Cargar
if ($userLang) {
    load_translations($userLang);
} else {
    load_translations(detect_browser_language());
}

// 3. Análisis de URL y Rutas
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}
$path = strtok($path, '?');
$currentSection = rtrim($path, '/');

// Interceptar API
if (strpos($currentSection, 'api/') === 0) {
    // CORRECCIÓN: Subir dos niveles para llegar a la raíz (config/routers/ -> config/ -> raiz/)
    $apiTarget = __DIR__ . '/../../' . $currentSection;
    if (file_exists($apiTarget)) {
        require_once $apiTarget;
        exit;
    }
}

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

$guestRoutes = ['login', 'register', 'register/aditional-data', 'register/verify', 'recover-password', 'recover-password-reset'];
// Ajustar nombres de rutas internas si cambiaste nombres de archivo, pero los keys del loader parecen iguales.
$appRoutes = ['main', 'explorer', 'settings/your-profile', 'settings/login-and-security', 'settings/accessibility'];
$validRoutes = array_merge($appRoutes, $guestRoutes);

if (!$isLoggedIn) {
    if (!in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
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