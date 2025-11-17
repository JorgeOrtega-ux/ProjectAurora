<?php
// 1. Incluye el router (inicia sesión y define rutas)
require_once 'config/router.php';

// 2. Incluir base de datos para poder consultar
require_once 'config/database.php';

// --- LÓGICA DE REFRESCO DE SESIÓN (NUEVO) ---
// Si el usuario ya está logueado, consultamos la BD para obtener sus datos frescos.
// Así, si cambias el rol en la BD, al dar F5 se actualiza la visualización.
if (isset($_SESSION['user_id'])) {
    try {
        // Preparamos la consulta
        $stmt = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            // Sobrescribimos los datos de la sesión con los de la BD
            $_SESSION['user_role'] = $freshUser['role'] ?? 'user';
            $_SESSION['user_avatar'] = $freshUser['avatar'];
        }
    } catch (Exception $e) {
        // Si falla la BD, no hacemos nada y usamos los datos viejos de la sesión
        // para no romper la página.
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <title>Project Aurora</title>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">

                <?php
                // --- Lógica MPA Híbrida ---
                if ($showNavigation):
                ?>
                    <div class="general-content-top">
                        <?php include 'includes/layouts/header.php'; ?>              
                    </div>

                <?php endif; ?>

                <div class="general-content-bottom">
                    <div class="module-content module-surface disabled" data-module="moduleSurface">
                        <div class="menu-content">
                            <div class="menu-list">
                                <div class="menu-link active">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">home</span>
                                    </div>
                                    <div class="menu-link-text">
                                        <span>Pagina principal</span>
                                    </div>
                                </div>
                                <div class="menu-link">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">explore</span>
                                    </div>
                                    <div class="menu-link-text">
                                        <span>Explorar comunidades</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="general-content-scrolleable" id="section-container">
                        <?php
                        $sectionFile = "includes/sections/{$CURRENT_SECTION}.php";
                        if (file_exists($sectionFile)) {
                            include $sectionFile;
                        } else {
                            include "includes/sections/main.php";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo $basePath; ?>assets/js/url-manager.js"></script>
    <script src="<?php echo $basePath; ?>assets/js/auth-manager.js"></script>
    <script src="<?php echo $basePath; ?>assets/js/main-controller.js"></script>
</body>

</html>