<?php
// public/index.php

// 1. BOOTSTRAP
$services = require_once __DIR__ . '/../includes/bootstrap.php';
extract($services); // $pdo, $i18n, $redis

// 2. SEGURIDAD HTTP
$cspNonce = Utils::applySecurityHeaders();

// 3. AUTO-LOGIN
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../api/services/AuthService.php';
    $authService = new AuthService($pdo, $i18n, $redis); 
    $authService->attemptAutoLogin();
}

// 4. CSRF TOKEN INIT
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 5. RUTEO Y CONTROL DE ACCESO
require_once __DIR__ . '/../config/routers/router.php';

// Carga de reglas de seguridad
$securityRules = require __DIR__ . '/../config/security.php';
$authRoutes = $securityRules['auth_routes'];
$protectedRoutes = $securityRules['protected_routes'];

// Configuración de mantenimiento
$maintenanceMode = Utils::getServerConfig($pdo, 'maintenance_mode', '0');
$userRole = $_SESSION['role'] ?? 'guest'; 
$allowedRoles = ['founder', 'administrator', 'moderator'];

$isAdminRoute = strpos($currentSection, 'admin/') === 0;
$isLoggedIn = isset($_SESSION['user_id']);

$showMaintenanceScreen = (
    $maintenanceMode === '1' && 
    !in_array($userRole, $allowedRoles) && 
    !in_array($currentSection, $authRoutes) &&
    $currentSection !== 'account-status'
);

// 6. REFRESCAR DATOS DE SESIÓN
if ($isLoggedIn && !$showMaintenanceScreen) {
    try {
        // Verificar token de persistencia
        if (isset($_SESSION['current_token_id'])) {
            $stmtToken = $pdo->prepare("SELECT id FROM user_auth_tokens WHERE id = ?");
            $stmtToken->execute([$_SESSION['current_token_id']]);
            if (!$stmtToken->fetch()) {
                session_unset(); session_destroy();
                setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                header("Location: " . $basePath . "login"); exit;
            }
        }
        
        // Obtener datos frescos
        $stmt = $pdo->prepare("SELECT role, avatar_path, username, email, two_factor_enabled, account_status, suspension_ends_at, status_reason FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            // Lógica de Bloqueo
            $isRestricted = false;
            if ($freshUser['account_status'] === 'deleted') {
                $isRestricted = true;
            } elseif ($freshUser['account_status'] === 'suspended') {
                $isPermanent = empty($freshUser['suspension_ends_at']);
                $isActiveSuspension = $isPermanent || (strtotime($freshUser['suspension_ends_at']) > time());
                if ($isActiveSuspension) $isRestricted = true;
            }

            if ($isRestricted) {
                $restrictionData = [
                    'status' => $freshUser['account_status'],
                    'reason' => $freshUser['status_reason'],
                    'suspension_ends_at' => $freshUser['suspension_ends_at']
                ];
                session_unset(); session_destroy();
                session_start();
                $_SESSION['account_status_data'] = $restrictionData;
                header("Location: " . $basePath . "account-status"); exit;
            }

            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['avatar'] = $freshUser['avatar_path'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['email'] = $freshUser['email'];
            $_SESSION['two_factor_enabled'] = $freshUser['two_factor_enabled'];

            $userRole = $freshUser['role']; 

            // Recargar preferencias
            $stmtPrefs = $pdo->prepare("SELECT language, open_links_new_tab, theme, extended_toast FROM user_preferences WHERE user_id = ?");
            $stmtPrefs->execute([$_SESSION['user_id']]);
            $freshPrefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

            if ($freshPrefs) {
                $previousLang = $_SESSION['preferences']['language'] ?? 'es-latam';

                $_SESSION['preferences'] = [
                    'language' => $freshPrefs['language'],
                    'open_links_new_tab' => (bool)$freshPrefs['open_links_new_tab'],
                    'theme' => $freshPrefs['theme'],
                    'extended_toast' => (bool)$freshPrefs['extended_toast']
                ];

                if ($freshPrefs['language'] !== $previousLang) {
                    $i18n = new I18n($freshPrefs['language']);
                }
            }
        } else {
            session_unset(); session_destroy();
            header("Location: " . $basePath . "login"); exit;
        }
    } catch (Exception $e) { error_log("Error sesión: " . $e->getMessage()); }
}

// 7. GESTIÓN DE REDIRECCIONES
if (!$showMaintenanceScreen) {
    if (($isAdminRoute || in_array($currentSection, $protectedRoutes)) && !$isLoggedIn) {
        header("Location: " . $basePath . "login");
        exit;
    }
    if ($isLoggedIn && in_array($currentSection, $authRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

// 8. PREPARAR VISTA
$globalAvatarSrc = Utils::getGlobalAvatarSrc();
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$turnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? '';

$jsUserPrefs = json_encode($_SESSION['preferences'] ?? new stdClass());
$jsTranslations = json_encode($i18n->getAll());

if ($showMaintenanceScreen) {
    $fileToLoad = __DIR__ . '/../includes/sections/system/status-screen.php';
    $isMaintenanceContext = true; 
    $showInterface = false; 
} else {
    $routesMap = require __DIR__ . '/../config/routes.php';
    
    if (strpos($currentSection, 'admin/') === 0 && !in_array($userRole, ['founder', 'administrator'])) {
        $fileToLoad = $routesMap['404'];
    } else {
        $fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];
    }
    
    $noInterfaceRoutes = array_merge($authRoutes, ['account-status']);
    $showInterface = !in_array($currentSection, $noInterfaceRoutes); 
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
        window.IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        (function() {
            try {
                var localPrefs = localStorage.getItem('guest_prefs');
                var prefs = window.USER_PREFS || {};
                if (!window.IS_LOGGED_IN && localPrefs) {
                    try { var parsed = JSON.parse(localPrefs); if (parsed.theme) prefs.theme = parsed.theme; } catch(e){}
                }
                var theme = prefs.theme || 'sync';
                if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
                else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
            } catch (e) {}
        })();
    </script>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/root.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/components.css">
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

                    <?php if ($showInterface): ?>
                        <div class="fab-wrapper" data-trigger="dropdown">
                            <button class="fab-button trigger-selector">
                                <span class="material-symbols-rounded">help</span>
                            </button>

                            <div class="popover-module">
                                <div class="menu-list">
                                    <div class="menu-link" data-nav="site-policy/send-feedback">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">support_agent</span>
                                        </div>
                                        <div class="menu-link-text">
                                            <?php echo $i18n->t('menu.contact_support'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</body>
</html>