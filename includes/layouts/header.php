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
                <div class="search-icon"><span class="material-symbols-rounded">search</span></div>
                <div class="search-input"><input type="text" placeholder="<?php echo __('search.placeholder'); ?>"></div>
            </div>
        </div>
    </div>
    <div class="header-right">

        <div class="header-item">
            <?php if (!$isLoggedIn): ?>
                <a href="<?php echo $basePath; ?>login" class="header-text-button ghost" data-nav="login">
                    <span><?php echo __('header.login_btn'); ?></span>
                </a>

                <div class="header-button" data-action="toggleModuleOptions">
                    <span class="material-symbols-rounded">more_vert</span>
                </div>

            <?php else: ?>
                <div class="header-button profile-button"
                    data-role="<?php echo htmlspecialchars($userRole); ?>"
                    data-action="toggleModuleOptions">
                    <img src="<?php echo $userPic ?? $basePath . 'assets/img/default-user.png'; ?>" alt="<?php echo $userName; ?>" class="header-profile-img">
                </div>
            <?php endif; ?>
        </div>

        <?php include PROJECT_ROOT . '/includes/modules/module-options.php'; ?>

    </div>
</div>