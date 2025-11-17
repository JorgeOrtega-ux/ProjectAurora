<?php
// Incluye el router al principio de todo
require_once 'config/router.php';
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
                        <div class="header">
                            <div class="header-left">
                                <div class="header-item">
                                    <div class="header-button">
                                        <span class="material-symbols-rounded">menu</span>
                                    </div>
                                </div>
                            </div>
                            <div class="header-right">
                                <div class="header-item">
                                    <div class="header-button profile-button"></div>
                                </div>
                            </div>
                            <div class="popover-module popover-profile disabled">
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
                                                <span class="material-symbols-rounded">help</span>
                                            </div>
                                            <div class="menu-link-text">
                                                <span>Ayuda y comentarios</span>
                                            </div>
                                        </div>
                                        <div class="menu-link menu-link-logout">
                                            <div class="menu-link-icon">
                                                <span class="material-symbols-rounded">logout</span>
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

                <?php endif; ?>

                <div class="general-content-bottom">
                    <div class="module-content module-surface disabled">
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
</body>

</html>