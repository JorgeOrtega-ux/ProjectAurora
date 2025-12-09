<?php
// --- CONFIGURACIÓN DE RUTAS (ROUTER) ---
$basePath = '/ProjectAurora/'; 

// INCLUIR CONEXIÓN Y LOGIC DE AUTH
require_once __DIR__ . '/../includes/db.php';

// 1. Obtener la URL solicitada
$requestUri = $_SERVER['REQUEST_URI'];

// 2. Limpiar la URL
if (strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}
$path = strtok($path, '?');
$currentSection = rtrim($path, '/');
if ($currentSection === '') { $currentSection = 'main'; }

// --- CONTROL DE SESIÓN ---
$isLoggedIn = isset($_SESSION['user_id']);

// Rutas permitidas para invitados
$guestRoutes = ['login', 'register'];

// Lógica de Redirección Forzada
if (!$isLoggedIn) {
    // Si no está logueado y trata de entrar a algo que no es login/register, mandar al login
    if (!in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    // Si SI está logueado y trata de entrar al login, mandar al main
    if (in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

// Definir rutas válidas del sistema (APP)
$appRoutes = ['main', 'explorer'];
$validRoutes = array_merge($appRoutes, $guestRoutes);

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
    
    <?php 
    // ESTILO EXTRA PARA CENTRAR LOGIN/REGISTER
    // Como ahora están dentro del layout general, forzamos el centrado visualmente.
    if (!$isLoggedIn): 
    ?>
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

    <div class="page-wrapper">
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
                                    <span style="font-weight:bold; color:#555;">
                                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                    </span>
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
                                        <a href="?logout=true" class="menu-link">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">logout</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span>Cerrar sesión</span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="general-content-bottom">
                    
                    <?php if ($isLoggedIn): ?>
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
                    <?php endif; ?>

                    <div class="general-content-scrolleable" data-container="main-section">
                        <?php
                        $file = __DIR__ . '/../includes/sections/' . $currentSection . '.php';
                        if (file_exists($file)) { include $file; } 
                        else { include __DIR__ . '/../includes/sections/404.php'; }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>

</body>
</html>