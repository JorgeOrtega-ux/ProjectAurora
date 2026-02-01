<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($userLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $i18n->t('app.name'); ?></title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"></script>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/root.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/styles.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $basePath; ?>public/assets/css/components.css">

    <script nonce="<?php echo $cspNonce; ?>">
        window.BASE_PATH = '<?php echo $basePath; ?>';
        window.USER_PREFS = <?php echo $jsUserPrefs; ?>;
        window.TRANSLATIONS = <?php echo $jsTranslations; ?>;
        window.TURNSTILE_SITE_KEY = '<?php echo $turnstileSiteKey; ?>';
        window.IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        
        // Aplicación inmediata del tema (antes de cargar módulos)
        (function() {
            try {
                var localPrefs = localStorage.getItem('guest_prefs');
                var prefs = window.USER_PREFS || {};
                if (!window.IS_LOGGED_IN && localPrefs) {
                    try {
                        var parsed = JSON.parse(localPrefs);
                        if (parsed.theme) prefs.theme = parsed.theme;
                    } catch (e) {}
                }
                var theme = prefs.theme || 'sync';
                if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
                else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
            } catch (e) {}
        })();
    </script>

    <script type="module" src="<?php echo $basePath; ?>public/assets/js/app-init.js"></script>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                
                <?php if ($showInterface): ?>
                    <div class="general-content-top">
                        <?php include __DIR__ . '/../layouts/header.php'; ?>
                    </div>
                <?php endif; ?>

                <div id="system-alert-container">
                    <div class="system-alert-box">
                        <div class="alert-content-left">
                            <span id="sys-alert-icon" class="material-symbols-rounded">info</span>
                            <span id="sys-alert-msg">Mensaje de alerta...</span>
                            <span id="sys-alert-title" style="display:none"></span>
                            <a id="sys-alert-link" style="display:none"></a>
                        </div>
                        <div class="alert-content-right">
                            <button id="sys-alert-close">
                                <span class="material-symbols-rounded" style="font-size: 18px;">close</span>
                            </button>
                        </div>
                    </div>
                </div>
               
                <div class="general-content-bottom">
                    <?php if ($showInterface): ?>
                        <?php include __DIR__ . '/../modules/module-surface.php'; ?>
                    <?php endif; ?>

                    <div class="general-content-scrolleable overflow-y" data-container="main-section">
                        <?php
                        if (file_exists($fileToLoad)) {
                            include $fileToLoad;
                        } else {
                            echo $i18n->t('errors.file_not_found');
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>