<div class="header">
    <div class="header-left">
        <div class="header-item">
            <div class="header-button" 
                 data-action="toggleModuleSurface" 
                 data-tooltip="Menú principal">
                <span class="material-symbols-rounded">menu</span>
            </div>
        </div>
    </div>
    
    <div class="header-center" id="header-search-bar">
        <div class="search-wrapper">
            <div class="search-content">
                <div class="search-icon">
                    <span class="material-symbols-rounded">search</span>
                </div>
                <div class="search-input">
                    <input type="text" placeholder="<?php echo $i18n->t('header.search_placeholder'); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="header-item">
            <div class="header-button mobile-search-trigger" 
                 data-action="toggleSearch"
                 data-tooltip="Buscar">
                <span class="material-symbols-rounded">search</span>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                
                <div class="header-item" style="position: relative;" data-trigger="dropdown">
                    
                    <div class="header-button" data-tooltip="Crear nuevo">
                        <span class="material-symbols-rounded">add</span>
                    </div>

                    <div class="popover-module">
                        <div class="menu-list">
                            <a href="#" class="menu-link">
                                <div class="menu-link-icon">
                                    <span class="material-symbols-rounded">add_circle</span>
                                </div>
                                <div class="menu-link-text">Crear un lienzo</div>
                            </a>
                            
                            <a href="#" class="menu-link">
                                <div class="menu-link-icon">
                                    <span class="material-symbols-rounded">link</span>
                                </div>
                                <div class="menu-link-text">Unirme a un lienzo</div>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="header-button profile-button" 
                     data-action="toggleModuleProfile"
                     data-role="<?php echo htmlspecialchars($userRole ?? 'guest'); ?>"
                     data-tooltip="<?php echo $i18n->t('menu.profile'); ?>">
                     
                     <img src="<?php echo $globalAvatarSrc; ?>" class="profile-img" alt="Profile">
                </div>

            <?php else: ?>
                <a href="<?php echo $basePath; ?>login" 
                   class="component-button primary" 
                   style="text-decoration: none; padding: 0 16px;">
                    <span style="font-weight: 600;">Acceder</span>
                </a>

                <div class="header-button" 
                     data-action="toggleModuleProfile"
                     data-tooltip="Opciones">
                    <span class="material-symbols-rounded">more_vert</span>
                </div>
            <?php endif; ?>

        </div>
        <?php include '../includes/modules/module-profile.php'; ?>
    </div>
</div>

<div id="system-alert-container" style="display:none; width: 100%; background: #0f0f0f; border-bottom: 2px solid #333; padding: 12px 24px; box-sizing: border-box; align-items: center; justify-content: space-between; position: relative; z-index: 9999;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <span id="sys-alert-icon" class="material-symbols-rounded" style="color: #ff9800;">warning</span>
        <div>
            <strong id="sys-alert-title" style="color: #fff; display: block; font-size: 14px;"></strong>
            <span id="sys-alert-msg" style="color: #ccc; font-size: 13px;"></span>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <a id="sys-alert-link" href="#" target="_blank" style="color: #4a90e2; font-size: 13px; display: none;">Ver detalles</a>
        <button id="sys-alert-close" style="background: none; border: none; color: #666; cursor: pointer;">
            <span class="material-symbols-rounded">close</span>
        </button>
    </div>
</div>