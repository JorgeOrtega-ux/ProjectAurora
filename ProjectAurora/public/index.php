<?php
require_once __DIR__ . '/../config/router.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/utilities.php';

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            // Usuario encontrado: Actualizamos datos
            $_SESSION['user_role'] = $freshUser['role'] ?? 'user';
            $_SESSION['user_avatar'] = $freshUser['avatar'];
        } else {
            // [NUEVO] Usuario NO encontrado (ej. BD reseteada): Forzar cierre de sesión
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {
        // Error de BD: No hacemos nada para no bloquear, pero podrías loguearlo
    }
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