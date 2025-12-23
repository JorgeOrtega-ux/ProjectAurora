<?php
// includes/sections/settings/login-security.php
$is2FA = isset($_SESSION['two_factor_enabled']) && $_SESSION['two_factor_enabled'] == 1;

// Traducimos el estado
$textEnabled = $i18n->trans('settings.security.2fa_enabled');
$textDisabled = $i18n->trans('settings.security.2fa_disabled');

$statusText = $is2FA 
    ? "<span style='color:#2e7d32; font-weight:500;'>$textEnabled</span>" 
    : $textDisabled;

$btnText = $is2FA 
    ? $i18n->trans('settings.security.btn_config') 
    : $i18n->trans('settings.security.btn_activate');
?>

<div class="section-content active" data-section="settings/login-security">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->trans('settings.security.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->trans('settings.security.desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="password-update-section">
                
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">lock</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->trans('settings.security.pass_title'); ?></h2>
                        <p class="component-card__description" style="color: #666;"><?php echo $i18n->trans('settings.security.pass_desc'); ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="password-stage-0">
                    <button type="button" class="component-button" data-action="pass-start-flow">
                        <?php echo $i18n->trans('settings.security.btn_change_pass'); ?>
                    </button>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-1">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" id="current-password-input" placeholder="<?php echo $i18n->trans('settings.security.field_current'); ?>">
                    </div>

                    <div class="component-card__actions actions-right actions-force-end">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?php echo $i18n->trans('settings.profile.btn_cancel'); ?></button>
                        <button type="button" class="component-button primary" data-action="pass-go-step-2"><?php echo $i18n->trans('auth.btn.continue'); ?></button>
                    </div>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-2">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" id="new-password-input" placeholder="<?php echo $i18n->trans('settings.security.field_new'); ?>">
                    </div>
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" id="repeat-password-input" placeholder="<?php echo $i18n->trans('settings.security.field_repeat'); ?>">
                    </div>

                    <div class="component-card__actions actions-right actions-force-end">
                        <button type="button" class="component-button" data-action="pass-cancel-flow"><?php echo $i18n->trans('settings.profile.btn_cancel'); ?></button>
                        <button type="button" class="component-button primary" data-action="pass-submit-final"><?php echo $i18n->trans('settings.profile.btn_save'); ?></button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">shield</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->trans('settings.security.2fa_title'); ?></h2>
                        <p class="component-card__description">
                            <?php echo $statusText; ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button <?php echo $is2FA ? '' : 'primary'; ?>" data-nav="settings/2fa-setup">
                        <?php echo $btnText; ?>
                    </button>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">devices</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->trans('settings.security.devices_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->trans('settings.security.devices_desc'); ?></p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="settings/devices">
                        <?php echo $i18n->trans('settings.security.btn_manage'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #d32f2f;"><?php echo $i18n->trans('settings.security.delete_title'); ?></h2>
                        <p class="component-card__description">
                            <?php echo $i18n->trans('settings.security.delete_desc'); ?>
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" style="color: #d32f2f; border-color: rgba(211, 47, 47, 0.3);" data-nav="settings/delete-account">
                        <?php echo $i18n->trans('settings.security.btn_delete'); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>