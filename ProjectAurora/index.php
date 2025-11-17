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
                // Solo muestra el header si $showNavigation (del router) es 'true'
                if ($showNavigation):
                ?>

                    <div class="general-content-top">
                        <div class="header">
                            <div class="header-left">
                                <div class="header-item">
                                    <div class="header-button">
                                        <span class="material-symbols-rounded">
                                            menu
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="header-right">
                                <div class="header-item">

                                    <div class="header-button profile-button"></div>
                                </div>

                            </div>
                            <div class="popover-module popover-profile">
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
                                        <div class="menu-link">
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

                <?php
                endif; // Fin del 'if ($showNavigation)'
                ?>

                <div class="general-content-bottom">
                    <div class="general-content-scrolleable" id="section-container">

                        <?php
                        // Carga SÓLO la sección inicial determinada por el router
                        // Nos aseguramos de que el archivo exista antes de incluirlo
                        $sectionFile = "includes/sections/{$CURRENT_SECTION}.php";
                        if (file_exists($sectionFile)) {
                            include $sectionFile;
                        } else {
                            // Si no existe, carga 'main' como fallback
                            include "includes/sections/main.php";
                        }
                        ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/url-manager.js"></script>
</body>

</html>