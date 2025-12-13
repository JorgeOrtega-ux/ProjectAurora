<?php
// public/index.php

// 1. Cargar el Router (Configuración y Sesión)
require_once __DIR__ . '/../config/routers/router.php';

// 2. Definir el mapa de rutas FÍSICAS
$sectionMap = require __DIR__ . '/../config/routes.php';

// Lógica de detección de sección
$isSettingsSection = (strpos($currentSection, 'settings/') === 0);
$isAdminSection = (strpos($currentSection, 'admin/') === 0);
$isHelpSection = (strpos($currentSection, 'help/') === 0);

// --- LÓGICA DE AVATAR DEL HEADER (MODIFICADA CON SELF-HEALING) ---
$globalAvatarSrc = '';
$headerAvatarNeedsRepair = false; 

if (isset($_SESSION['uuid']) && isset($_SESSION['user_id'])) {
    $uuid = $_SESSION['uuid'];
    $userId = $_SESSION['user_id'];
    $cacheBuster = '?v=' . time(); 
    
    // Rutas relativas (para el navegador)
    $relCustom  = 'assets/uploads/avatars/custom/' . $uuid . '.png';
    $relDefault = 'assets/uploads/avatars/default/' . $uuid . '.png';
    
    // Rutas absolutas (para file_exists)
    $absCustom  = __DIR__ . '/' . $relCustom;
    $absDefault = __DIR__ . '/' . $relDefault;

    // 1. Prioridad: Avatar personalizado
    if (file_exists($absCustom)) {
        $globalAvatarSrc = $basePath . $relCustom . $cacheBuster;
    } 
    // 2. Fallback: Avatar por defecto (SOLO SI EXISTE)
    elseif (file_exists($absDefault)) {
        $globalAvatarSrc = $basePath . $relDefault . $cacheBuster;
    }
    // 3. Emergencia: No existe ninguno (Se borraron) -> Usar Fallback Local
    else {
        $headerAvatarNeedsRepair = true;
        // Determinista: El mismo usuario siempre obtiene el mismo color de fallback
        $fallbackIndex = ($userId % 5) + 1;
        $globalAvatarSrc = $basePath . 'assets/uploads/avatars/fallback/' . $fallbackIndex . '.png';
    }
}

// --- LÓGICA DE TEMA ---
$initialThemeClass = 'light-theme';
$userThemePref = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'system';
$userExtendedAlerts = isset($_SESSION['extended_alerts']) ? (int)$_SESSION['extended_alerts'] : 0;

if ($userThemePref === 'dark') {
    $initialThemeClass = 'dark-theme';
} elseif ($userThemePref === 'light') {
    $initialThemeClass = 'light-theme';
} else {
    $initialThemeClass = 'light-theme system-theme-pending';
}

// --- SEGURIDAD: FILTRADO DE CONFIGURACIÓN PÚBLICA ---
$publicConfigKeys = [
    'maintenance_mode',
    'allow_registrations',
    'min_password_length',
    'max_password_length',
    'min_username_length',
    'max_username_length',
    'max_email_length',
    'profile_picture_max_size'
];

$rawConfig = $GLOBALS['SERVER_CONFIG'] ?? [];
$safePublicConfig = array_intersect_key($rawConfig, array_flip($publicConfigKeys));

?>
<!DOCTYPE html>
<html lang="<?php echo isset($userLang) ? $userLang : 'es'; ?>" class="<?php echo $initialThemeClass; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

    <script>
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.TRANSLATIONS = <?php echo json_encode($GLOBALS['AURORA_TRANSLATIONS']); ?>;
        
        // INYECCIÓN DE CONFIGURACIÓN DEL SERVIDOR (FILTRADA)
        window.SERVER_CONFIG = <?php echo json_encode($safePublicConfig); ?>;
        
        window.USER_PREFS = {
            theme: '<?php echo $userThemePref; ?>',
            extended_alerts: <?php echo $userExtendedAlerts; ?>
        };

        // EXPOSICIÓN DEL ID DE USUARIO PARA REPARACIÓN UI
        window.CURRENT_USER_ID = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;

        // Función de traducción mejorada
        window.t = function(key, ...args) {
            let text = window.TRANSLATIONS[key] || key;
            if (args.length > 0) {
                args.forEach(arg => {
                    text = text.replace('%s', arg);
                });
            }
            return text;
        };
    </script>
    
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/components.css">

    <title><?php echo __('app.name'); ?></title>

    <?php 
    $showFullLayout = ($isLoggedIn || $isHelpSection) && $currentSection !== 'maintenance';
    
    if (!$showFullLayout): ?>
        <style>
            [data-container="main-section"] {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100%;
            }
        </style>
    <?php endif; ?>
</head>

<body>

    <div class="page-wrapper <?php echo (!$isLoggedIn) ? 'auth-mode' : ''; ?>">
        <div class="main-content">
            <div class="general-content">

                <?php if ($showFullLayout): ?>
                    <div class="general-content-top">
                        <?php include __DIR__ . '/../includes/layout/header.php'; ?>
                    </div>
                <?php endif; ?>

                <div class="general-content-bottom">
                    <?php if ($showFullLayout): ?>
                        <?php include __DIR__ . '/../includes/layout/surface.php'; ?>
                    <?php endif; ?>

                    <div class="general-content-scrolleable overflow-y" data-container="main-section">
                        <?php
                        $fileToLoad = $sectionMap['404']; 
                        
                        if (array_key_exists($currentSection, $sectionMap)) {
                            $fileToLoad = $sectionMap[$currentSection];
                        } 

                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            echo "<h1>" . __('global.critical_error_404') . "</h1><p>" . __('global.critical_error_404_desc') . "</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="<?php echo $basePath; ?>assets/js/core/app-init.js"></script>

</body>

</html>