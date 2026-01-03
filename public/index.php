<?php
// public/index.php

// 1. BOOTSTRAP
$services = require_once __DIR__ . '/../includes/bootstrap.php';
extract($services); 

// 2. SEGURIDAD HTTP
$cspNonce = Utils::applySecurityHeaders();

// 3. AUTO-LOGIN
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../api/services/AuthService.php';
    $authService = new AuthService($pdo, $i18n); 
    $authService->attemptAutoLogin();
}

// 4. CSRF TOKEN INIT
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 5. RUTEO Y CONTROL DE ACCESO
require_once __DIR__ . '/../config/routers/router.php';

// === CONFIGURACIÓN DE MANTENIMIENTO ===
$maintenanceMode = Utils::getServerConfig($pdo, 'maintenance_mode', '0');
$userRole = $_SESSION['role'] ?? 'guest';
$allowedRoles = ['founder', 'administrator', 'moderator'];

// LISTA BLANCA: Secciones que SIEMPRE deben ser visibles (Renderizar HTML)
$alwaysVisibleSections = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verification-account', 
    'recover-password', 
    'reset-password'
];

// Determinar si debemos mostrar la pantalla de Mantenimiento
// Solo si está activo, el usuario NO es staff, y NO está intentando ver una sección pública.
$showMaintenanceScreen = (
    $maintenanceMode === '1' && 
    !in_array($userRole, $allowedRoles) && 
    !in_array($currentSection, $alwaysVisibleSections)
);

// 6. GESTIÓN DE SESIÓN
$isLoggedIn = isset($_SESSION['user_id']);
$publicRoutes = $alwaysVisibleSections; // Usamos la misma lista para el Auth Guard

// Redirecciones de Seguridad (Auth Guard)
if (!$showMaintenanceScreen) {
    if (!$isLoggedIn && !in_array($currentSection, $publicRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    } elseif ($isLoggedIn && in_array($currentSection, $publicRoutes)) {
        // Si ya está logueado e intenta ir al login, lo mandamos al inicio
        header("Location: " . $basePath);
        exit;
    }
}

// Refrescar datos de sesión si corresponde
if ($isLoggedIn && !$showMaintenanceScreen) {
    try {
        $stmt = $pdo->prepare("SELECT role, avatar_path, username, email, two_factor_enabled FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['avatar'] = $freshUser['avatar_path'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['email'] = $freshUser['email'];
            $_SESSION['two_factor_enabled'] = $freshUser['two_factor_enabled'];
        } else {
            // [FIX DE SEGURIDAD] 
            // Si el usuario no existe en la BD (ej. tabla vacía), 
            // se destruye la sesión y se fuerza la salida.
            session_unset();
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {
        error_log("Error sesión: " . $e->getMessage());
    }
}

// 7. PREPARAR VISTA
$userRole = $_SESSION['role'] ?? 'guest';
$globalAvatarSrc = Utils::getGlobalAvatarSrc();
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$turnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? '';

$jsUserPrefs = json_encode($_SESSION['preferences'] ?? new stdClass());
$jsTranslations = json_encode($i18n->getAll());

// Decidir qué archivo cargar
if ($showMaintenanceScreen) {
    $fileToLoad = __DIR__ . '/maintenance.php';
    $showInterface = false; // Ocultar Header y Menú
} else {
    $routesMap = require __DIR__ . '/../config/routes.php';
    $fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];
    $showInterface = $isLoggedIn; // Mostrar interfaz solo si está dentro del sistema
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($userLang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $i18n->t('app.name'); ?></title>

    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"></script>

    <script nonce="<?php echo $cspNonce; ?>">
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.USER_PREFS = <?php echo $jsUserPrefs; ?>;
        window.TRANSLATIONS = <?php echo $jsTranslations; ?>;
        window.TURNSTILE_SITE_KEY = '<?php echo $turnstileSiteKey; ?>';
    </script>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />

    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/root.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/components.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/dialogs.css">

    <script type="module" src="<?php echo $basePath; ?>public/assets/js/app-init.js"></script>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                <?php if ($showInterface): ?>
                    <div class="general-content-top">
                        <?php include __DIR__ . '/../includes/layouts/header.php'; ?>
                    </div>
                <?php endif; ?>
                <div class="general-content-bottom">
                    <?php if ($showInterface): ?>
                        <?php include __DIR__ . '/../includes/modules/module-surface.php'; ?>
                    <?php endif; ?>
                    
                    <div class="general-content-scrolleable overflow-y" data-container="main-section">
                        <?php
                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            echo $i18n->t('errors.file_not_found');
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/layouts/dialogs.php'; ?>
</body>
</html>