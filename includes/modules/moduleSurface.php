<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);

// Verificar si cargamos directamente desde el navegador en la sección settings
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$isSettings = strpos($currentUri, '/settings/') !== false;

$mainMenuDisplay = $isSettings ? 'none' : 'flex';
$settingsMenuDisplay = $isSettings ? 'flex' : 'none';
?>
<div class="component-module component-module--display-block component-module--size-m component-module--offset-s disabled" data-module="moduleSurface">
    <div class="component-module-panel">
        <div class="component-module-panel-top">
            
            <div class="component-menu-list" id="menu-surface-main" style="display: <?php echo $mainMenuDisplay; ?>; flex-direction: column; gap: 4px;">
                <div class="component-menu-link nav-item" data-nav="/ProjectAurora/">
                    <div class="component-menu-link-icon">
                        <span class="material-symbols-rounded">home</span>
                    </div>
                    <div class="component-menu-link-text">
                        <span>Página principal</span>
                    </div>
                </div>

                <div class="component-menu-link nav-item" data-nav="/ProjectAurora/explore">
                    <div class="component-menu-link-icon">
                        <span class="material-symbols-rounded">trending_up</span>
                    </div>
                    <div class="component-menu-link-text">
                        <span>Explorar tendencias</span>
                    </div>
                </div>
            </div>

            <div class="component-menu-list" id="menu-surface-settings" style="display: <?php echo $settingsMenuDisplay; ?>; flex-direction: column; gap: 4px;">
                <div class="component-menu-link nav-item" data-nav="/ProjectAurora/" style="margin-bottom: 12px;">
                    <div class="component-menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="component-menu-link-text">
                        <span style="font-weight: 600;">Volver al inicio</span>
                    </div>
                </div>

                <?php if ($isLoggedIn): ?>
                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/your-account">
                        <div class="component-menu-link-icon">
                            <span class="material-symbols-rounded">person</span>
                        </div>
                        <div class="component-menu-link-text">
                            <span>Tu perfil</span>
                        </div>
                    </div>

                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/security">
                        <div class="component-menu-link-icon">
                            <span class="material-symbols-rounded">security</span>
                        </div>
                        <div class="component-menu-link-text">
                            <span>Inicio de sesión y seguridad</span>
                        </div>
                    </div>

                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/accessibility">
                        <div class="component-menu-link-icon">
                            <span class="material-symbols-rounded">accessibility_new</span>
                        </div>
                        <div class="component-menu-link-text">
                            <span>Accesibilidad</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/guest">
                        <div class="component-menu-link-icon">
                            <span class="material-symbols-rounded">manage_accounts</span>
                        </div>
                        <div class="component-menu-link-text">
                            <span>Configuración de invitado</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        <div class="component-module-panel-bottom">
        </div>
    </div>
</div>