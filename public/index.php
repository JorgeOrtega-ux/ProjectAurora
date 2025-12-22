<?php
// public/index.php
session_start();

// Cargar Router y DB
require_once __DIR__ . '/../config/routers/router.php';
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

// === PREPARAR VARIABLES DE HEADER (REPLICANDO PROJECT TEST) ===
$globalAvatarSrc = '';
$userRole = 'guest';

if ($isLoggedIn) {
    // Definir rol (simulado o desde sesión si existe)
    $userRole = $_SESSION['role'] ?? 'user';

    // Obtener imagen de perfil (Base64 para mantener compatibilidad con almacenamiento local fuera de public)
    if (!empty($_SESSION['avatar'])) {
        $avatarFile = __DIR__ . '/../' . $_SESSION['avatar'];

        if (file_exists($avatarFile)) {
            // Detectamos el tipo real del contenido (ej: image/svg+xml o image/png)
            $mimeType = mime_content_type($avatarFile);
            $data = file_get_contents($avatarFile);

            // Usamos el tipo detectado en lugar de la extensión
            $globalAvatarSrc = 'data:' . $mimeType . ';base64,' . base64_encode($data);
        }
    }

    // Fallback: Si no hay imagen, usar un placeholder base64 simple o un servicio externo
    if (empty($globalAvatarSrc)) {
        // Generar un placeholder visual si no hay imagen
        $name = $_SESSION['username'] ?? 'User';
        $globalAvatarSrc = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
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
                        // El header ahora usará $globalAvatarSrc y $userRole directamente
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