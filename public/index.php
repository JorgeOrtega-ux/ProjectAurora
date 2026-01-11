<?php
// public/index.php

// 1. CONFIGURACIÓN
define('PROJECT_ROOT', dirname(__DIR__));
define('CONFIG_PATH', PROJECT_ROOT . '/config');
$basePath = '/ProjectAurora/';

// 2. BOOTSTRAP (Carga Idioma, Preferencias, SESIÓN y AuthServices)
require_once PROJECT_ROOT . '/includes/core/boot.php';

// --- INTERCEPTOR DE AUTH ---
if (isset($_REQUEST['action'])) {
    require_once PROJECT_ROOT . '/api/controllers/AuthHandler.php';
    $handler = new AuthHandler($basePath);
    $handler->handleRequest();
}

// Variables de sesión
$isLoggedIn = isset($_SESSION['user_id']);
$userPic = $_SESSION['user_pic'] ?? null;
$userName = $_SESSION['username'] ?? 'Usuario';
$userRole = $_SESSION['user_role'] ?? 'user'; 

// 3. DETECTAR SECCIÓN ACTUAL
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace($basePath, '', $requestUri);
$currentSection = ($path === '' || $path === '/') ? 'main' : $path;
$currentSection = trim($currentSection, '/');
if ($currentSection === '') $currentSection = 'main';

// --- LÓGICA DE REDIRECCIÓN /settings ---
if ($currentSection === 'settings') {
    $target = $isLoggedIn ? 'settings/profile' : 'settings/preferences';
    header("Location: " . $basePath . $target);
    exit;
}
// ---------------------------------------

// --- SEGURIDAD: RUTAS PROTEGIDAS ---
$protectedRoutes = [
    'settings/profile',
    'settings/security'
];

if (in_array($currentSection, $protectedRoutes) && !$isLoggedIn) {
    header("Location: " . $basePath . "login");
    exit;
}
// -----------------------------------

// 4. CONTEXTO
$helpSections = ['help', 'privacy', 'terms', 'cookies', 'feedback'];
$settingsSections = ['settings/preferences', 'settings/profile', 'settings/security', 'settings/accessibility'];

$isHelp = in_array($currentSection, $helpSections);
$isSettings = in_array($currentSection, $settingsSections);
$context = $isHelp ? 'help' : ($isSettings ? 'settings' : 'app');

// 5. CARGAR RUTAS
$routes = require CONFIG_PATH . '/routes.php';
$fileToLoad = $routes[$currentSection] ?? $routes['404'];

// 6. LÓGICA DE TEMA
$htmlDataTheme = 'light';
if (isset($currentTheme)) {
    if ($currentTheme === 'dark') $htmlDataTheme = 'dark';
    elseif ($currentTheme === 'light') $htmlDataTheme = 'light';
    else $htmlDataTheme = 'sync';
}

// 7. BUFFER DE SALIDA
ob_start();
if (file_exists($fileToLoad)) include $fileToLoad;
else echo "<div style='padding:40px;'><h1>404</h1></div>";
$contentHtml = ob_get_clean();

// --- NUEVO: Cálculo del Título para <head> ---
$pageTitle = __('app.title');
if ($currentSection !== 'main') {
    $pageTitle .= ' - ' . ucfirst($currentSection);
}
// ---------------------------------------------
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>" data-theme="<?php echo $htmlDataTheme; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>assets/css/components.css">

    <script>
        (function() {
            const savedTheme = '<?php echo $currentTheme ?? "sync"; ?>';
            if (savedTheme === 'sync') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            }
        })();
        window.BASE_PATH = '<?php echo $basePath; ?>';
    </script>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">

                <div class="general-content-top">
                      <?php include PROJECT_ROOT . '/includes/layouts/header.php'; ?>
                </div>

                <div class="general-content-bottom">
                    <div class="module-content module-surface disabled">
                        <?php if ($context === 'app'): ?>
                            <?php include PROJECT_ROOT . '/includes/menus/app.php'; ?>
                        <?php elseif ($context === 'settings'): ?>
                            <?php include PROJECT_ROOT . '/includes/menus/settings.php'; ?>
                        <?php elseif ($context === 'help'): ?>
                            <?php include PROJECT_ROOT . '/includes/menus/help.php'; ?>
                        <?php endif; ?>
                    </div>

                    <div class="general-content-scrolleable overflow-y" id="app-content">
                        <?php echo $contentHtml; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="module" src="<?php echo $basePath; ?>assets/js/app-init.js"></script>
</body>

</html>