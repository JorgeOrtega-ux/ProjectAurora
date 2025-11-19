<?php
// includes/modules/module-surface.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Si el router bloqueó el acceso, CURRENT_SECTION será '404'.
// Si es '404', no empieza ni con 'settings/' ni con 'admin/', 
// por lo que $isSettings y $isAdminSection serán falsos.
$CURRENT_SECTION = $CURRENT_SECTION ?? 'main';

$isSettings = (strpos($CURRENT_SECTION, 'settings/') === 0);
$isAdminSection = (strpos($CURRENT_SECTION, 'admin/') === 0);

// Validar permisos reales para decidir si IMPRIMIMOS el código HTML del menú admin
$userRole = $_SESSION['user_role'] ?? 'user';
$canSeeAdmin = in_array($userRole, ['founder', 'administrator', 'admin']);
?>
<div class="module-content module-surface body-title disabled" data-module="moduleSurface">
    <div class="menu-content">
        
        <div class="menu-list" id="sidebar-menu-app" style="display: <?php echo (!$isSettings && !$isAdminSection) ? 'flex' : 'none'; ?>;">
            
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

        <?php if ($canSeeAdmin): ?>
        <div class="menu-list" id="sidebar-menu-admin" style="display: <?php echo $isAdminSection ? 'flex' : 'none'; ?>;">
            
            <div class="menu-link" data-nav="main" style="border-bottom: 1px solid #eee; margin-bottom: 5px;">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="menu-link-text">
                    <span>Volver a inicio</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/dashboard') ? 'active' : ''; ?>"
                data-nav="admin/dashboard">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">dashboard</span>
                </div>
                <div class="menu-link-text">
                    <span>Dashboard</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/users') ? 'active' : ''; ?>"
                data-nav="admin/users">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">group</span>
                </div>
                <div class="menu-link-text">
                    <span>Gestionar usuarios</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/backups') ? 'active' : ''; ?>"
                data-nav="admin/backups">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">backup</span>
                </div>
                <div class="menu-link-text">
                    <span>Copias de seguridad</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'admin/server') ? 'active' : ''; ?>"
                data-nav="admin/server">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">dns</span>
                </div>
                <div class="menu-link-text">
                    <span>Configuración del servidor</span>
                </div>
            </div>

        </div>
        <?php endif; ?>

    </div>
</div>