<?php
// public/index.php

// 1. Cargar el Router (Configuración y Sesión)
require_once __DIR__ . '/../config/routers/router.php';

// 2. Definir el mapa de rutas FÍSICAS
$sectionMap = require __DIR__ . '/../config/routes.php';

// Lógica de detección de sección
$isSettingsSection = (strpos($currentSection, 'settings/') === 0);
$isAdminSection = (strpos($currentSection, 'admin/') === 0);
$isHelpSection = (strpos($currentSection, 'help/') === 0);

// --- LÓGICA DE AVATAR DEL HEADER (MODIFICADA CON SELF-HEALING) ---
$globalAvatarSrc = '';
$headerAvatarNeedsRepair = false; 

if (isset($_SESSION['uuid']) && isset($_SESSION['user_id'])) {
    $uuid = $_SESSION['uuid'];
    $userId = $_SESSION['user_id'];
    $cacheBuster = '?v=' . time(); 
    
    // Rutas relativas (para el navegador)
    $relCustom  = 'assets/uploads/avatars/custom/' . $uuid . '.png';
    $relDefault = 'assets/uploads/avatars/default/' . $uuid . '.png';
    
    // Rutas absolutas (para file_exists)
    $absCustom  = __DIR__ . '/' . $relCustom;
    $absDefault = __DIR__ . '/' . $relDefault;

    // 1. Prioridad: Avatar personalizado
    if (file_exists($absCustom)) {
        $globalAvatarSrc = $basePath . $relCustom . $cacheBuster;
    } 
    // 2. Fallback: Avatar por defecto (SOLO SI EXISTE)
    elseif (file_exists($absDefault)) {
        $globalAvatarSrc = $basePath . $relDefault . $cacheBuster;
    }
    // 3. Emergencia: No existe ninguno (Se borraron) -> Usar Fallback Local
    else {
        $headerAvatarNeedsRepair = true;
        // Determinista: El mismo usuario siempre obtiene el mismo color de fallback
        $fallbackIndex = ($userId % 5) + 1;
        $globalAvatarSrc = $basePath . 'assets/uploads/avatars/fallback/' . $fallbackIndex . '.png';
    }
}

// --- LÓGICA DE TEMA ---
$initialThemeClass = 'light-theme';
$userThemePref = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'system';
$userExtendedAlerts = isset($_SESSION['extended_alerts']) ? (int)$_SESSION['extended_alerts'] : 0;

if ($userThemePref === 'dark') {
    $initialThemeClass = 'dark-theme';
} elseif ($userThemePref === 'light') {
    $initialThemeClass = 'light-theme';
} else {
    $initialThemeClass = 'light-theme system-theme-pending';
}

// --- SEGURIDAD: FILTRADO DE CONFIGURACIÓN PÚBLICA ---
$publicConfigKeys = [
    'maintenance_mode',
    'allow_registrations',
    'min_password_length',
    'max_password_length',
    'min_username_length',
    'max_username_length',
    'max_email_length',
    'profile_picture_max_size'
];

$rawConfig = $GLOBALS['SERVER_CONFIG'] ?? [];
$safePublicConfig = array_intersect_key($rawConfig, array_flip($publicConfigKeys));

?>
<!DOCTYPE html>
<html lang="<?php echo isset($userLang) ? $userLang : 'es'; ?>" class="<?php echo $initialThemeClass; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.TRANSLATIONS = <?php echo json_encode($GLOBALS['AURORA_TRANSLATIONS']); ?>;
        
        // INYECCIÓN DE CONFIGURACIÓN DEL SERVIDOR (FILTRADA)
        window.SERVER_CONFIG = <?php echo json_encode($safePublicConfig); ?>;
        
        window.USER_PREFS = {
            theme: '<?php echo $userThemePref; ?>',
            extended_alerts: <?php echo $userExtendedAlerts; ?>
        };

        // EXPOSICIÓN DEL ID DE USUARIO PARA REPARACIÓN UI
        // Esto permite que JS sepa qué tarjeta actualizar en la lista de usuarios
        window.CURRENT_USER_ID = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;

        // Función de traducción mejorada
        window.t = function(key, ...args) {
            let text = window.TRANSLATIONS[key] || key;
            if (args.length > 0) {
                args.forEach(arg => {
                    text = text.replace('%s', arg);
                });
            }
            return text;
        };
    </script>
    
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/components.css">

    <title><?php echo __('app.name'); ?></title>

    <?php 
    $showFullLayout = ($isLoggedIn || $isHelpSection) && $currentSection !== 'maintenance';
    
    if (!$showFullLayout): ?>
        <style>
            [data-container="main-section"] {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100%;
            }
        </style>
    <?php endif; ?>
</head>

<body>

    <div class="page-wrapper <?php echo (!$isLoggedIn) ? 'auth-mode' : ''; ?>">
        <div class="main-content">
            <div class="general-content">

                <?php 
                if ($showFullLayout): ?>
                    <div class="general-content-top">
                        <div class="header">
                            <div class="header-left">
                                <div class="header-button" 
                                     data-action="toggleModuleSurface"
                                     data-tooltip="<?php echo __('global.menu'); ?>"
                                     data-lang-tooltip="global.menu">
                                    <span class="material-symbols-rounded">menu</span>
                                </div>
                            </div>

                            <div class="header-center" id="headerCenter">
                                <div class="search-wrapper">
                                    <div class="search-container">
                                        <div class="search-icon">
                                            <span class="material-symbols-rounded">search</span>
                                        </div>
                                        <div class="search-input">
                                            <input type="text" 
                                                   placeholder="<?php echo __('global.search_placeholder'); ?>"
                                                   data-lang-placeholder="global.search_placeholder">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="header-right">
                                <div class="header-item">
                                    <div class="header-button profile-button"
                                        data-action="toggleModuleProfile"
                                        data-role="<?php echo htmlspecialchars($userRole); ?>"
                                        data-tooltip="<?php echo __('menu.profile'); ?>"
                                        data-lang-tooltip="menu.profile">

                                        <?php if ($isLoggedIn): ?>
                                            <img src="<?php echo $globalAvatarSrc; ?>" 
                                                 alt="<?php echo __('menu.profile'); ?>" 
                                                 class="profile-img"
                                                 <?php echo $headerAvatarNeedsRepair ? 'data-needs-repair="true"' : ''; ?>>
                                        <?php else: ?>
                                            <span style="font-weight:bold; color:#555; position: relative; z-index: 1;">?</span>
                                        <?php endif; ?>
                                    </div>

                                </div>

                                <div class="module-content module-profile body-text disabled" data-module="moduleProfile">
                                    <div class="menu-content">
                                        <div class="pill-container">
                                            <div class="drag-handle"></div>
                                        </div>
                                        <div class="menu-list">
                                            
                                            <?php if ($isLoggedIn): ?>
                                                <?php if (in_array($_SESSION['role'], ['founder', 'administrator'])): ?>
                                                    <div class="menu-link" data-nav="admin/dashboard">
                                                        <div class="menu-link-icon">
                                                            <span class="material-symbols-rounded">admin_panel_settings</span>
                                                        </div>
                                                        <div class="menu-link-text">
                                                            <span data-lang-key="menu.admin_panel"><?php echo __('menu.admin_panel'); ?></span>
                                                        </div>
                                                    </div>
                                                    <hr class="component-divider" style="margin: 4px 0;">
                                                <?php endif; ?>
                                                
                                                <div class="menu-link" data-nav="settings/your-profile">
                                                    <div class="menu-link-icon">
                                                        <span class="material-symbols-rounded">settings</span>
                                                    </div>
                                                    <div class="menu-link-text">
                                                        <span data-lang-key="menu.settings"><?php echo __('menu.settings'); ?></span>
                                                    </div>
                                                </div>

                                                <div class="menu-link" data-nav="help/privacy">
                                                    <div class="menu-link-icon">
                                                        <span class="material-symbols-rounded">help</span>
                                                    </div>
                                                    <div class="menu-link-text">
                                                        <span data-lang-key="menu.help"><?php echo __('menu.help'); ?></span>
                                                    </div>
                                                </div>

                                                <div class="menu-link" id="btn-logout">
                                                    <div class="menu-link-icon">
                                                        <span class="material-symbols-rounded">logout</span>
                                                    </div>
                                                    <div class="menu-link-text">
                                                        <span data-lang-key="menu.logout"><?php echo __('menu.logout'); ?></span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="menu-link" onclick="window.location.href='<?php echo $basePath; ?>login'">
                                                    <div class="menu-link-icon">
                                                        <span class="material-symbols-rounded">login</span>
                                                    </div>
                                                    <div class="menu-link-text">
                                                        <span><?php echo __('auth.login.title'); ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <?php 
                    if ($showFullLayout): ?>
                        <div class="module-content module-surface body-text disabled" data-module="moduleSurface">
                            <div class="menu-content">
                                <div class="menu-content-top">

                                    <div id="nav-main" class="menu-list <?php echo ($isSettingsSection || $isAdminSection || $isHelpSection) ? 'disabled' : ''; ?>">
                                        <div class="menu-link <?php echo ($currentSection === 'main') ? 'active' : ''; ?>" data-nav="main">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">home</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="menu.home"><?php echo __('menu.home'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'explorer') ? 'active' : ''; ?>" data-nav="explorer">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">explore</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="menu.explore"><?php echo __('menu.explore'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="nav-settings" class="menu-list <?php echo !$isSettingsSection ? 'disabled' : ''; ?>">
                                        <div class="menu-link menu-link-back" data-nav="main">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">arrow_back</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="global.back_home"><?php echo __('global.back_home'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'settings/your-profile') ? 'active' : ''; ?>" data-nav="settings/your-profile">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">account_circle</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="menu.profile"><?php echo __('menu.profile'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'settings/login-and-security') ? 'active' : ''; ?>" data-nav="settings/login-and-security">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">lock</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="menu.security"><?php echo __('menu.security'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'settings/accessibility') ? 'active' : ''; ?>" data-nav="settings/accessibility">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">accessibility_new</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="menu.accessibility"><?php echo __('menu.accessibility'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="nav-help" class="menu-list <?php echo !$isHelpSection ? 'disabled' : ''; ?>">
                                        <div class="menu-link menu-link-back" data-nav="main">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">arrow_back</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="global.back_home"><?php echo __('global.back_home'); ?></span>
                                            </div>
                                        </div>

                                        <div style="padding: 8px 12px; font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase;">
                                            <span data-lang-key="menu.help"><?php echo __('menu.help'); ?></span>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'help/privacy') ? 'active' : ''; ?>" data-nav="help/privacy">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">privacy_tip</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="help.privacy.title"><?php echo __('help.privacy.title'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'help/terms') ? 'active' : ''; ?>" data-nav="help/terms">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">gavel</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="help.terms.title"><?php echo __('help.terms.title'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'help/cookies') ? 'active' : ''; ?>" data-nav="help/cookies">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">cookie</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="help.cookies.title"><?php echo __('help.cookies.title'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'help/feedback') ? 'active' : ''; ?>" data-nav="help/feedback">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">feedback</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="help.feedback.title"><?php echo __('help.feedback.title'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="nav-admin" class="menu-list <?php echo !$isAdminSection ? 'disabled' : ''; ?>">
                                        <div class="menu-link menu-link-back" data-nav="main">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">arrow_back</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="global.back_home"><?php echo __('global.back_home'); ?></span>
                                            </div>
                                        </div>

                                        <div style="padding: 8px 12px; font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase;">
                                            <span data-lang-key="menu.admin_panel"><?php echo __('menu.admin_panel'); ?></span>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'admin/dashboard') ? 'active' : ''; ?>" data-nav="admin/dashboard">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">dashboard</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="admin.dashboard.title"><?php echo __('admin.dashboard.title'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'admin/users') ? 'active' : ''; ?>" data-nav="admin/users">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">group</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="admin.users.title"><?php echo __('admin.users.title'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                <div class="menu-content-bottom">
                                    <div id="nav-admin-bottom" class="menu-list <?php echo !$isAdminSection ? 'disabled' : ''; ?>">
                                         <div class="menu-link <?php echo ($currentSection === 'admin/server') ? 'active' : ''; ?>" data-nav="admin/server">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">dns</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span data-lang-key="admin.server.title"><?php echo __('admin.server.title'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="general-content-scrolleable overflow-y" data-container="main-section">
                        <?php
                        $fileToLoad = $sectionMap['404']; 
                        
                        if (array_key_exists($currentSection, $sectionMap)) {
                            $fileToLoad = $sectionMap[$currentSection];
                        } 

                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            echo "<h1>" . __('global.critical_error_404') . "</h1><p>" . __('global.critical_error_404_desc') . "</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="<?php echo $basePath; ?>assets/js/core/app-init.js"></script>

</body>

</html>