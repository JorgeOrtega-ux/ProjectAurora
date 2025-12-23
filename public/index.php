<?php
// public/index.php
session_start();

// Cargar Router y DB
require_once __DIR__ . '/../config/routers/router.php';
require_once __DIR__ . '/../config/database/db.php';

// === GENERACIÓN DE TOKEN CSRF ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === CONTROL DE ACCESO (MIDDLEWARE) ===
$isLoggedIn = isset($_SESSION['user_id']);

$publicRoutes = [
    'login', 
    'register', 
    'register/aditional-data', 
    'register/verification-account',
    'recover-password',
    'reset-password'
];

if (!$isLoggedIn) {
    if (!in_array($currentSection, $publicRoutes)) {
        header("Location: " . $basePath . "login");
        exit;
    }
} else {
    if (in_array($currentSection, $publicRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

// === PREPARAR VARIABLES DE HEADER ===
$globalAvatarSrc = '';
$userRole = 'guest';

if ($isLoggedIn) {
    
    // =========================================================
    // REFRESCAR ROL, AVATAR Y EMAIL EN CADA RECARGA
    // =========================================================
    try {
        if (isset($pdo)) {
            // AHORA INCLUIMOS 'email' EN LA CONSULTA
            $stmt = $pdo->prepare("SELECT role, avatar_path, username, email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $freshUser = $stmt->fetch();

            if ($freshUser) {
                $_SESSION['role'] = $freshUser['role'];
                $_SESSION['avatar'] = $freshUser['avatar_path'];
                $_SESSION['username'] = $freshUser['username'];
                $_SESSION['email'] = $freshUser['email']; // <--- ACTUALIZAMOS EMAIL EN SESIÓN
            }
        }
    } catch (Exception $e) {
        error_log("Error al refrescar sesión: " . $e->getMessage());
    }
    // =========================================================

    $userRole = $_SESSION['role'] ?? 'user';

    if (!empty($_SESSION['avatar'])) {
        $avatarFile = __DIR__ . '/../' . $_SESSION['avatar'];
        if (file_exists($avatarFile)) {
            $mimeType = mime_content_type($avatarFile);
            $data = file_get_contents($avatarFile);
            $globalAvatarSrc = 'data:' . $mimeType . ';base64,' . base64_encode($data);
        }
    }

    if (empty($globalAvatarSrc)) {
        $name = $_SESSION['username'] ?? 'User';
        $globalAvatarSrc = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
    }
}

$routesMap = require __DIR__ . '/../config/routes.php';
$fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProjectAurora</title>

    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
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