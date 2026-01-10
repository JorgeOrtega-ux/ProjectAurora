<?php
// public/index.php

// 1. CONFIGURACIÓN BÁSICA
define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');
$basePath = '/'; // Ajusta esto si estás en una subcarpeta (ej: '/mi-web/')

// 2. DETECTAR SECCIÓN ACTUAL (Ruteo Simple)
// Si usas .htaccess, la URL vendrá limpia. Si no, usamos $_GET.
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace($basePath, '', $requestUri);
$currentSection = ($path === '' || $path === '/') ? 'main' : $path;
$currentSection = trim($currentSection, '/');
if ($currentSection === '') $currentSection = 'main';

// 3. CARGAR EL CONTENIDO (SSR)
$routes = require CONFIG_PATH . '/routes.php';
$fileToLoad = $routes[$currentSection] ?? $routes['404'];

// Buffer para capturar el HTML del contenido
ob_start();
if (file_exists($fileToLoad)) {
    include $fileToLoad;
} else {
    echo "<div style='padding:20px'><h1>404</h1><p>Sección no encontrada.</p></div>";
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
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">
    
    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
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
                                        <div class="menu-link" data-nav="settings"> <div class="menu-link-icon"><span class="material-symbols-rounded">settings</span></div>
                                            <div class="menu-link-text">
                                                <span>Configuración</span>
                                            </div>
                                        </div>
                                        <div class="menu-link">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                                            <div class="menu-link-text">
                                                <span>Ayuda y comentarios</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="general-content-bottom">
                    
                    <div class="module-content module-surface disabled"> <div class="menu-content">
                            <div class="menu-content-top">
                                <div class="menu-list">
                                    
                                    <div class="menu-link <?php echo $currentSection === 'main' ? 'active' : ''; ?>" data-nav="main">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">home</span></div>
                                        <div class="menu-link-text">
                                            <span>Página principal</span>
                                        </div>
                                    </div>

                                    <div class="menu-link <?php echo $currentSection === 'trends' ? 'active' : ''; ?>" data-nav="trends">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">folder</span></div>
                                        <div class="menu-link-text">
                                            <span>Explorar tendencias</span>
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

    <script type="module" src="assets/js/app-init.js"></script>
</body>
</html>