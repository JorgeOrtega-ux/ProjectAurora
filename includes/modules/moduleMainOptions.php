<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedInOption = isset($_SESSION['user_id']);
$settingsRoute = $isLoggedInOption ? '/ProjectAurora/settings/your-account' : '/ProjectAurora/settings/guest';
?>
<div class="component-module component-module--display-overlay component-module--size-m disabled" data-module="moduleMainOptions">
    <div class="component-module-panel component-module-panel--padded">
        <div class="pill-container">
            <div class="drag-handle"></div>
        </div>
        <div class="component-menu-list">
            
            <a href="<?php echo $settingsRoute; ?>" class="component-menu-link nav-item" data-nav="<?php echo $settingsRoute; ?>">
                <div class="component-menu-link-icon">
                    <span class="material-symbols-rounded">settings</span>
                </div>
                <div class="component-menu-link-text">
                    <span>Configuracion</span>
                </div>
            </a>
            
            <a href="#" class="component-menu-link">
                <div class="component-menu-link-icon">
                    <span class="material-symbols-rounded">help</span>
                </div>
                <div class="component-menu-link-text">
                    <span>Ayuda y comentarios</span>
                </div>
            </a>
            
            <?php if ($isLoggedInOption): ?>
                <div style="height: 1px; background-color: #eee; margin: 4px 0;"></div>

                <a href="#" class="component-menu-link" data-action="logout">
                    <div class="component-menu-link-icon">
                        <span class="material-symbols-rounded">logout</span>
                    </div>
                    <div class="component-menu-link-text">
                        <span>Cerrar sesión</span>
                    </div>
                </a>
            <?php endif; ?>

        </div>
    </div>
</div>