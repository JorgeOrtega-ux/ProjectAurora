<div class="module-content module-profile disabled" data-module="moduleProfile">
    <div class="menu-content">
        <div class="pill-container">
            <div class="drag-handle"></div>
        </div>
        <div class="menu-list">
            
            <?php if (isset($_SESSION['user_id'])): ?>
                
                <?php if (isset($userRole) && in_array($userRole, ['founder', 'administrator'])): ?>
                    <div class="menu-link menu-link--bordered" data-nav="admin/dashboard">
                        <div class="menu-link-icon">
                            <span class="material-symbols-rounded">admin_panel_settings</span>
                        </div>
                        <div class="menu-link-text">
                            <span><?php echo $i18n->t('menu.admin_panel'); ?></span>
                        </div>
                    </div>
                    <div class="menu-divider"></div>
                <?php endif; ?>

                <div class="menu-link" data-nav="c/<?php echo $_SESSION['uuid'] ?? ''; ?>">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">video_settings</span>
                    </div>
                    <div class="menu-link-text">
                        <span>Administrar canal</span>
                    </div>
                </div>

                <div class="menu-link" data-nav="settings/your-profile">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">settings</span>
                    </div>
                    <div class="menu-link-text">
                        <span><?php echo $i18n->t('menu.settings'); ?></span>
                    </div>
                </div>
                
                <div class="menu-link" data-nav="site-policy">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">help</span>
                    </div>
                    <div class="menu-link-text">
                        <span><?php echo $i18n->t('menu.help'); ?></span>
                    </div>
                </div>
                
                <div class="menu-link" data-action="logout">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">logout</span>
                    </div>
                    <div class="menu-link-text">
                        <span><?php echo $i18n->t('menu.logout'); ?></span>
                    </div>
                </div>

            <?php else: ?>
                <div class="menu-link" data-nav="settings/preferences">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">settings</span>
                    </div>
                    <div class="menu-link-text">
                        <span><?php echo $i18n->t('menu.settings'); ?></span>
                    </div>
                </div>

                <div class="menu-link" data-nav="site-policy">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">help</span>
                    </div>
                    <div class="menu-link-text">
                        <span><?php echo $i18n->t('menu.help'); ?></span>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>