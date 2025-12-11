<?php
// includes/sections/settings/2fa-setup.php

$is2faEnabled = false;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is2faEnabled = (bool)$stmt->fetchColumn();
}
?>
<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title"><?= __('settings.2fa.setup_title') ?></h1>
            <p class="component-page-description"><?= __('settings.2fa.setup_desc') ?></p>
        </div>

        <div class="component-card component-card--grouped" id="2fa-wizard-container">
            
            <?php if ($is2faEnabled): ?>
                <div class="component-group-item component-group-item--stacked wizard-step-container" style="justify-content: center;">
                    <div class="wizard-icon-container success">
                        <span class="material-symbols-rounded">check_circle</span>
                    </div>
                    
                    <div class="component-card__text text-center">
                        <h2 class="component-card__title"><?= __('settings.2fa.enabled_title') ?></h2>
                        <p class="component-card__description"><?= __('settings.2fa.enabled_desc') ?></p>
                    </div>
                    
                    <div class="component-input-wrapper input-small-center">
                        <input class="component-text-input" id="disable-2fa-password" type="password" placeholder="<?= __('settings.security.current_pass_ph') ?>">
                    </div>
                    
                    <div class="component-card__actions centered-actions">
                         <button class="component-button" data-nav="settings/login-and-security"><?= __('global.cancel') ?></button>
                         <button class="component-button danger" id="btn-disable-2fa"><?= __('settings.2fa.confirm_disable') ?></button>
                    </div>
                </div>

            <?php else: ?>
                
                <div class="component-group-item component-group-item--stacked active" id="step-1-auth">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('settings.2fa.step1_title') ?></h2>
                        <p class="component-card__description">
                            <?= __('settings.2fa.step1_desc') ?>
                        </p>
                    </div>
                    
                    <div class="component-input-wrapper">
                        <input class="component-text-input" id="setup-2fa-password" type="password" placeholder="<?= __('settings.security.current_pass_ph') ?>">
                    </div>

                    <div class="component-card__actions actions-right">
                        <button class="component-button" data-nav="settings/login-and-security"><?= __('global.cancel') ?></button>
                        <button class="component-button primary" id="btn-start-2fa-setup"><?= __('global.continue') ?></button>
                    </div>
                </div>

                <div class="component-group-item component-group-item--stacked wizard-step-container disabled" id="step-2-qr">
                    <div class="component-card__text text-center">
                        <h2 class="component-card__title"><?= __('settings.2fa.step2_title') ?></h2>
                        <p class="component-card__description">
                            <?= __('settings.2fa.step2_desc') ?>
                        </p>
                    </div>
                    
                    <div class="qr-display-area">
                        <div id="qr-code-container"></div>
                    </div>

                    <div class="wizard-manual-entry">
                        <p><?= __('settings.2fa.manual_entry') ?></p>
                        <strong id="secret-text"></strong>
                    </div>

                    <div class="component-input-wrapper input-small-center">
                        <input class="component-text-input text-center-input" id="verify-2fa-code" type="text" placeholder="000 000" maxlength="6">
                    </div>

                    <div class="component-card__actions centered-actions">
                        <button class="component-button primary" id="btn-verify-2fa-setup"><?= __('settings.2fa.verify_btn') ?></button>
                    </div>
                </div>

                <div class="component-group-item component-group-item--stacked wizard-step-container disabled" id="step-3-backup">
                    <div class="wizard-icon-container success">
                        <span class="material-symbols-rounded">verified</span>
                    </div>

                    <div class="component-card__text text-center">
                        <h2 class="component-card__title"><?= __('settings.2fa.success_title') ?></h2>
                        <p class="component-card__description">
                            <?= __('settings.2fa.success_desc') ?>
                        </p>
                    </div>

                    <div class="backup-codes-grid" id="backup-codes-list">
                        </div>

                    <div class="component-card__actions centered-actions">
                        <button class="component-button primary" data-nav="settings/login-and-security"><?= __('settings.2fa.finish_btn') ?></button>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>