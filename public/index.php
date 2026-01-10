<?php
// public/index.php

// 1. CONFIGURACIÓN
define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');
$basePath = '/ProjectAurora/'; // Ajusta según tu entorno

// 2. BOOTSTRAP (Carga Idioma y Preferencias)
require_once PROJECT_ROOT . '/includes/core/boot.php';

// 3. DETECTAR SECCIÓN ACTUAL
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace($basePath, '', $requestUri);
$currentSection = ($path === '' || $path === '/') ? 'main' : $path;
$currentSection = trim($currentSection, '/');
if ($currentSection === '') $currentSection = 'main';

// 4. CONTEXTO
$helpSections = ['help', 'privacy', 'terms', 'cookies', 'feedback'];
$settingsSections = ['settings/preferences', 'settings/accessibility'];

$isHelp = in_array($currentSection, $helpSections);
$isSettings = in_array($currentSection, $settingsSections);
$context = $isHelp ? 'help' : ($isSettings ? 'settings' : 'app');

// 5. CARGAR RUTAS
$routes = require CONFIG_PATH . '/routes.php';
$fileToLoad = $routes[$currentSection] ?? $routes['404'];

// 6. LÓGICA DE TEMA (Para el <body> inicial)
// $currentTheme viene de boot.php
$bodyClass = '';
$htmlDataTheme = 'light'; 

if ($currentTheme === 'dark') {
    $htmlDataTheme = 'dark';
    $bodyClass = 'theme-dark';
} elseif ($currentTheme === 'light') {
    $htmlDataTheme = 'light';
    $bodyClass = 'theme-light';
} else {
    $htmlDataTheme = 'sync'; 
}

// 7. BUFFER DE SALIDA
ob_start();
if (file_exists($fileToLoad)) include $fileToLoad;
else echo "<div style='padding:40px;'><h1>404</h1></div>";
$contentHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>" data-theme="<?php echo $htmlDataTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('app.title'); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/components.css">
    
    <script>
        (function() {
            const savedTheme = '<?php echo $currentTheme; ?>';
            if (savedTheme === 'sync') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            }
        })();
        window.BASE_PATH = '<?php echo $basePath; ?>'; 
    </script>
</head>
<body class="<?php echo $bodyClass; ?>">
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                
                <div class="general-content-top">
                    <div class="header">
                        <div class="header-left">
                            <div class="header-item">
                                <div class="header-button" data-action="toggleModuleSurface">
                                    <span class="material-symbols-rounded">menu</span>
                                </div>
                            </div>
                        </div>
                        <div class="header-center">
                            <div class="search-wrapper">
                                <div class="search-content">
                                    <div class="search-icon"><span class="material-symbols-rounded">search</span></div>
                                    <div class="search-input"><input type="text" placeholder="<?php echo __('search.placeholder'); ?>"></div>
                                </div>
                            </div>
                        </div>
                        <div class="header-right">
                            <div class="header-item">
                                <div class="header-button" data-action="toggleModuleOptions">
                                    <span class="material-symbols-rounded">more_vert</span>
                                </div>
                            </div>
                            <div class="module-content module-options disabled">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <div class="menu-link" data-nav="settings/preferences"> 
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">settings</span></div>
                                            <div class="menu-link-text"><span><?php echo __('menu.settings'); ?></span></div>
                                        </div>
                                        <div class="menu-link" data-nav="help">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                                            <div class="menu-link-text"><span><?php echo __('menu.help'); ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="general-content-bottom">
                    <div class="module-content module-surface disabled"> 
                        
                        <?php if ($context === 'app'): ?>
                             <?php include PROJECT_ROOT . '/includes/menus/app.php'; ?>

                        <?php elseif ($context === 'settings'): ?>
                             <?php include PROJECT_ROOT . '/includes/menus/settings.php'; ?>

                        <?php elseif ($context === 'help'): ?>
                             <?php include PROJECT_ROOT . '/includes/menus/help.php'; ?>
                        <?php endif; ?>

                    </div>

                    <div class="general-content-scrolleable overflow-y" id="app-content">
                        <?php echo $contentHtml; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>
</body>
</html>