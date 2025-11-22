<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="section-content active" data-section="settings/change-password">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.change_password.title"><?php echo trans('settings.change_password.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.change_password.description"><?php echo trans('settings.change_password.description'); ?></p>
        </div>

        <div class="component-card active" data-step="password-step-1" style="flex-direction: column; gap: 0;">
            
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 8px;">
                <div class="component-icon-container">
                    <span class="material-symbols-rounded">lock</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.change_password.current_label"><?php echo trans('settings.change_password.current_label'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.change_password.current_desc"><?php echo trans('settings.change_password.current_desc'); ?></p>
                </div>
            </div>

            <div style="width: 100%; margin-bottom: 8px;">
                <input type="password" class="component-text-input" data-element="current-password" placeholder="********" style="width: 100%; padding-left: 12px;">
            </div>

            <div style="display: flex; justify-content: flex-end; width: 100%;">
                <button type="button" class="component-button primary" data-action="verify-current-password" data-i18n="settings.change_password.next_btn">
                    <?php echo trans('settings.change_password.next_btn'); ?>
                </button>
            </div>
        </div>

        <div class="component-card disabled" data-step="password-step-2" style="flex-direction: column; gap: 24px;">
            
            <div style="display: flex; flex-direction: column;">
                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 8px;">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">vpn_key</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.change_password.new_label"><?php echo trans('settings.change_password.new_label'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.new_desc"><?php echo trans('settings.change_password.new_desc'); ?></p>
                    </div>
                </div>
                <div style="width: 100%;">
                    <input type="password" class="component-text-input" data-element="new-password" placeholder="********" style="width: 100%;">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; width: 100%; margin: 0;">

            <div style="display: flex; flex-direction: column;">
                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 8px;">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">check_circle</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-i18n="settings.change_password.confirm_label"><?php echo trans('settings.change_password.confirm_label'); ?></h2>
                        <p class="component-card__description" data-i18n="settings.change_password.confirm_desc"><?php echo trans('settings.change_password.confirm_desc'); ?></p>
                    </div>
                </div>
                <div style="width: 100%;">
                    <input type="password" class="component-text-input" data-element="confirm-password" placeholder="********" style="width: 100%;">
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode disabled" data-step="password-step-2-sessions">
            <div class="component-card__content">
                <div class="component-icon-container">
                    <span class="material-symbols-rounded">devices_other</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.change_password.sessions_check_title"><?php echo trans('settings.change_password.sessions_check_title'); ?></h2>
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

        <div class="component-card__actions actions-right disabled" data-step="password-step-2-actions" style="justify-content: flex-end; margin-top: 8px;">
            <button type="button" class="component-button primary" data-action="save-new-password" data-i18n="global.save">
                <?php echo trans('global.save'); ?>
            </button>
        </div>

    </div>
</div>