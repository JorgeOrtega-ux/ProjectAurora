<?php
/**
 * includes/router.php
 * Encargado de la lógica de enrutamiento y seguridad.
 */

// 1. Configuración Básica
$basePath = '/ProjectAurora/'; 

// 2. Conexión a BD y Sesión
// (db.php inicia la sesión y conecta a la base de datos)
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

// Si está vacío, es la home
if ($currentSection === '') { 
    $currentSection = 'main'; 
}

// 4. Estado de la Sesión
$isLoggedIn = isset($_SESSION['user_id']);

// 5. Refresco de Sesión (Seguridad)
// Verifica que el usuario siga existiendo y actualiza su rol/datos
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
            // Si el usuario fue borrado de la BD, cerrar sesión forzosamente
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {
        // Error silencioso en producción
    }
}

// 6. Definición de Rutas (Whitelisting)
$guestRoutes = ['login', 'register', 'register/aditional-data', 'register/verify'];
$appRoutes = ['main', 'explorer'];

// Todas las rutas válidas conocidas por el sistema
$validRoutes = array_merge($appRoutes, $guestRoutes);

// 7. Middleware de Autenticación (Redirecciones)
if (!$isLoggedIn) {
    // USUARIO NO LOGUEADO:
    // Si intenta acceder a algo que NO es ruta de invitado, mandar al login
    if (!in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    // USUARIO LOGUEADO:
    // Si intenta acceder a login o registro, mandar al home
    if (in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

// 8. Manejo de Error 404
// Si la sección no existe en la lista de rutas válidas, forzar 404
if (!in_array($currentSection, $validRoutes)) {
    $currentSection = '404'; 
}

// 9. Variables para la Vista
$userRole = ($isLoggedIn && isset($_SESSION['role'])) ? $_SESSION['role'] : 'user';
?>