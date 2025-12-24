<?php
$is2FAEnabled = isset($_SESSION['two_factor_enabled']) && (int)$_SESSION['two_factor_enabled'] === 1;
?>

<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->t('settings.2fa.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->t('settings.2fa.desc'); ?></p>
        </div>

        <div class="component-card" id="2fa-wizard-container">
            
            <?php if ($is2FAEnabled): ?>
                <div style="text-align: center; padding: 20px;">
                    <span class="material-symbols-rounded" style="font-size: 48px; color: #2e7d32;">verified_user</span>
                    <h2 class="component-card__title" style="font-size: 18px; margin-top: 16px;"><?php echo $i18n->t('settings.2fa.protected_title'); ?></h2>
                    <p class="component-card__description" style="margin-top: 8px;">
                        <?php echo $i18n->t('settings.2fa.protected_desc'); ?>
                    </p>
                    <br>
                    <button type="button" class="component-button" id="btn-disable-2fa" style="width: 100%; justify-content: center; color: #d32f2f; border-color: #d32f2f50;">
                        <?php echo $i18n->t('settings.2fa.btn_disable'); ?>
                    </button>
                </div>

            <?php else: ?>
                <div id="step-intro" class="active">
                    <div style="text-align: center; padding: 20px;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: #000;">security</span>
                        <h2 class="component-card__title" style="font-size: 18px; margin-top: 16px;"><?php echo $i18n->t('settings.2fa.setup_title'); ?></h2>
                        <p class="component-card__description" style="margin-top: 8px;">
                            <?php echo $i18n->t('settings.2fa.setup_desc'); ?>
                        </p>
                        <br>
                        <button type="button" class="component-button primary" id="btn-start-2fa" style="width: 100%; justify-content: center;">
                            <?php echo $i18n->t('settings.2fa.btn_start'); ?>
                        </button>
                    </div>
                </div>

                <div id="step-qr" class="disabled">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
                        <p class="component-card__description" style="text-align: center;">
                            <?php echo $i18n->t('settings.2fa.step_qr'); ?>
                        </p>
                        
                        <div id="qr-container" style="border: 1px solid #eee; padding: 8px; border-radius: 8px;">
                            <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                        </div>

                        <div style="width: 100%; border-top: 1px solid #eee; margin-top: 8px; padding-top: 16px;">
                            <p class="component-card__description" style="margin-bottom: 8px;"><?php echo $i18n->t('settings.2fa.step_code'); ?></p>
                            <div class="component-input-wrapper">
                                <input type="text" id="input-2fa-verify" class="component-text-input" placeholder="000000" maxlength="6" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
                            </div>
                            <br>
                            <button type="button" class="component-button primary" id="btn-confirm-2fa" style="width: 100%; justify-content: center;">
                                <?php echo $i18n->t('settings.2fa.btn_verify'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="step-success" class="disabled">
                    <div style="text-align: center;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: #2e7d32;">check_circle</span>
                        <h2 class="component-card__title" style="margin-top: 16px;"><?php echo $i18n->t('settings.2fa.success_title'); ?></h2>
                        <p class="component-card__description" style="margin-top: 8px; color: #d32f2f;">
                            <?php echo $i18n->t('settings.2fa.success_desc'); ?>
                        </p>
                    </div>
                    
                    <div id="recovery-codes-list" style="background: #f5f5fa; padding: 16px; border-radius: 8px; margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-family: monospace; font-size: 16px; text-align: center;"></div>

                    <br>
                    <button type="button" class="component-button" onclick="window.location.reload()" style="width: 100%; justify-content: center;">
                        <?php echo $i18n->t('settings.2fa.btn_finish'); ?>
                    </button>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>