<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedInOption = isset($_SESSION['user_id']);
$settingsRoute = $isLoggedInOption ? '/ProjectAurora/settings/your-account' : '/ProjectAurora/settings/guest';
$userRoleOption = $isLoggedInOption && isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';
?>
<div class="component-module component-module--display-overlay component-module--size-m disabled" data-module="moduleMainOptions">
    <div class="component-module-panel">
        <div class="pill-container"><div class="drag-handle"></div></div>
        <div class="component-module-panel-body component-module-panel-body--padded">
            <div class="component-menu-list">
                
                <?php if ($isLoggedInOption && in_array($userRoleOption, ['administrator', 'founder'])): ?>
                <a href="#" class="component-menu-link component-menu-link--bordered" onclick="event.preventDefault();" style="margin-bottom: 4px;">
                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">admin_panel_settings</span></div>
                    <div class="component-menu-link-text"><span style="font-weight: 600;"><?= t('module.admin') ?></span></div>
                </a>
                <?php endif; ?>

                <a href="<?= $settingsRoute; ?>" class="component-menu-link nav-item" data-nav="<?= $settingsRoute; ?>">
                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">settings</span></div>
                    <div class="component-menu-link-text"><span><?= t('module.settings') ?></span></div>
                </a>
                
                <a href="#" class="component-menu-link" onclick="event.preventDefault();">
                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                    <div class="component-menu-link-text"><span><?= t('module.help') ?></span></div>
                </a>
                
                <?php if ($isLoggedInOption): ?>
                    <div style="height: 1px; background-color: #eee; margin: 4px 0; flex-shrink: 0;"></div>
                    <a href="#" class="component-menu-link" data-action="logout">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">logout</span></div>
                        <div class="component-menu-link-text"><span><?= t('module.logout') ?></span></div>
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>