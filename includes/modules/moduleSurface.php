<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? 'user';
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

$isSettings = strpos($currentUri, '/settings/') !== false;
$isAdminRoute = strpos($currentUri, '/admin/') !== false;
$hasAdminRights = in_array($userRole, ['administrator', 'founder']);

// Si intenta acceder a una ruta de admin pero no tiene permisos, el menú no cambiará al de admin
if ($isAdminRoute && !$hasAdminRights) {
    $isAdminRoute = false;
}

$adminMenuDisplay = $isAdminRoute ? 'flex' : 'none';
$settingsMenuDisplay = (!$isAdminRoute && $isSettings) ? 'flex' : 'none';
$mainMenuDisplay = (!$isAdminRoute && !$isSettings) ? 'flex' : 'none';
?>
<div class="component-module component-module--display-block component-module--size-m component-module--offset-s overflow-y disabled" data-module="moduleSurface">
    
    <div class="component-module-panel" style="display: <?= $mainMenuDisplay; ?>;">
        <div class="component-module-panel-body">
            <div class="component-module-panel-top">
                <div class="component-menu-list" id="menu-surface-main" style="flex-direction: column; gap: 4px;">
                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">home</span></div>
                        <div class="component-menu-link-text"><span><?= t('surface.home') ?></span></div>
                    </div>
                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/explore">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">trending_up</span></div>
                        <div class="component-menu-link-text"><span><?= t('surface.explore') ?></span></div>
                    </div>
                </div>
            </div>
            <div class="component-module-panel-bottom"></div>
        </div>
    </div>

    <div class="component-module-panel" style="display: <?= $settingsMenuDisplay; ?>;">
        <div class="component-module-panel-body">
            <div class="component-module-panel-top">
                <div class="component-menu-list" id="menu-surface-settings" style="flex-direction: column; gap: 4px;">
                    <div class="component-menu-link component-menu-link--bordered nav-item" data-nav="/ProjectAurora/">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                        <div class="component-menu-link-text"><span style="font-weight: 600;"><?= t('surface.back') ?></span></div>
                    </div>
                    
                    <div style="height: 1px; background-color: var(--divider-color); margin: 4px 0; flex-shrink: 0;"></div>

                    <?php if ($isLoggedIn): ?>
                        <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/your-account">
                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                            <div class="component-menu-link-text"><span><?= t('surface.profile') ?></span></div>
                        </div>
                        <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/security">
                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">security</span></div>
                            <div class="component-menu-link-text"><span><?= t('surface.security') ?></span></div>
                        </div>
                        <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/accessibility">
                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">accessibility_new</span></div>
                            <div class="component-menu-link-text"><span><?= t('surface.accessibility') ?></span></div>
                        </div>
                    <?php else: ?>
                        <div class="component-menu-link nav-item" data-nav="/ProjectAurora/settings/guest">
                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">manage_accounts</span></div>
                            <div class="component-menu-link-text"><span><?= t('surface.guest') ?></span></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="component-module-panel-bottom"></div>
        </div>
    </div>

    <?php if ($hasAdminRights): ?>
    <div class="component-module-panel" style="display: <?= $adminMenuDisplay; ?>;">
        <div class="component-module-panel-body">
            
            <div class="component-module-panel-top">
                <div class="component-menu-list" id="menu-surface-admin" style="flex-direction: column; gap: 4px;">
                    <div class="component-menu-link component-menu-link--bordered nav-item" data-nav="/ProjectAurora/">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                        <div class="component-menu-link-text"><span style="font-weight: 600;"><?= t('surface.back') ?></span></div>
                    </div>
                    
                    <div style="height: 1px; background-color: var(--divider-color); margin: 4px 0; flex-shrink: 0;"></div>

                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/admin/dashboard">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">dashboard</span></div>
                        <div class="component-menu-link-text"><span><?= t('surface.admin_dashboard') ?></span></div>
                    </div>
                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/admin/users">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">group</span></div>
                        <div class="component-menu-link-text"><span><?= t('surface.admin_users') ?></span></div>
                    </div>
                </div>
            </div>

            <div class="component-module-panel-bottom">
                <div class="component-menu-list" style="flex-direction: column; gap: 4px; display: flex;">
                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/admin/backups">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">backup</span></div>
                        <div class="component-menu-link-text"><span><?= t('surface.admin_backups') ?></span></div>
                    </div>
                    <div class="component-menu-link nav-item" data-nav="/ProjectAurora/admin/server">
                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">dns</span></div>
                        <div class="component-menu-link-text"><span><?= t('surface.admin_server') ?></span></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>