<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<div class="view-content">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.security.title') ?></h1>
            <p class="component-page-description"><?= t('settings.security.desc') ?></p>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item" data-component="password-update-section" style="flex-direction: column; align-items: flex-start;">
                
                <div class="component-card__content" style="width: 100%;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; border: 1px solid #e0e0e0; background-color: #fcfcfc; flex-shrink: 0;">
                        <span class="material-symbols-rounded" style="color: #666; font-size: 20px;">lock</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.security.pass_title') ?></h2>
                        <p class="component-card__description"><?= t('settings.security.pass_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right active w-100" data-state="password-stage-0" style="justify-content: flex-end; margin-top: 8px;">
                    <button type="button" class="component-button" data-action="pass-start-flow">
                        <?= t('settings.security.pass_btn_change') ?>
                    </button>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-1" style="margin-top: 16px;">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input has-action" id="current-password-input" placeholder=" ">
                        <label for="current-password-input" class="component-label-floating"><?= t('settings.security.pass_placeholder_current') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>

                    <div class="component-card__actions actions-right" style="margin-top: 12px;">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= t('settings.security.pass_btn_cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-go-step-2"><?= t('settings.security.pass_btn_continue') ?></button>
                    </div>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-2" style="margin-top: 16px;">
                    <div class="component-input-wrapper" style="margin-bottom: 8px;">
                        <input type="password" class="component-text-input has-action" id="new-password-input" placeholder=" ">
                        <label for="new-password-input" class="component-label-floating"><?= t('settings.security.pass_placeholder_new') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input has-action" id="repeat-password-input" placeholder=" ">
                        <label for="repeat-password-input" class="component-label-floating"><?= t('settings.security.pass_placeholder_repeat') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>

                    <div class="component-card__actions actions-right" style="margin-top: 12px;">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= t('settings.security.pass_btn_cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-submit-final"><?= t('settings.security.pass_btn_save') ?></button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; border: 1px solid #e0e0e0; background-color: #fcfcfc; flex-shrink: 0;">
                        <span class="material-symbols-rounded" style="color: #666; font-size: 20px;">shield</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.security.2fa_title') ?></h2>
                        <p class="component-card__description"><?= t('settings.security.2fa_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button primary" data-nav="/ProjectAurora/settings/2fa-setup">
                        <?= t('settings.security.2fa_btn_setup') ?>
                    </button>
                </div>
            </div>

        </div>

        <div class="component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; border: 1px solid #e0e0e0; background-color: #fcfcfc; flex-shrink: 0;">
                        <span class="material-symbols-rounded" style="color: #666; font-size: 20px;">devices</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.security.devices_title') ?></h2>
                        <p class="component-card__description"><?= t('settings.security.devices_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="/ProjectAurora/settings/devices">
                        <?= t('settings.security.devices_btn_manage') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #d32f2f;"><?= t('settings.security.delete_title') ?></h2>
                        <p class="component-card__description"><?= t('settings.security.delete_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" style="color: #d32f2f; border-color: rgba(211, 47, 47, 0.3);" data-nav="/ProjectAurora/settings/delete-account">
                        <?= t('settings.security.delete_btn') ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>