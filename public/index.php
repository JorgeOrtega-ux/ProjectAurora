<?php
// public/index.php

// 1. Cargar el Router (Configuración y Sesión)
require_once __DIR__ . '/../config/routers/router.php';

// 2. Definir el mapa de rutas FÍSICAS (Igual que en loader.php)
// Esto conecta la URL "virtual" con el archivo PHP real.
$sectionMap = [
    'main'     => __DIR__ . '/../includes/sections/app/main.php',
    'explorer' => __DIR__ . '/../includes/sections/app/explorer.php',
    '404'      => __DIR__ . '/../includes/sections/system/404.php',
    
    // Rutas de Settings
    'settings/your-profile'       => __DIR__ . '/../includes/sections/settings/your-profile.php',
    'settings/login-and-security' => __DIR__ . '/../includes/sections/settings/login-security.php',
    'settings/accessibility'      => __DIR__ . '/../includes/sections/settings/accessibility.php',
    
    // Rutas de Registro
    'register'                => __DIR__ . '/../includes/sections/auth/register.php',
    'register/aditional-data' => __DIR__ . '/../includes/sections/auth/register.php',
    'register/verify'         => __DIR__ . '/../includes/sections/auth/register.php',
];

$isSettingsSection = (strpos($currentSection, 'settings/') === 0);
?>
<!DOCTYPE html>
<html lang="<?php echo isset($userLang) ? $userLang : 'es'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">

    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.TRANSLATIONS = <?php echo json_encode($GLOBALS['AURORA_TRANSLATIONS']); ?>;
        
        window.t = function(key) {
            return window.TRANSLATIONS[key] || key;
        };
    </script>
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
                                <div class="header-button" data-action="toggleModuleSurface">
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
                                            <input type="text" placeholder="<?php echo __('global.search_placeholder'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="header-right">
                                <div class="header-item">
                                    <div class="header-button profile-button"
                                        data-action="toggleModuleProfile"
                                        data-role="<?php echo htmlspecialchars($userRole); ?>">

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
                                        <div class="menu-list">
                                            <div class="menu-link" data-nav="settings/your-profile">
                                                <div class="menu-link-icon">
                                                    <span class="material-symbols-rounded">settings</span>
                                                </div>
                                                <div class="menu-link-text">
                                                    <span><?php echo __('menu.settings'); ?></span>
                                                </div>
                                            </div>

                                            <div class="menu-link" id="btn-logout">
                                                <div class="menu-link-icon">
                                                    <span class="material-symbols-rounded">logout</span>
                                                </div>
                                                <div class="menu-link-text">
                                                    <span><?php echo __('menu.logout'); ?></span>
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
                                                <span><?php echo __('menu.home'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'explorer') ? 'active' : ''; ?>" data-nav="explorer">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">explore</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span><?php echo __('menu.explore'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="nav-settings" class="menu-list <?php echo !$isSettingsSection ? 'disabled' : ''; ?>">
                                        <div class="menu-link" data-nav="main" style="border-bottom: 1px solid #eee; margin-bottom: 8px;">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">arrow_back</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span><?php echo __('global.back_home'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'settings/your-profile') ? 'active' : ''; ?>" data-nav="settings/your-profile">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">account_circle</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span><?php echo __('menu.profile'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'settings/login-and-security') ? 'active' : ''; ?>" data-nav="settings/login-and-security">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">lock</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span><?php echo __('menu.security'); ?></span>
                                            </div>
                                        </div>

                                        <div class="menu-link <?php echo ($currentSection === 'settings/accessibility') ? 'active' : ''; ?>" data-nav="settings/accessibility">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">accessibility_new</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span><?php echo __('menu.accessibility'); ?></span>
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
                        // LÓGICA DE CARGA CORREGIDA
                        // Usamos el mapa $sectionMap definido arriba.
                        
                        $fileToLoad = $sectionMap['404']; // Por defecto 404
                        
                        if (array_key_exists($currentSection, $sectionMap)) {
                            $fileToLoad = $sectionMap[$currentSection];
                        } else {
                            // Fallback para intentar cargar archivos genéricos si no están en el mapa
                            // pero con cuidado de no romper las rutas nuevas
                            $potentialFile = __DIR__ . '/../includes/sections/' . $currentSection . '.php';
                            if (file_exists($potentialFile)) {
                                $fileToLoad = $potentialFile;
                            }
                        }

                        // Verificar existencia física final
                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            // Si falla incluso el 404 del mapa
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