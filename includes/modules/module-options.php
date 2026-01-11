<div class="module-content module-options disabled">
    <div class="menu-content">
        <div class="menu-list">
            
            <div class="menu-link" data-nav="settings/preferences">
                <div class="menu-link-icon"><span class="material-symbols-rounded">settings</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.settings'); ?></span></div>
            </div>
            <div class="menu-link" data-nav="help">
                <div class="menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.help'); ?></span></div>
            </div>

            <?php if ($isLoggedIn && in_array($_SESSION['user_role'] ?? '', ['founder', 'administrator'])): ?>
                <div style="width: 100%; height: 1px; background-color: #eee; margin: 4px 0;"></div>
                <div class="menu-link" data-nav="admin/dashboard"> 
                    <div class="menu-link-icon"><span class="material-symbols-rounded">admin_panel_settings</span></div>
                    <div class="menu-link-text"><span>Panel de Administración</span></div>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <div style="width: 100%; height: 1px; background-color: #eee; margin: 4px 0;"></div>
                <a href="<?php echo $basePath; ?>?action=logout" class="menu-link">
                    <div class="menu-link-icon"><span class="material-symbols-rounded" style="color: #ff4444;">logout</span></div>
                    <div class="menu-link-text"><span style="color: #ff4444;"><?php echo __('menu.logout'); ?></span></div>
                </a>
            <?php endif; ?>

        </div>
    </div>
</div>