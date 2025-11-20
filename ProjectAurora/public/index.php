<?php
require_once __DIR__ . '/../config/router.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/utilities.php';

// [SEGURIDAD] Generar token WS solo si hay sesión activa
$jsUserId = 'null';
$wsToken = 'null';

if (isset($_SESSION['user_id'])) {
    $jsUserId = $_SESSION['user_id'];
    // Generamos el token que el JS usará para "loguearse" en el socket
    $token = generate_ws_auth_token($pdo, $jsUserId);
    $wsToken = "'$token'";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">

    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.USER_ID = <?php echo $jsUserId; ?>; 
        // [SEGURIDAD] Token para el WebSocket
        window.WS_TOKEN = <?php echo $wsToken; ?>;
    </script>

    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <title>Project Aurora</title>
</head>
<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                <?php if ($showNavigation): ?>
                    <div class="general-content-top">
                        <?php include __DIR__ . '/../includes/layouts/header.php'; ?>
                    </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <?php include __DIR__ . '/../includes/modules/module-surface.php'; ?>

                    <div class="general-content-scrolleable" data-container="main-section">
                        <?php
                        $sectionFile = __DIR__ . "/../includes/sections/{$SECTION_FILE_NAME}.php";
                        if (file_exists($sectionFile)) {
                            include $sectionFile;
                        } else {
                            include __DIR__ . "/../includes/sections/system/404.php";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>
</body>
</html>