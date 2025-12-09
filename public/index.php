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
    if (!in_array($currentSection, $guestRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
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

// --- CONFIGURACIÓN DE COLORES DE ROL ---
$roleBorderColor = '#00000020'; // Default (gris claro) para usuarios normales o fallback
if ($isLoggedIn && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'founder':
            $roleBorderColor = '#FFD700'; // Dorado
            break;
        case 'administrator':
            $roleBorderColor = '#FF4444'; // Rojo
            break;
        case 'moderator':
            $roleBorderColor = '#33B5E5'; // Azul
            break;
        case 'user':
        default:
            $roleBorderColor = '#00000020'; // El borde por defecto
            break;
    }
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

                                <div class="header-button profile-button" 
                                     data-action="toggleModuleProfile"
                                     style="border: 2px solid <?php echo $roleBorderColor; ?>;">
                                     
                                    <?php 
                                    // Verificar si tenemos UUID y si el archivo existe
                                    $hasAvatar = false;
                                    if (isset($_SESSION['uuid'])) {
                                        $avatarRelPath = 'assets/uploads/profile_pictures/' . $_SESSION['uuid'] . '.png';
                                        $avatarFullPath = __DIR__ . '/' . $avatarRelPath;
                                        if (file_exists($avatarFullPath)) {
                                            $hasAvatar = true;
                                        }
                                    }
                                    ?>

                                    <?php if ($hasAvatar): ?>
                                        <img src="<?php echo $basePath . $avatarRelPath; ?>" 
                                             alt="Perfil" 
                                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                    <?php else: ?>
                                        <span style="font-weight:bold; color:#555;">
                                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                            </div>
                            
                            <div class="module-content module-profile disabled" data-module="moduleProfile">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <div class="menu-link" style="cursor: default;">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">badge</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span style="text-transform: capitalize;"><?php echo $_SESSION['role'] ?? 'User'; ?></span>
                                            </div>
                                        </div>
                                        <div style="width:100%; height:1px; background:#00000010; margin: 4px 0;"></div>

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