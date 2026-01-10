<?php
// public/index.php

// 1. CONFIGURACIÓN BÁSICA
define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');

// Ajusta esto si tu proyecto está en otra carpeta. 
// Ejemplo: si es localhost/ProjectAurora, usa '/ProjectAurora/'
$basePath = '/ProjectAurora/'; 

// 2. DETECTAR SECCIÓN ACTUAL
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace($basePath, '', $requestUri);
$currentSection = ($path === '' || $path === '/') ? 'main' : $path;
$currentSection = trim($currentSection, '/');
if ($currentSection === '') $currentSection = 'main';

// 3. LÓGICA DE CONTEXTO
$helpSections = ['help', 'privacy', 'terms', 'cookies', 'feedback'];
$settingsSections = ['settings/accessibility', 'settings/preferences'];

$isHelpContext = in_array($currentSection, $helpSections);
$isSettingsContext = in_array($currentSection, $settingsSections);
$isAppContext = !$isHelpContext && !$isSettingsContext;

// 4. CARGAR EL CONTENIDO (SSR)
$routes = require CONFIG_PATH . '/routes.php';
$fileToLoad = $routes[$currentSection] ?? $routes['404'];

ob_start();
if (file_exists($fileToLoad)) {
    include $fileToLoad;
} else {
    echo "<div style='padding:40px; text-align:center;'>
            <h1>404</h1>
            <p>Sección no encontrada: " . htmlspecialchars($currentSection) . "</p>
          </div>";
}
$contentHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Aurora</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/components.css">
    
    <script>
        // Definimos la ruta base para que JS también la use
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.HELP_SECTIONS = <?php echo json_encode($helpSections); ?>;
        window.SETTINGS_SECTIONS = <?php echo json_encode($settingsSections); ?>;
    </script>
</head>

<body>
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
                                    <div class="search-icon">
                                        <span class="material-symbols-rounded">search</span>
                                    </div>
                                    <div class="search-input">
                                        <input type="text" placeholder="Buscar...">
                                    </div>
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
                                            <div class="menu-link-text"><span>Preferencias</span></div>
                                        </div>
                                        <div class="menu-link" data-nav="settings/accessibility"> 
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">accessibility_new</span></div>
                                            <div class="menu-link-text"><span>Accesibilidad</span></div>
                                        </div>
                                        <div class="menu-link" data-nav="help">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                                            <div class="menu-link-text"><span>Ayuda y comentarios</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="general-content-bottom">
                    <div class="module-content module-surface disabled"> 
                        <div class="menu-content">
                            <div class="menu-content-top">
                                <div class="menu-list">
                                    
                                    <div id="nav-group-app" style="<?php echo $isAppContext ? '' : 'display:none;'; ?>">
                                        <div class="menu-link <?php echo $currentSection === 'main' ? 'active' : ''; ?>" data-nav="main">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">home</span></div>
                                            <div class="menu-link-text"><span>Página principal</span></div>
                                        </div>
                                        <div class="menu-link <?php echo $currentSection === 'trends' ? 'active' : ''; ?>" data-nav="trends">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">trending_up</span></div>
                                            <div class="menu-link-text"><span>Explorar tendencias</span></div>
                                        </div>
                                    </div>

                                    <div id="nav-group-settings" style="<?php echo $isSettingsContext ? '' : 'display:none;'; ?>">
                                        <div class="menu-link" data-nav="main" style="margin-bottom: 8px; border-bottom: 1px solid #eee;">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                                            <div class="menu-link-text"><span>Volver al inicio</span></div>
                                        </div>
                                        <div class="menu-link <?php echo $currentSection === 'settings/preferences' ? 'active' : ''; ?>" data-nav="settings/preferences">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">tune</span></div>
                                            <div class="menu-link-text"><span>Preferencias</span></div>
                                        </div>
                                        <div class="menu-link <?php echo $currentSection === 'settings/accessibility' ? 'active' : ''; ?>" data-nav="settings/accessibility">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">accessibility_new</span></div>
                                            <div class="menu-link-text"><span>Accesibilidad</span></div>
                                        </div>
                                    </div>

                                    <div id="nav-group-help" style="<?php echo $isHelpContext ? '' : 'display:none;'; ?>">
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
                            </div>
                            <div class="menu-content-bottom"></div>
                        </div>
                    </div>

                    <div class="general-content-scrolleable" id="app-content">
                        <?php echo $contentHtml; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>
</body>
</html>