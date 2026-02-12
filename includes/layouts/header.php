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