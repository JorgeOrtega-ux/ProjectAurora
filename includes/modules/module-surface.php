<?php
// includes/modules/module-surface.php

// Lógica de visualización inicial (Server-Side)
$isSettings = strpos($currentSection, 'settings/') === 0;
$isHelp = strpos($currentSection, 'site-policy') === 0;
$isAdmin = strpos($currentSection, 'admin/') === 0;
$isMain = !$isSettings && !$isHelp && !$isAdmin;

// Helper para imprimir el estilo de ocultamiento
function visibility($isVisible) {
    return $isVisible ? '' : 'style="display: none;"';
}
?>

<div class="module-content module-surface body-text disabled" data-module="moduleSurface">

    <div class="menu-content" id="surface-main" <?php echo visibility($isMain); ?>>
        <div class="menu-content-top">
            <div class="menu-list">
                <div class="menu-link <?php echo ($currentSection === 'main') ? 'active' : ''; ?>" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">home</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.home'); ?></div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'explore') ? 'active' : ''; ?>" data-nav="explore">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">explore</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.explore'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="menu-content" id="surface-settings" <?php echo visibility($isSettings); ?>>
        <div class="menu-content-top">
            <div class="menu-list">
                <div class="menu-link menu-link--bordered" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.back_home'); ?></div>
                </div>
                <div class="menu-divider"></div>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="menu-link <?php echo ($currentSection === 'settings/your-profile') ? 'active' : ''; ?>" data-nav="settings/your-profile">
                        <div class="menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                        <div class="menu-link-text"><?php echo $i18n->t('menu.profile'); ?></div>
                    </div>
                    <div class="menu-link <?php echo ($currentSection === 'settings/login-security') ? 'active' : ''; ?>" data-nav="settings/login-security">
                        <div class="menu-link-icon"><span class="material-symbols-rounded">lock</span></div>
                        <div class="menu-link-text"><?php echo $i18n->t('menu.security'); ?></div>
                    </div>
                    <div class="menu-link <?php echo ($currentSection === 'settings/accessibility') ? 'active' : ''; ?>" data-nav="settings/accessibility">
                        <div class="menu-link-icon"><span class="material-symbols-rounded">accessibility_new</span></div>
                        <div class="menu-link-text"><?php echo $i18n->t('menu.accessibility'); ?></div>
                    </div>
                <?php else: ?>
                    <div class="menu-link <?php echo ($currentSection === 'settings/preferences') ? 'active' : ''; ?>" data-nav="settings/preferences">
                        <div class="menu-link-icon"><span class="material-symbols-rounded">tune</span></div>
                        <div class="menu-link-text">Preferencias</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="menu-content" id="surface-help" <?php echo visibility($isHelp); ?>>
        <div class="menu-content-top">
            <div class="menu-list">
                <div class="menu-link menu-link--bordered" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.back_home'); ?></div>
                </div>

                <div class="menu-divider"></div>

                <div class="menu-link <?php echo ($currentSection === 'site-policy/privacy-policy') ? 'active' : ''; ?>" data-nav="site-policy/privacy-policy">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">policy</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('help.menu_privacy'); ?></div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'site-policy/terms-of-service') ? 'active' : ''; ?>" data-nav="site-policy/terms-of-service">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">gavel</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('help.menu_terms'); ?></div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'site-policy/cookie-policy') ? 'active' : ''; ?>" data-nav="site-policy/cookie-policy">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">cookie</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('help.menu_cookies'); ?></div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'site-policy/send-feedback') ? 'active' : ''; ?>" data-nav="site-policy/send-feedback">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">chat</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('help.menu_feedback'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($userRole) && in_array($userRole, ['founder', 'administrator'])): ?>
    <div class="menu-content" id="surface-admin" <?php echo visibility($isAdmin); ?>>
        <div class="menu-content-top">
            <div class="menu-list">
                <div class="menu-link menu-link--bordered" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.admin.exit'); ?></div>
                </div>

                <div class="menu-divider"></div>

                <div class="menu-link <?php echo ($currentSection === 'admin/dashboard') ? 'active' : ''; ?>" data-nav="admin/dashboard">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">dashboard</span></div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.admin.dashboard'); ?></div>
                </div>
                
                <div class="menu-link <?php echo ($currentSection === 'admin/users') ? 'active' : ''; ?>" data-nav="admin/users">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">group</span></div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.admin.users'); ?></div>
                </div>
                
                </div>
        </div>

        <div class="menu-content-bottom">
            <div class="menu-list">
                <div class="menu-divider"></div>
                
                <div class="menu-link <?php echo ($currentSection === 'admin/backups') ? 'active' : ''; ?>" data-nav="admin/backups">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">backup</span></div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.admin.backups'); ?></div>
                </div>
                
                <div class="menu-link <?php echo ($currentSection === 'admin/server') ? 'active' : ''; ?>" data-nav="admin/server">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">dns</span></div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.admin.server'); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>