<?php
// includes/sections/settings/login-security.php
?>
<div class="section-content active" data-section="settings/login-and-security">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?= __('settings.security.title') ?></h1>
            <p class="component-page-description"><?= __('settings.security.desc') ?></p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="password-update-section">
                
                <div class="component-card__content">
                    <div class="component-card__profile-picture" style="background:transparent;">
                        <span class="material-symbols-rounded" style="font-size: 32px; color: #000;">lock</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('settings.security.pass_title') ?></h2>
                        <p class="component-card__description"><?= __('settings.security.pass_desc') ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="password-stage-0">
                    <button type="button" class="component-button" data-action="pass-start-flow">
                        <?= __('settings.security.btn_update') ?>
                    </button>
                </div>

                <div class="disabled w-100 component-stage-form" data-state="password-stage-1">
                    
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" 
                               id="current-password-input"
                               placeholder="<?= __('settings.security.current_pass_ph') ?>">
                    </div>

                    <div class="component-card__actions actions-right">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= __('global.cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-go-step-2"><?= __('global.continue') ?></button>
                    </div>
                </div>

                <div class="disabled w-100 component-stage-form" data-state="password-stage-2">
                    
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" 
                               id="new-password-input"
                               placeholder="<?= __('settings.security.new_pass_ph') ?>">
                    </div>

                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" 
                               id="repeat-password-input"
                               placeholder="<?= __('settings.security.repeat_pass_ph') ?>">
                    </div>

                    <div class="component-card__actions actions-right">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?= __('global.cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="pass-submit-final"><?= __('global.continue') ?></button>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>