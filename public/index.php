<?php
// --- CONFIGURACIÓN DE RUTAS (ROUTER) ---

// Definimos la ruta base del proyecto
$basePath = '/ProjectAurora/'; 

// 1. Obtener la URL solicitada
$requestUri = $_SERVER['REQUEST_URI'];

// 2. Limpiar la URL para obtener solo la "sección"
// Si la URL empieza con la base, la quitamos
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

// Quitamos parámetros GET (?id=1) y barras finales
$path = strtok($path, '?');
$currentSection = rtrim($path, '/');

// 3. Definir rutas válidas
// Si está vacío es la home ('main')
if ($currentSection === '') {
    $currentSection = 'main';
}

$validRoutes = ['main', 'explorer'];

// Si la sección no existe, forzamos 404
if (!in_array($currentSection, $validRoutes)) {
    $currentSection = '404';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <script>window.BASE_PATH = '<?php echo $basePath; ?>';</script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <title>ProjectAurora</title>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
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
                                        <input type="text" placeholder="Buscar...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="header-right">
                            <div class="header-item">
                                <div class="header-button search-toggle-btn" id="searchToggleBtn">
                                    <span class="material-symbols-rounded">search</span>
                                </div>

                                <div class="header-button profile-button" data-action="toggleModuleProfile">
                                    </div>
                            </div>
                            
                            <div class="module-content module-profile disabled" data-module="moduleProfile">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <div class="menu-link">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">settings</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span>Configuración</span>
                                            </div>
                                        </div>
                                        <div class="menu-link">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">close</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span>Cerrar sesión</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                
                <div class="general-content-bottom">
                    <div class="module-content module-surface disabled" data-module="moduleSurface">
                        <div class="menu-content">
                            <div class="menu-content-top">
                                <div class="menu-list">
                                    <div class="menu-link <?php echo ($currentSection === 'main') ? 'active' : ''; ?>" data-nav="main">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">home</span>
                                        </div>
                                        <div class="menu-link-text">
                                            <span>Página principal</span>
                                        </div>
                                    </div>
                                    
                                    <div class="menu-link <?php echo ($currentSection === 'explorer') ? 'active' : ''; ?>" data-nav="explorer">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">explore</span>
                                        </div>
                                        <div class="menu-link-text">
                                            <span>Explorar colecciones</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="menu-content-bottom"></div>
                        </div>
                    </div>

                    <div class="general-content-scrolleable" data-container="main-section">
                        <?php
                        // CARGA INICIAL DESDE EL SERVIDOR (Server-Side Rendering)
                        // Esto asegura que si entras directo a /explorer, veas el contenido sin esperar al JS.
                        
                        $file = __DIR__ . '/../includes/sections/' . $currentSection . '.php';
                        
                        if (file_exists($file)) {
                            include $file;
                        } else {
                            // Fallback de seguridad si el archivo físico no existe
                            include __DIR__ . '/../includes/sections/404.php';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>

</html>