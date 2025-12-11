<?php
// public/index.php

// 1. Cargar el Router (Configuración y Sesión)
require_once __DIR__ . '/../config/routers/router.php';

// 2. Definir el mapa de rutas FÍSICAS
$sectionMap = require __DIR__ . '/../config/routes.php';

$isSettingsSection = (strpos($currentSection, 'settings/') === 0);

// --- MODIFICADO: Lógica Inicial del Tema ---
$initialThemeClass = 'light-theme'; // Default fallback
$userThemePref = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'system';
$userExtendedAlerts = isset($_SESSION['extended_alerts']) ? (int)$_SESSION['extended_alerts'] : 0;

if ($userThemePref === 'dark') {
    $initialThemeClass = 'dark-theme';
} elseif ($userThemePref === 'light') {
    $initialThemeClass = 'light-theme';
} else {
    // Si es system, PHP no sabe la preferencia del OS, así que dejamos una por defecto
    // y JS lo corregirá inmediatamente, o usamos un script inline pequeño.
    // Dejaremos 'light-theme' como base, pero agregamos 'system-theme' para que JS sepa.
    $initialThemeClass = 'light-theme system-theme-pending';
}
?>
<!DOCTYPE html>
<html lang="<?php echo isset($userLang) ? $userLang : 'es'; ?>" class="<?php echo $initialThemeClass; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">

    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.TRANSLATIONS = <?php echo json_encode($GLOBALS['AURORA_TRANSLATIONS']); ?>;
        
        // MODIFICADO: Pasamos las preferencias al frontend
        window.USER_PREFS = {
            theme: '<?php echo $userThemePref; ?>',
            extended_alerts: <?php echo $userExtendedAlerts; ?>
        };

        window.t = function(key) {
            return window.TRANSLATIONS[key] || key;
        };
    </script>
    
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/components.css">

    <title><?php echo __('app.name'); ?></title>

    <?php if (!$isLoggedIn): ?>
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

                <?php if ($isLoggedIn): ?>
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

                                        <?php
                                        $hasImage = false;
                                        $avatarSrc = '';
                                        if (isset($_SESSION['uuid'])) {
                                            $uuid = $_SESSION['uuid'];
                                            $relCustom  = 'assets/uploads/avatars/custom/' . $uuid . '.png';
                                            $relDefault = 'assets/uploads/avatars/default/' . $uuid . '.png';

                                            if (file_exists(__DIR__ . '/' . $relCustom)) {
                                                $avatarSrc = $basePath . $relCustom . '?v=' . microtime(true);
                                                $hasImage = true;
                                            } elseif (file_exists(__DIR__ . '/' . $relDefault)) {
                                                $avatarSrc = $basePath . $relDefault . '?v=' . microtime(true);
                                                $hasImage = true;
                                            }
                                        }
                                        ?>

                                        <?php if ($hasImage): ?>
                                            <img src="<?php echo $avatarSrc; ?>" alt="Perfil" class="profile-img">
                                        <?php else: ?>
                                            <span style="font-weight:bold; color:#555; position: relative; z-index: 1;">
                                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                </div>

                                <div class="module-content module-profile body-text disabled" data-module="moduleProfile">
                                    <div class="menu-content">
                                        <div class="pill-container">
                                            <div class="drag-handle"></div>
                                        </div>
                                        <div class="menu-list">
                                            <div class="menu-link" data-nav="settings/your-profile">
                                                <div class="menu-link-icon">
                                                    <span class="material-symbols-rounded">settings</span>
                                                </div>
                                                <div class="menu-link-text">
                                                    <span data-lang-key="menu.settings"><?php echo __('menu.settings'); ?></span>
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

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="general-content-bottom">

                    <?php if ($isLoggedIn): ?>
                        <div class="module-content module-surface body-text disabled" data-module="moduleSurface">
                            <div class="menu-content">
                                <div class="menu-content-top">

                                    <div id="nav-main" class="menu-list <?php echo $isSettingsSection ? 'disabled' : ''; ?>">
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
                                        <div class="menu-link" data-nav="main" style="border-bottom: 1px solid #eee; margin-bottom: 8px;">
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

                                </div>
                                <div class="menu-content-bottom"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="general-content-scrolleable" data-container="main-section">
                        <?php
                        // LÓGICA DE CARGA
                        $fileToLoad = $sectionMap['404']; 
                        
                        if (array_key_exists($currentSection, $sectionMap)) {
                            $fileToLoad = $sectionMap[$currentSection];
                        } 

                        // Verificar existencia física final
                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            echo "<h1>Error 404 Crítico</h1><p>No se encuentra el archivo solicitado ni la página de error.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>

</body>

</html>