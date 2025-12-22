<div class="header">
    <div class="header-left">
        <div class="header-item">
            <div class="header-button" data-action="toggleModuleSurface">
                <span class="material-symbols-rounded">menu</span>
            </div>
        </div>
    </div>
    <div class="header-center">
        <div class="search-wrapper">
            <div class="search-content">
                <div class="search-icon">
                    <span class="material-symbols-rounded">search</span>
                </div>
                <div class="search-input">
                    <input type="text">
                </div>
            </div>
        </div>
    </div>
    <div class="header-right">
        <div class="header-item">
            <div class="header-button">
                <span class="material-symbols-rounded">notifications</span>
            </div>
            <div class="header-button profile-button" 
                 data-action="toggleModuleProfile"
                 style="<?php echo isset($avatarStyle) ? $avatarStyle : ''; ?>">
                 <?php if(empty($avatarStyle)): ?>
                    <span class="material-symbols-rounded">person</span>
                 <?php endif; ?>
            </div>
        </div>
        <?php include '../includes/modules/module-profile.php'; ?>
    </div>
</div>