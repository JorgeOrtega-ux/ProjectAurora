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
        // --- VALIDACIÓN DE SESIÓN ACTIVA (Dispositivos) ---
        // Verificamos si la sesión actual existe en la tabla active_sessions.
        // Si no existe, significa que fue revocada remotamente.
        $currentSessionId = session_id();
        $stmtSession = $pdo->prepare("SELECT id FROM active_sessions WHERE session_id = ? AND user_id = ?");
        $stmtSession->execute([$currentSessionId, $_SESSION['user_id']]);
        
        if ($stmtSession->rowCount() === 0) {
            // Sesión no válida o revocada -> Cerrar sesión forzosamente
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: " . $basePath . "login?reason=session_revoked");
            exit;
        } else {
            // Actualizar 'last_activity' para mantenerla viva en la lista
            $pdo->prepare("UPDATE active_sessions SET last_activity = NOW() WHERE session_id = ?")->execute([$currentSessionId]);
        }

        // --- CARGA DE DATOS DE USUARIO ---
        $stmt = $pdo->prepare("
            SELECT u.role, u.username, u.uuid, 
                   p.language, p.theme, p.extended_alerts 
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
            
            $_SESSION['theme'] = $freshUser['theme'] ?? 'system';
            $_SESSION['extended_alerts'] = $freshUser['extended_alerts'] ?? 0;
            
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

$guestRoutes = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verify', 
    'recover-password', 
    'recover-password-reset',
    '2fa-challenge'
];

$is2faPending = isset($_SESSION['temp_2fa_user_id']);

if (!$isLoggedIn) {
    if ($currentSection === '2fa-challenge' && $is2faPending) {
        // Permitir acceso
    } 
    elseif (!in_array($currentSection, $guestRoutes)) {
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