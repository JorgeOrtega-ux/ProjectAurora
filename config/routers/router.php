<?php
/**
 * config/routers/router.php
 * Encargado de:
 * 1. Iniciar sesión y validar autenticación.
 * 2. Determinar la sección actual basada en la URL.
 * 3. Gestionar redirecciones de seguridad (Guest vs User).
 */

$basePath = '/ProjectAurora/'; 

// Rutas relativas desde config/routers/
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../helpers/i18n.php'; 

// ==========================================
// 1. AUTENTICACIÓN Y SESIÓN
// ==========================================
$isLoggedIn = isset($_SESSION['user_id']);
$userLang = null;

if ($isLoggedIn) {
    try {
        // Obtener rol y preferencias actualizadas
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
            // Si el usuario no existe en BD (borrado), forzar logout
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

// Limpiar base path de la URI
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

// Limpiar query params (?id=1...) y slashes finales
$path = strtok($path, '?');
$currentSection = rtrim($path, '/');

// --- A) Interceptar llamadas a la API ---
// Permite acceder a /ProjectAurora/api/auth_handler.php aunque estemos en modo rewrite
if (strpos($currentSection, 'api/') === 0) {
    $apiTarget = __DIR__ . '/../../' . $currentSection;
    if (file_exists($apiTarget)) {
        require_once $apiTarget;
        exit;
    }
}

// --- B) Manejo de casos especiales de URL ---

// Redirección amigable: /settings -> /settings/your-profile
if ($currentSection === 'settings') {
    header("Location: " . $basePath . "settings/your-profile");
    exit;
}

// Manejo de token de recuperación de contraseña (ej. recover-password/TOKEN123)
$resetToken = null;
if (strpos($currentSection, 'recover-password/') === 0) {
    $parts = explode('/', $currentSection);
    if (isset($parts[1]) && !empty($parts[1])) {
        $resetToken = $parts[1]; 
        // Cambiamos la sección lógica para que cargue la vista de reset
        $currentSection = 'recover-password-reset'; 
    }
}

// Página de inicio por defecto
if ($currentSection === '') { 
    $currentSection = 'main'; 
}

// ==========================================
// 4. VALIDACIÓN DE RUTAS Y SEGURIDAD
// ==========================================

// Cargar el mapa maestro de rutas (Centralización)
$routes = require __DIR__ . '/../routes.php';
$validRoutes = array_keys($routes); 
// Nota: Asegúrate de agregar 'recover-password-reset' a tu config/routes.php si no está.

// Definir política de acceso: ¿Qué rutas son para invitados?
$guestRoutes = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verify', 
    'recover-password', 
    'recover-password-reset'
];

if (!$isLoggedIn) {
    // USUARIO NO LOGUEADO:
    // Solo puede ver rutas de invitado. Si intenta ver otra, mandar a login.
    if (!in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    // USUARIO LOGUEADO:
    // No debería ver login/registro. Si intenta entrar, mandar al home.
    if (in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

// Si la sección no existe en nuestro mapa de rutas, es un 404
if (!in_array($currentSection, $validRoutes)) {
    $currentSection = '404'; 
}

// Variable auxiliar para la vista
$userRole = ($isLoggedIn && isset($_SESSION['role'])) ? $_SESSION['role'] : 'user';
?>