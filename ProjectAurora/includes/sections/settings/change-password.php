<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="section-content active" data-section="settings/change-password">
    <div class="component-wrapper">

        <div class="component-header-card" style="position: relative; text-align: center;">
            <div style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%);">
                <a href="#" onclick="event.preventDefault(); navigateTo('settings/login-security')" style="color:#666; text-decoration:none; display:flex; align-items:center; width: 40px; height: 40px; justify-content: center; border-radius: 50%; transition: background 0.2s;">
                    <span class="material-symbols-rounded">arrow_back</span>
                </a>
            </div>
            <h1 class="component-page-title" data-i18n="settings.change_password.title"><?php echo trans('settings.change_password.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.change_password.description"><?php echo trans('settings.change_password.description'); ?></p>
        </div>

        <div class="component-card active" data-step="password-step-1" style="flex-direction: column; gap: 0;">
            
            <div class="component-card__content" style="flex-direction: column; align-items: flex-start; width: 100%; gap: 0;">
                <div class="component-card__text" style="width: 100%; margin-bottom: 16px;">
                    <h2 class="component-card__title" style="font-size: 16px; margin-bottom: 4px;" data-i18n="settings.change_password.current_label"><?php echo trans('settings.change_password.current_label'); ?></h2>
                    <p class="component-card__description" style="font-size: 14px; line-height: 1.4; color: #666;" data-i18n="settings.change_password.current_desc"><?php echo trans('settings.change_password.current_desc'); ?></p>
                </div>
                
                <div class="input-with-actions" style="width: 100%;">
                    <input type="password" class="component-text-input" data-element="current-password" style="width: 100%;" placeholder="********">
                </div>
            </div>
            
            <div class="component-card__actions actions-right" style="width: 100%; margin-top: 20px; justify-content: flex-end; display: flex;">
                <button type="button" class="component-button primary" data-action="verify-current-password" data-i18n="settings.change_password.next_btn">
                    <?php echo trans('settings.change_password.next_btn'); ?>
                </button>
            </div>
        </div>

        <div class="component-card disabled" data-step="password-step-2" style="flex-direction: column; gap: 0;">
            
            <div class="component-card__content" style="flex-direction: column; align-items: flex-start; width: 100%; gap: 0;">
                
                <div style="width: 100%; margin-bottom: 24px;">
                    <div class="component-card__text" style="margin-bottom: 8px;">
                        <h2 class="component-card__title" style="font-size: 16px;" data-i18n="settings.change_password.new_label"><?php echo trans('settings.change_password.new_label'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.new_desc"><?php echo trans('settings.change_password.new_desc'); ?></p>
                    </div>
                    <div class="input-with-actions" style="width: 100%;">
                        <input type="password" class="component-text-input" data-element="new-password" style="width: 100%;" placeholder="********">
                    </div>
                </div>

                <div style="width: 100%;">
                    <div class="component-card__text" style="margin-bottom: 8px;">
                        <h2 class="component-card__title" style="font-size: 16px;" data-i18n="settings.change_password.confirm_label"><?php echo trans('settings.change_password.confirm_label'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.confirm_desc"><?php echo trans('settings.change_password.confirm_desc'); ?></p>
                    </div>
                    <div class="input-with-actions" style="width: 100%;">
                        <input type="password" class="component-text-input" data-element="confirm-password" style="width: 100%;" placeholder="********">
                    </div>
                </div>

            </div>
        </div>

        <div class="component-card component-card--edit-mode disabled" data-step="password-step-2-sessions">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" style="font-size: 15px;" data-i18n="settings.change_password.sessions_check_title"><?php echo trans('settings.change_password.sessions_check_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.change_password.sessions_check_desc"><?php echo trans('settings.change_password.sessions_check_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" data-element="logout-others-check">
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="component-card__actions actions-right disabled" data-step="password-step-2-actions" style="width: 100%; justify-content: flex-end; margin-top: 16px;">
            <button type="button" class="component-button primary" data-action="save-new-password" data-i18n="global.save">
                <?php echo trans('global.save'); ?>
            </button>
        </div>

    </div>
</div>