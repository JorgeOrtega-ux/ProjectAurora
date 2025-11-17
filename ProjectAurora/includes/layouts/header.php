<div class="header">
    <div class="header-left">
        <div class="header-item">
            <div class="header-button" data-action="toggleModuleSurface">
                <span class="material-symbols-rounded">menu</span>
            </div>
        </div>
    </div>
    <div class="header-right">
        <div class="header-item">
            <?php
            // Obtener rol actualizado (gracias al código de arriba)
            $userRole = $_SESSION['user_role'] ?? 'user';
            ?>
            <div class="header-button profile-button"
                data-action="toggleModuleOptions"
                data-role="<?php echo htmlspecialchars($userRole); ?>">

                <?php
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

    <div class="popover-module popover-profile body-title disabled" data-module="moduleOptions">
        <div class="menu-content">
            <div class="menu-list">
                <div class="menu-link">
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