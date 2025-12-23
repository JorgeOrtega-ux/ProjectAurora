<div class="module-content module-profile disabled" data-module="moduleProfile">
    <div class="menu-content">
        <div class="menu-list">
            
            <?php 
            if (isset($userRole) && in_array($userRole, ['founder', 'administrator'])): 
            ?>
                <div class="menu-link" style="border: 1px solid #00000020;">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">admin_panel_settings</span>
                    </div>
                    <div class="menu-link-text">
                        <span><?php echo $i18n->t('menu.admin_panel'); ?></span>
                    </div>
                </div>
                
                <div style="width: 100%; border-bottom: 1px solid #00000020; margin: 8px 0;"></div>
            <?php endif; ?>

            <div class="menu-link" data-nav="settings/your-profile">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">settings</span>
                </div>
                <div class="menu-link-text">
                    <span><?php echo $i18n->t('menu.settings'); ?></span>
                </div>
            </div>
            
            <div class="menu-link">
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

        </div>
    </div>
</div>