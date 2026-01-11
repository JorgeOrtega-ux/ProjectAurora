<?php
// includes/menus/settings.php

// Verificar sesión para determinar el orden y las opciones
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<div id="menu-content-settings" class="menu-content" style="display: flex;">
    <div class="menu-content-top">
        <div class="menu-list">
            <div class="menu-link" data-nav="main" style="border: 1px solid #00000020;">
                <div class="menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                <div class="menu-link-text"><span><?php echo __('menu.back_home'); ?></span></div>
            </div>
            
            <div style="width: 100%; height: 1px; background-color: #eee; margin: 4px 0 8px 0;"></div>

            <?php if ($isLoggedIn): ?>
                
                <div class="menu-link <?php echo ($currentSection ?? '') === 'settings/profile' ? 'active' : ''; ?>" data-nav="settings/profile">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                    <div class="menu-link-text"><span><?php echo __('menu.profile'); ?></span></div>
                </div>

                <div class="menu-link <?php echo ($currentSection ?? '') === 'settings/security' ? 'active' : ''; ?>" data-nav="settings/security">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">security</span></div>
                    <div class="menu-link-text"><span><?php echo __('menu.security'); ?></span></div>
                </div>

                <div class="menu-link <?php echo ($currentSection ?? '') === 'settings/preferences' ? 'active' : ''; ?>" data-nav="settings/preferences">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">tune</span></div>
                    <div class="menu-link-text"><span><?php echo __('menu.preferences'); ?></span></div>
                </div>

            <?php else: ?>

                <div class="menu-link <?php echo ($currentSection ?? '') === 'settings/preferences' ? 'active' : ''; ?>" data-nav="settings/preferences">
                    <div class="menu-link-icon"><span class="material-symbols-rounded">tune</span></div>
                    <div class="menu-link-text"><span><?php echo __('menu.preferences'); ?></span></div>
                </div>

            <?php endif; ?>
        </div>
    </div>
    <div class="menu-content-bottom"></div>
</div>