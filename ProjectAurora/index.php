<?php
// Incluye el router al principio de todo
require_once 'router.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <title>Project Aurora</title>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                
                <div class="loader-wrapper">
                    <div class="loader-spinner"></div>
                </div>

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
                                <button class="header-button" onclick="navigateTo('main')">Main</button>
                                <button class="header-button" onclick="navigateTo('login')">Login</button>
                                <button class="header-button" onclick="navigateTo('register')">Register</button>
                                
                                <div class="header-button profile-button">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="general-content-bottom">
                    <div class="general-content-scrolleable" id="section-container">
                        
                        <?php
                            // Carga SÓLO la sección inicial determinada por el router
                            // Nos aseguramos de que el archivo exista antes de incluirlo
                            $sectionFile = "includes/sections/{$CURRENT_SECTION}.php"; // <-- RUTA CORREGIDA
                            if (file_exists($sectionFile)) {
                                include $sectionFile;
                            } else {
                                // Si no existe, carga 'main' como fallback
                                include "includes/sections/main.php"; // <-- RUTA CORREGIDA
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