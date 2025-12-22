<?php
// public/index.php
session_start();

// Cargar Router y DB
require_once __DIR__ . '/../config/routers/router.php';
// ACTUALIZADO: Ruta corregida a config/database/db.php
require_once __DIR__ . '/../config/database/db.php'; 

// === CONTROL DE ACCESO (MIDDLEWARE) ===
$isLoggedIn = isset($_SESSION['user_id']);

// Lista de rutas que NO requieren login
$publicRoutes = ['login', 'register'];

if (!$isLoggedIn) {
    // Si NO está logueado y trata de acceder a una ruta privada -> Mandar a Login
    if (!in_array($currentSection, $publicRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    // Si SÍ está logueado y trata de acceder a Login/Registro -> Mandar a Home
    if (in_array($currentSection, $publicRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

// === PREPARAR AVATAR PARA LA VISTA ===
$userAvatarCss = '';
if ($isLoggedIn && !empty($_SESSION['avatar'])) {
    // Leemos el archivo y lo convertimos a base64
    $avatarFile = __DIR__ . '/../' . $_SESSION['avatar'];
    if (file_exists($avatarFile)) {
        $type = pathinfo($avatarFile, PATHINFO_EXTENSION);
        $data = file_get_contents($avatarFile);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        $userAvatarCss = "background-image: url('{$base64}'); background-size: cover; background-position: center;";
    } else {
        // Fallback color si no encuentra el archivo
        $userAvatarCss = "background-color: #000;"; 
    }
}

// 2. Definir rutas físicas
$routesMap = require __DIR__ . '/../config/routes.php';
$fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Aurora Alpha</title>
    
    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
    </script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/styles.css">
    
    <script type="module" src="<?php echo $basePath; ?>public/assets/js/app-init.js"></script>
</head>
<body>

    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                
                <?php if ($isLoggedIn): ?>
                <div class="general-content-top">
                    <?php 
                        // Pasamos la variable de estilo al header
                        $avatarStyle = $userAvatarCss; 
                        include __DIR__ . '/../includes/layouts/header.php'; 
                    ?>
                </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <?php if ($isLoggedIn): ?>
                        <?php include __DIR__ . '/../includes/modules/module-surface.php'; ?>
                    <?php endif; ?>
                    
                    <div class="general-content-scrolleable" data-container="main-section">
                        <?php 
                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            echo "Error: Archivo base no encontrado.";
                        }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>