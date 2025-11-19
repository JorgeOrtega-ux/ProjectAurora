<?php
// includes/modules/module-surface.php
$isSettings = (strpos($CURRENT_SECTION, 'settings/') === 0);
?>
<div class="module-content module-surface body-title disabled" data-module="moduleSurface">
    <div class="menu-content">
        
        <div class="menu-list" id="sidebar-menu-app" style="display: <?php echo $isSettings ? 'none' : 'flex'; ?>;">
            
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

        <div class="menu-list" id="sidebar-menu-settings" style="display: <?php echo $isSettings ? 'flex' : 'none'; ?>;">
            
            <div class="menu-link" data-nav="main" style="border-bottom: 1px solid #eee; margin-bottom: 5px;">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="menu-link-text">
                    <span>Volver a inicio</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'settings/your-profile') ? 'active' : ''; ?>"
                data-nav="settings/your-profile">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">person</span>
                </div>
                <div class="menu-link-text">
                    <span>Tu perfil</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'settings/login-security') ? 'active' : ''; ?>"
                data-nav="settings/login-security">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">lock</span>
                </div>
                <div class="menu-link-text">
                    <span>Inicio de sesión y seguridad</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'settings/accessibility') ? 'active' : ''; ?>"
                data-nav="settings/accessibility">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">accessibility_new</span>
                </div>
                <div class="menu-link-text">
                    <span>Accesibilidad</span>
                </div>
            </div>

        </div>

    </div>
</div>