<?php
// includes/layout/header.php
?>
<div class="header">
    <div class="header-left">
        <div class="header-button" 
                data-action="toggleModuleSurface"
                data-tooltip="<?php echo __('global.menu'); ?>"
                data-lang-tooltip="global.menu">
            <span class="material-symbols-rounded">menu</span>
        </div>
    </div>

    <div class="header-center" id="headerCenter">
        <div class="search-wrapper">
            <div class="search-container">
                <div class="search-icon">
                    <span class="material-symbols-rounded">search</span>
                </div>
                <div class="search-input">
                    <input type="text" 
                            placeholder="<?php echo __('global.search_placeholder'); ?>"
                            data-lang-placeholder="global.search_placeholder">
                </div>
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="header-item">
            <div class="header-button profile-button"
                data-action="toggleModuleProfile"
                data-role="<?php echo htmlspecialchars($userRole ?? 'guest'); ?>"
                data-tooltip="<?php echo __('menu.profile'); ?>"
                data-lang-tooltip="menu.profile">

                <?php if ($isLoggedIn): ?>
                    <img src="<?php echo $globalAvatarSrc; ?>" 
                            alt="<?php echo __('menu.profile'); ?>" 
                            class="profile-img"
                            <?php echo $headerAvatarNeedsRepair ? 'data-needs-repair="true"' : ''; ?>>
                <?php else: ?>
                    <span style="font-weight:bold; color:#555; position: relative; z-index: 1;">?</span>
                <?php endif; ?>
            </div>

        </div>

        <div class="module-content module-profile body-text disabled" data-module="moduleProfile">
            <div class="menu-content">
                <div class="pill-container">
                    <div class="drag-handle"></div>
                </div>
                <div class="menu-list">
                    
                    <?php if ($isLoggedIn): ?>
                        <?php if (in_array($_SESSION['role'] ?? '', ['founder', 'administrator'])): ?>
                            <div class="menu-link" data-nav="admin/dashboard">
                                <div class="menu-link-icon">
                                    <span class="material-symbols-rounded">admin_panel_settings</span>
                                </div>
                                <div class="menu-link-text">
                                    <span data-lang-key="menu.admin_panel"><?php echo __('menu.admin_panel'); ?></span>
                                </div>
                            </div>
                            <hr class="component-divider" style="margin: 4px 0;">
                        <?php endif; ?>
                        
                        <div class="menu-link" data-nav="settings/your-profile">
                            <div class="menu-link-icon">
                                <span class="material-symbols-rounded">settings</span>
                            </div>
                            <div class="menu-link-text">
                                <span data-lang-key="menu.settings"><?php echo __('menu.settings'); ?></span>
                            </div>
                        </div>

                        <div class="menu-link" data-nav="help/privacy">
                            <div class="menu-link-icon">
                                <span class="material-symbols-rounded">help</span>
                            </div>
                            <div class="menu-link-text">
                                <span data-lang-key="menu.help"><?php echo __('menu.help'); ?></span>
                            </div>
                        </div>

                        <div class="menu-link" id="btn-logout">
                            <div class="menu-link-icon">
                                <span class="material-symbols-rounded">logout</span>
                            </div>
                            <div class="menu-link-text">
                                <span data-lang-key="menu.logout"><?php echo __('menu.logout'); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="menu-link" onclick="window.location.href='<?php echo $basePath; ?>login'">
                            <div class="menu-link-icon">
                                <span class="material-symbols-rounded">login</span>
                            </div>
                            <div class="menu-link-text">
                                <span><?php echo __('auth.login.title'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>