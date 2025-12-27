<?php
// public/index.php

// 1. BOOTSTRAP (Carga BD, Sesión, Utils, I18n)
$services = require_once __DIR__ . '/../includes/bootstrap.php';
extract($services); 

// 2. SEGURIDAD HTTP
// Obtenemos el nonce llamando al método que creamos en Utils
$cspNonce = Utils::applySecurityHeaders();

// 3. AUTO-LOGIN (Middleware)
// Instanciamos AuthService solo para intentar el login automático si no hay sesión
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

$isLoggedIn = isset($_SESSION['user_id']);
$publicRoutes = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verification-account', 
    'recover-password', 
    'reset-password'
];

// Redirecciones de seguridad (Auth Guard)
if (!$isLoggedIn && !in_array($currentSection, $publicRoutes)) {
    header("Location: " . $basePath . "login");
    exit;
} elseif ($isLoggedIn && in_array($currentSection, $publicRoutes)) {
    header("Location: " . $basePath);
    exit;
}

// Refrescar datos de sesión si está logueado
if ($isLoggedIn) {
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
        }
    } catch (Exception $e) {
        error_log("Error al refrescar sesión: " . $e->getMessage());
    }
}

// 6. DATOS DE VISTA
$userRole = $_SESSION['role'] ?? 'guest';
$globalAvatarSrc = Utils::getGlobalAvatarSrc();
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$turnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? '';

// Pre-codificar JSON para evitar errores de sintaxis en el HTML
$jsUserPrefs = json_encode($_SESSION['preferences'] ?? new stdClass());
$jsTranslations = json_encode($i18n->getAll());

$routesMap = require __DIR__ . '/../config/routes.php';
$fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];
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
                <?php if ($isLoggedIn): ?>
                    <div class="general-content-top">
                        <?php include __DIR__ . '/../includes/layouts/header.php'; ?>
                    </div>
                <?php endif; ?>
                <div class="general-content-bottom">
                    <?php if ($isLoggedIn): ?>
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