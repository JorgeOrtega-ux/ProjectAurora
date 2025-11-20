<div class="header">
    <div class="header-left">
        <div class="header-item">
            <div class="header-button" 
                 data-action="toggleModuleSurface" 
                 data-tooltip="Menú Principal">
                <span class="material-symbols-rounded">menu</span>
            </div>
        </div>
    </div>

    <div class="header-center">
        <div class="search-container">
            <span class="material-symbols-rounded search-icon">search</span>
            <input type="text" class="search-input" placeholder="Buscar en Aurora..." spellcheck="false">
        </div>
    </div>

    <div class="header-right">
        <div class="header-item">
            
            <div class="header-button"
                 data-action="toggleModuleNotifications"
                 data-tooltip="Notificaciones">
                <span class="material-symbols-rounded">notifications</span>
            </div>

            <?php
            $userRole = $_SESSION['user_role'] ?? 'user';
            ?>
            <div class="header-button profile-button"
                data-action="toggleModuleOptions"
                data-role="<?php echo htmlspecialchars($userRole); ?>"
                data-tooltip="Tu Cuenta"> <?php
                if (isset($_SESSION['user_avatar']) && !empty($_SESSION['user_avatar'])) {
                    $avatarUrl = $basePath . $_SESSION['user_avatar'];
                    echo '<img src="' . htmlspecialchars($avatarUrl) . '" alt="Perfil" class="profile-img">';
                } else {
                    echo '<span class="material-symbols-rounded">person</span>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="popover-module popover-notifications disabled" data-module="moduleNotifications">
        <div class="menu-content">
            
            <div class="pill-container">
                <div class="drag-handle"></div>
            </div>
            
            <div class="menu-content-top">
                <span class="menu-title">Notificaciones</span>
                <div class="notifications-action" title="Marcar todo como leído">
                    <span>Marcar todas como leídas</span>
                </div>
            </div>

            <div class="menu-content-bottom">
                <div class="notifications-empty">
                    <span class="material-symbols-rounded empty-icon">notifications_off</span>
                    <p>No hay nada nuevo por el momento</p>
                </div>
            </div>

        </div>
    </div>

    <div class="popover-module popover-profile body-title disabled" data-module="moduleOptions">
        
        <div class="menu-content">
            <div class="pill-container">
                <div class="drag-handle"></div>
            </div>
            
            <div class="menu-list">
                
                <?php if (in_array($userRole, ['founder', 'administrator', 'admin'])): ?>
                <div class="menu-link" onclick="event.preventDefault(); navigateTo('admin'); document.querySelector('[data-module=\'moduleOptions\']').classList.add('disabled'); document.querySelector('[data-module=\'moduleOptions\']').classList.remove('active');">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">admin_panel_settings</span>
                    </div>
                    <div class="menu-link-text">
                        <span>Panel de administración</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="menu-link" onclick="event.preventDefault(); navigateTo('settings/your-profile'); document.querySelector('[data-module=\'moduleOptions\']').classList.add('disabled'); document.querySelector('[data-module=\'moduleOptions\']').classList.remove('active');">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">settings</span>
                    </div>
                    <div class="menu-link-text">
                        <span>Configuración</span>
                    </div>
                </div>
                
                <div class="menu-link">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">help</span>
                    </div>
                    <div class="menu-link-text">
                        <span>Ayuda y comentarios</span>
                    </div>
                </div>
                <div class="menu-link menu-link-logout">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">logout</span>
                    </div>
                    <div class="menu-link-text">
                        <span>Cerrar sesión</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>