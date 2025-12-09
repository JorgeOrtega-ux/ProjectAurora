<?php
/**
 * includes/router.php
 * Encargado de la lógica de enrutamiento y seguridad.
 */

// 1. Configuración Básica
$basePath = '/ProjectAurora/'; 

// 2. Conexión a BD y Sesión
require_once __DIR__ . '/db.php';

// 3. Análisis de la URL
$requestUri = $_SERVER['REQUEST_URI'];

// Detectar la sub-ruta relativa al basePath
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

// Limpiar parámetros GET (?id=...)
$path = strtok($path, '?');
$currentSection = rtrim($path, '/');

// --- NUEVO: INTERCEPTAR RUTAS DE API ---
// Si la URL comienza con "api/", servimos el archivo desde la raíz y detenemos la ejecución.
if (strpos($currentSection, 'api/') === 0) {
    // Construir la ruta al archivo fuera de public (ej: ../api/auth_handler.php)
    $apiTarget = __DIR__ . '/../' . $currentSection;
    
    if (file_exists($apiTarget)) {
        require_once $apiTarget;
        exit; // Detenemos aquí para que no cargue el HTML del index
    }
}

// Si está vacío, es la home
if ($currentSection === '') { 
    $currentSection = 'main'; 
}

// 4. Estado de la Sesión
$isLoggedIn = isset($_SESSION['user_id']);

// 5. Refresco de Sesión (Seguridad)
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT role, username, uuid FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['uuid'] = $freshUser['uuid'];
        } else {
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {}
}

// 6. Definición de Rutas (Whitelisting)
$guestRoutes = ['login', 'register', 'register/aditional-data', 'register/verify'];
$appRoutes = ['main', 'explorer'];

$validRoutes = array_merge($appRoutes, $guestRoutes);

// 7. Middleware de Autenticación
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

// 8. Manejo de Error 404
if (!in_array($currentSection, $validRoutes)) {
    $currentSection = '404'; 
}

// 9. Variables para la Vista
$userRole = ($isLoggedIn && isset($_SESSION['role'])) ? $_SESSION['role'] : 'user';
?>