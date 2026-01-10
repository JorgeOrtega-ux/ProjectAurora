<div id="menu-content-help" class="menu-content" style="display: flex;">
    <div class="menu-content-top">
        <div class="menu-list">
            <div class="menu-link" data-nav="main" style="margin-bottom: 8px; border-bottom: 1px solid #eee;">
                <div class="menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.back_home'); ?></span></div>
            </div>
            
            <div class="menu-link <?php echo ($currentSection ?? '') === 'help' ? 'active' : ''; ?>" data-nav="help">
                <div class="menu-link-icon"><span class="material-symbols-rounded">help_center</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.help_center'); ?></span></div>
            </div>

            <div class="menu-link <?php echo ($currentSection ?? '') === 'privacy' ? 'active' : ''; ?>" data-nav="privacy">
                <div class="menu-link-icon"><span class="material-symbols-rounded">lock</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.privacy'); ?></span></div>
            </div>

            <div class="menu-link <?php echo ($currentSection ?? '') === 'terms' ? 'active' : ''; ?>" data-nav="terms">
                <div class="menu-link-icon"><span class="material-symbols-rounded">gavel</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.terms'); ?></span></div>
            </div>

            <div class="menu-link <?php echo ($currentSection ?? '') === 'cookies' ? 'active' : ''; ?>" data-nav="cookies">
                <div class="menu-link-icon"><span class="material-symbols-rounded">cookie</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.cookies'); ?></span></div>
            </div>

            <div class="menu-link <?php echo ($currentSection ?? '') === 'feedback' ? 'active' : ''; ?>" data-nav="feedback">
                <div class="menu-link-icon"><span class="material-symbols-rounded">chat</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.feedback'); ?></span></div>
            </div>
        </div>
    </div>
    <div class="menu-content-bottom"></div>
</div>