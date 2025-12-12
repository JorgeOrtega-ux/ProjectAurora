<?php
/**
 * config/routers/router.php
 */

$basePath = '/ProjectAurora/'; 

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../helpers/i18n.php'; 

// ==========================================
// 0. CARGAR CONFIGURACIÓN DEL SERVIDOR
// ==========================================
$serverConfig = ['maintenance_mode' => 0, 'allow_registrations' => 1];
try {
    $stmtConfig = $pdo->query("SELECT * FROM server_config WHERE id = 1");
    $dbConfig = $stmtConfig->fetch();
    if ($dbConfig) {
        $serverConfig = $dbConfig;
    }
} catch (Exception $e) {}

// Global para uso en servicios
$GLOBALS['SERVER_CONFIG'] = $serverConfig;

// ==========================================
// 1. AUTENTICACIÓN Y SESIÓN
// ==========================================
$isLoggedIn = isset($_SESSION['user_id']);
$userLang = null;
$userRole = 'user'; 

if ($isLoggedIn) {
    try {
        $currentSessionId = session_id();
        $stmtSession = $pdo->prepare("SELECT id FROM active_sessions WHERE session_id = ? AND user_id = ?");
        $stmtSession->execute([$currentSessionId, $_SESSION['user_id']]);
        
        if ($stmtSession->rowCount() === 0) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: " . $basePath . "login?reason=session_revoked");
            exit;
        } else {
            $pdo->prepare("UPDATE active_sessions SET last_activity = NOW() WHERE session_id = ?")->execute([$currentSessionId]);
        }

        $stmt = $pdo->prepare("SELECT u.role, u.username, u.uuid, p.language, p.theme, p.extended_alerts FROM users u LEFT JOIN user_preferences p ON u.id = p.user_id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['uuid'] = $freshUser['uuid'];
            $_SESSION['theme'] = $freshUser['theme'] ?? 'system';
            $_SESSION['extended_alerts'] = $freshUser['extended_alerts'] ?? 0;
            $userLang = $freshUser['language']; 
            $userRole = $freshUser['role'];
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
// 3. ANÁLISIS DE URL
// ==========================================
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}
$path = strtok($path, '?');
$currentSection = rtrim($path, '/');

// Permitir API
if (strpos($currentSection, 'api/') === 0) {
    $apiTarget = __DIR__ . '/../../' . $currentSection;
    if (file_exists($apiTarget)) {
        require_once $apiTarget;
        exit;
    }
}

if ($currentSection === 'settings') { header("Location: " . $basePath . "settings/your-profile"); exit; }
// MODIFICADO: Redirigir admin raíz al dashboard
if ($currentSection === 'admin') { header("Location: " . $basePath . "admin/dashboard"); exit; }

$resetToken = null;
if (strpos($currentSection, 'recover-password/') === 0) {
    $parts = explode('/', $currentSection);
    if (isset($parts[1]) && !empty($parts[1])) {
        $resetToken = $parts[1]; 
        $currentSection = 'recover-password-reset'; 
    }
}

if ($currentSection === '') { $currentSection = 'main'; }

// ==========================================
// 4. LÓGICA DE MANTENIMIENTO Y REGISTRO
// ==========================================

// Rutas permitidas en mantenimiento (Login para que entren admins)
$maintenanceWhitelist = ['login', 'maintenance', '2fa-challenge'];

// MANTENIMIENTO ACTIVO
if ($serverConfig['maintenance_mode'] == 1) {
    $isAdmin = in_array($userRole, ['founder', 'administrator']);
    
    // Si NO es admin, redirigir a mantenimiento (salvo que ya esté en login o whitelist)
    if (!$isAdmin) {
        if (!in_array($currentSection, $maintenanceWhitelist)) {
            $currentSection = 'maintenance';
        }
    }
}

// REGISTROS CERRADOS
if ($serverConfig['allow_registrations'] == 0) {
    if (strpos($currentSection, 'register') === 0) {
        header("Location: " . $basePath . "account-status?type=registrations_closed");
        exit;
    }
}

// ==========================================
// 5. VALIDACIÓN DE RUTAS
// ==========================================
$routes = require __DIR__ . '/../routes.php';
$validRoutes = array_keys($routes); 

$guestRoutes = ['login', 'register', 'register/aditional-data', 'register/verify', 'recover-password', 'recover-password-reset', '2fa-challenge', 'account-status', 'maintenance'];

$is2faPending = isset($_SESSION['temp_2fa_user_id']);

if (!$isLoggedIn) {
    if ($currentSection === '2fa-challenge' && $is2faPending) {
        // Permitir
    } elseif (!in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    if (in_array($currentSection, ['login', 'register'])) {
         header("Location: " . $basePath);
         exit;
    }
}

// Roles Admin
if (strpos($currentSection, 'admin/') === 0) {
    $allowedRoles = ['founder', 'administrator'];
    if (!in_array($userRole, $allowedRoles)) {
        $currentSection = '404';
    }
}

if (!in_array($currentSection, $validRoutes)) {
    $currentSection = '404'; 
}
?>