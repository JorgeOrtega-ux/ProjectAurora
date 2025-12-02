<?php
require_once __DIR__ . '/../config/routes/router.php';
require_once __DIR__ . '/../config/core/database.php';
require_once __DIR__ . '/../config/helpers/utilities.php';
require_once __DIR__ . '/../includes/logic/i18n_server.php'; 

$jsUserId = 'null';
$wsToken = 'null';

if (isset($_SESSION['user_lang'])) {
    $userLang = $_SESSION['user_lang'];
} else {
    $userLang = detect_browser_language(); 
}

$userTheme = $_SESSION['user_theme'] ?? 'system';

// Preferencias de sesión
$userExtendedMsg = isset($_SESSION['user_extended_msg']) ? (int)$_SESSION['user_extended_msg'] : 0;
$openLinksNewTab = isset($_SESSION['user_new_tab']) ? (int)$_SESSION['user_new_tab'] : 1;

// Configuración del servidor
$serverConfigData = getServerConfig($pdo);

I18n::load($userLang);

if (isset($_SESSION['user_id'])) {
    $jsUserId = $_SESSION['user_id'];
    $currentSessionId = session_id();
    $token = generate_ws_auth_token($pdo, $jsUserId, $currentSessionId);
    $wsToken = "'$token'";
} 
?>
<!DOCTYPE html>
<html lang="<?php echo substr($userLang, 0, 2); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">

    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.USER_ID = <?php echo $jsUserId; ?>; 
        window.WS_TOKEN = <?php echo $wsToken; ?>;
        window.USER_LANG = '<?php echo $userLang; ?>';
        window.USER_THEME = '<?php echo $userTheme; ?>';
        
        window.USER_EXTENDED_MSG = <?php echo $userExtendedMsg; ?>; 
        window.OPEN_NEW_TAB = <?php echo $openLinksNewTab; ?>; 
        
        window.SERVER_CONFIG = <?php echo json_encode($serverConfigData); ?>;

        // [SISTEMA DE FALLBACK DE IMÁGENES - EJECUCIÓN INMEDIATA]
        // Se coloca aquí para capturar errores de carga durante el renderizado inicial del HTML
        window.addEventListener('error', function(e) {
            if (e.target.tagName === 'IMG') {
                const img = e.target;
                
                // Evitar bucles infinitos
                if (img.dataset.hasFallback === "true") return;
                img.dataset.hasFallback = "true";

                // Solo actuar si tiene el atributo data-img-type
                const type = img.getAttribute('data-img-type'); 
                if (!type) return;

                const name = img.alt || 'Content'; 
                let fallbackUrl = '';

                if (type === 'banner') {
                    // Fallback rectangular para banners
                    // Usamos un servicio de placeholder gris oscuro con texto blanco
                    fallbackUrl = `https://placehold.co/600x200/555555/ffffff?text=${encodeURIComponent(name)}`;
                } else {
                    // Fallback cuadrado para usuarios y comunidades
                    let bg = '000000';
                    let color = 'fff';
                    
                    if (type === 'community') { bg = '1976d2'; }
                    else if (type === 'user') { bg = '333333'; }

                    fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=${bg}&color=${color}&size=128&bold=true`;
                }

                if (fallbackUrl) {
                    // Reemplazar la fuente
                    img.src = fallbackUrl;
                    
                    // Efecto visual al cargar el fallback (opcional)
                    img.onload = function() {
                        img.classList.add('img-fallback-active');
                    };
                }
            }
        }, true); // useCapture: true es vital para eventos que no burbujean (como 'error')
    </script>

    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/chat.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/componnents.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/admin.css">
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

                    <div class="loader-wrapper">
                        <div class="loader-spinner"></div>
                    </div>

                    <div class="general-content-scrolleable overflow-y" data-container="main-section">
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