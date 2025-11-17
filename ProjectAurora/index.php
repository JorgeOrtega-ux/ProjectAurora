<?php
require_once 'config/router.php';
require_once 'config/database.php';

// Refresco de datos de sesión
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            $_SESSION['user_role'] = $freshUser['role'] ?? 'user';
            $_SESSION['user_avatar'] = $freshUser['avatar'];
        }
    } catch (Exception $e) {
        // Fallo silencioso
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Definimos la variable JS global para el path (los módulos pueden acceder a esto) -->
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
                        <?php include 'includes/layouts/header.php'; ?>
                    </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <!-- Menú Lateral -->
                    <div class="module-content module-surface disabled" data-module="moduleSurface">
                        <div class="menu-content">
                            <div class="menu-list">
                                <!-- 
                                  NOTA: Se ha quitado el onclick="...".
                                  Ahora url-manager.js lo manejará usando 'data-nav'.
                                -->
                                <div class="menu-link <?php echo ($CURRENT_SECTION === 'main') ? 'active' : ''; ?>"
                                    data-nav="main">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">home</span>
                                    </div>
                                    <div class="menu-link-text">
                                        <span>Página principal</span>
                                    </div>
                                </div>

                                <div class="menu-link <?php echo ($CURRENT_SECTION === 'explorer') ? 'active' : ''; ?>"
                                    data-nav="explorer">
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

                    <!-- Contenedor de Secciones -->
                    <div class="general-content-scrolleable" id="section-container">
                        <?php
                        $sectionFile = "includes/sections/{$CURRENT_SECTION}.php";
                        if (file_exists($sectionFile)) {
                            include $sectionFile;
                        } else {
                            include "includes/sections/404.php"; 
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 
      ¡TADA! Solo un script. 
      'type="module"' es la magia que permite usar 'import' y 'export'.
    -->
    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>
</body>
</html>