<?php
// public/index.php

// 1. CONFIGURACIÓN
define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');
$basePath = '/ProjectAurora/'; // Ajusta según tu ruta real

// 2. DETECTAR SECCIÓN ACTUAL
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace($basePath, '', $requestUri);
$currentSection = ($path === '' || $path === '/') ? 'main' : $path;
$currentSection = trim($currentSection, '/');
if ($currentSection === '') $currentSection = 'main';

// 3. CONTEXTO
$helpSections = ['help', 'privacy', 'terms', 'cookies', 'feedback'];
$settingsSections = ['settings/preferences', 'settings/accessibility'];

$isHelp = in_array($currentSection, $helpSections);
$isSettings = in_array($currentSection, $settingsSections);
$context = $isHelp ? 'help' : ($isSettings ? 'settings' : 'app');

// 4. CARGAR CONTENIDO
$routes = require CONFIG_PATH . '/routes.php';
$fileToLoad = $routes[$currentSection] ?? $routes['404'];

// ==========================================
// 5. DETECCIÓN PREFERENCIAS (SERVER-SIDE)
// ==========================================
$cookies = json_decode($_COOKIE['project_aurora_prefs'] ?? '{}', true);
$themePref = $cookies['theme'] ?? 'sync';

// Calcular tema inicial para renderizar <body> con la clase correcta
$bodyClass = '';
$htmlDataTheme = 'light'; // default

if ($themePref === 'dark') {
    $htmlDataTheme = 'dark';
    $bodyClass = 'theme-dark';
} elseif ($themePref === 'light') {
    $htmlDataTheme = 'light';
    $bodyClass = 'theme-light';
} else {
    // Si es 'sync', PHP no sabe si el sistema es dark o light.
    // Opción A: Renderizar default y dejar que JS ajuste rápido.
    // Opción B: Usar media queries en CSS puro para evitar FOUC.
    // Por defecto lo dejaremos limpio para que el CSS 'prefers-color-scheme' actúe.
    $htmlDataTheme = 'sync'; 
}

// 6. BUFFER DE SALIDA
ob_start();
if (file_exists($fileToLoad)) include $fileToLoad;
else echo "<div style='padding:40px;'><h1>404</h1></div>";
$contentHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $htmlDataTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Aurora</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/components.css">
    
    <script>
        (function() {
            const savedTheme = '<?php echo $themePref; ?>';
            if (savedTheme === 'sync') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            }
        })();
    </script>

    <script> window.BASE_PATH = '<?php echo $basePath; ?>'; </script>
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
                                    <div class="search-input"><input type="text" placeholder="Buscar..."></div>
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
                                            <div class="menu-link-text"><span>Configuración</span></div>
                                        </div>
                                        <div class="menu-link" data-nav="help">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                                            <div class="menu-link-text"><span>Ayuda</span></div>
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
                        <div id="menu-content-app" class="menu-content" style="display: flex;">
                            <div class="menu-content-top">
                                <div class="menu-list">
                                    <div class="menu-link <?php echo $currentSection === 'main' ? 'active' : ''; ?>" data-nav="main">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">home</span></div>
                                        <div class="menu-link-text"><span>Página principal</span></div>
                                    </div>
                                    <div class="menu-link <?php echo $currentSection === 'trends' ? 'active' : ''; ?>" data-nav="trends">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">trending_up</span></div>
                                        <div class="menu-link-text"><span>Explorar tendencias</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="menu-content-bottom"></div>
                        </div>

                        <?php elseif ($context === 'settings'): ?>
                        <div id="menu-content-settings" class="menu-content" style="display: flex;">
                            <div class="menu-content-top">
                                <div class="menu-list">
                                    <div class="menu-link" data-nav="main" style="margin-bottom: 8px; border-bottom: 1px solid #eee;">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                                        <div class="menu-link-text"><span>Volver al inicio</span></div>
                                    </div>
                                    <div class="menu-link <?php echo $currentSection === 'settings/preferences' ? 'active' : ''; ?>" data-nav="settings/preferences">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">tune</span></div>
                                        <div class="menu-link-text"><span>Preferencias</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="menu-content-bottom"></div>
                        </div>

                        <?php elseif ($context === 'help'): ?>
                        <div id="menu-content-help" class="menu-content" style="display: flex;">
                            <div class="menu-content-top">
                                <div class="menu-list">
                                    <div class="menu-link" data-nav="main" style="margin-bottom: 8px; border-bottom: 1px solid #eee;">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                                        <div class="menu-link-text"><span>Volver al inicio</span></div>
                                    </div>
                                    <div class="menu-link <?php echo $currentSection === 'help' ? 'active' : ''; ?>" data-nav="help">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">help_center</span></div>
                                        <div class="menu-link-text"><span>Centro de Ayuda</span></div>
                                    </div>
                                    <div class="menu-link <?php echo $currentSection === 'privacy' ? 'active' : ''; ?>" data-nav="privacy">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">lock</span></div>
                                        <div class="menu-link-text"><span>Privacidad</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="menu-content-bottom"></div>
                        </div>
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