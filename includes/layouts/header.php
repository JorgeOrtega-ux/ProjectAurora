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

            <div class="header-button" 
                 data-action="toggleModuleNotifications"
                 data-tooltip="<?php echo $i18n->t('notifications.title'); ?>">
                <span class="material-symbols-rounded">notifications</span>
            </div>
            
            <div class="header-button profile-button" 
                 data-action="toggleModuleProfile"
                 data-role="<?php echo htmlspecialchars($userRole ?? 'guest'); ?>"
                 data-tooltip="<?php echo $i18n->t('menu.profile'); ?>">
                 
                 <?php if(isset($isLoggedIn) && $isLoggedIn): ?>
                    <img src="<?php echo $globalAvatarSrc; ?>" class="profile-img" alt="Profile">
                 <?php else: ?>
                    <span class="header-guest-icon">?</span>
                 <?php endif; ?>
                 
            </div>
        </div>
        <?php include '../includes/modules/module-profile.php'; ?>
        <?php include '../includes/modules/module-notifications.php'; ?>
    </div>
</div>