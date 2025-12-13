<?php
// includes/layout/sidebar.php
?>
<div class="module-content module-surface body-text disabled" data-module="moduleSurface">
    <div class="menu-content">
        <div class="menu-content-top">

            <div data-context="main" class="menu-list <?php echo ($isSettingsSection || $isAdminSection || $isHelpSection) ? 'disabled' : ''; ?>">
                <div class="menu-link <?php echo ($currentSection === 'main') ? 'active' : ''; ?>" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">home</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="menu.home"><?php echo __('menu.home'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'explorer') ? 'active' : ''; ?>" data-nav="explorer">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">explore</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="menu.explore"><?php echo __('menu.explore'); ?></span>
                    </div>
                </div>
            </div>

            <div data-context="settings" class="menu-list <?php echo !$isSettingsSection ? 'disabled' : ''; ?>">
                <div class="menu-link menu-link-back" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="global.back_home"><?php echo __('global.back_home'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'settings/your-profile') ? 'active' : ''; ?>" data-nav="settings/your-profile">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">account_circle</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="menu.profile"><?php echo __('menu.profile'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'settings/login-and-security') ? 'active' : ''; ?>" data-nav="settings/login-and-security">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">lock</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="menu.security"><?php echo __('menu.security'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'settings/accessibility') ? 'active' : ''; ?>" data-nav="settings/accessibility">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">accessibility_new</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="menu.accessibility"><?php echo __('menu.accessibility'); ?></span>
                    </div>
                </div>
            </div>

            <div data-context="help" class="menu-list <?php echo !$isHelpSection ? 'disabled' : ''; ?>">
                <div class="menu-link menu-link-back" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="global.back_home"><?php echo __('global.back_home'); ?></span>
                    </div>
                </div>

                <div style="padding: 8px 12px; font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase;">
                    <span data-lang-key="menu.help"><?php echo __('menu.help'); ?></span>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'help/privacy') ? 'active' : ''; ?>" data-nav="help/privacy">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">privacy_tip</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="help.privacy.title"><?php echo __('help.privacy.title'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'help/terms') ? 'active' : ''; ?>" data-nav="help/terms">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">gavel</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="help.terms.title"><?php echo __('help.terms.title'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'help/cookies') ? 'active' : ''; ?>" data-nav="help/cookies">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">cookie</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="help.cookies.title"><?php echo __('help.cookies.title'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'help/feedback') ? 'active' : ''; ?>" data-nav="help/feedback">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">feedback</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="help.feedback.title"><?php echo __('help.feedback.title'); ?></span>
                    </div>
                </div>
            </div>

            <div data-context="admin" class="menu-list <?php echo !$isAdminSection ? 'disabled' : ''; ?>">
                <div class="menu-link menu-link-back" data-nav="main">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="global.back_home"><?php echo __('global.back_home'); ?></span>
                    </div>
                </div>

                <div style="padding: 8px 12px; font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase;">
                    <span data-lang-key="menu.admin_panel"><?php echo __('menu.admin_panel'); ?></span>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'admin/dashboard') ? 'active' : ''; ?>" data-nav="admin/dashboard">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">dashboard</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="admin.dashboard.title"><?php echo __('admin.dashboard.title'); ?></span>
                    </div>
                </div>

                <div class="menu-link <?php echo ($currentSection === 'admin/users') ? 'active' : ''; ?>" data-nav="admin/users">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">group</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="admin.users.title"><?php echo __('admin.users.title'); ?></span>
                    </div>
                </div>
            </div>

        </div>
        <div class="menu-content-bottom">
            <div data-context="admin-bottom" class="menu-list <?php echo !$isAdminSection ? 'disabled' : ''; ?>">
                    <div class="menu-link <?php echo ($currentSection === 'admin/server') ? 'active' : ''; ?>" data-nav="admin/server">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">dns</span>
                    </div>
                    <div class="menu-link-text">
                        <span data-lang-key="admin.server.title"><?php echo __('admin.server.title'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>