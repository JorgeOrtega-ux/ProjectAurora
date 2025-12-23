<div class="module-content module-surface body-text disabled" data-module="moduleSurface">
    <div class="menu-content">
        <div class="menu-content-top">
            
            <div class="menu-list" id="nav-main">
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

            <div class="menu-list" id="nav-settings" style="display: none;">
                
                <div class="menu-link" data-nav="main" style="border: 1px solid #00000020;">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.back_home'); ?></div>
                </div>

                <div style="width: 100%; border-bottom: 1px solid #00000020; margin: 8px 0;"></div>

                <div class="menu-link" data-nav="settings/your-profile">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">person</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.profile'); ?></div>
                </div>

                <div class="menu-link" data-nav="settings/login-security">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">lock</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.security'); ?></div>
                </div>

                <div class="menu-link" data-nav="settings/accessibility">
                    <div class="menu-link-icon">
                        <span class="material-symbols-rounded">accessibility_new</span>
                    </div>
                    <div class="menu-link-text"><?php echo $i18n->t('menu.accessibility'); ?></div>
                </div>
            </div>

        </div>
        <div class="menu-content-bottom"></div>
    </div>
</div>