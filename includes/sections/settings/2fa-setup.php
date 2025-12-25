<?php
// includes/sections/settings/2fa-setup.php
$is2FAEnabled = isset($_SESSION['two_factor_enabled']) && (int)$_SESSION['two_factor_enabled'] === 1;
?>

<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->t('settings.2fa.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->t('settings.2fa.desc'); ?></p>
        </div>

        <div id="2fa-content-area">
            <?php if ($is2FAEnabled): ?>
                
                <div class="component-card">
                    <div style="text-align: center; padding: 20px;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: var(--color-success);">verified_user</span>
                        <h2 class="component-card__title" style="font-size: 18px; margin-top: 16px;"><?php echo $i18n->t('settings.2fa.protected_title'); ?></h2>
                        <p class="component-card__description" style="margin-top: 8px;">
                            <?php echo $i18n->t('settings.2fa.protected_desc'); ?>
                        </p>
                        <br>
                        <button type="button" class="component-button" id="btn-disable-2fa" style="width: 100%; justify-content: center; color: var(--color-error); border-color: var(--color-error-bg);">
                            <?php echo $i18n->t('settings.2fa.btn_disable'); ?>
                        </button>
                    </div>
                </div>

            <?php else: ?>

                <div id="step-qr" class="active" style="display: flex; flex-direction: column; gap: 16px;">
                    
                    <div class="component-card">
                        <div class="component-group-item" style="padding: 0;">
                            <div class="component-card__content">
                                <div class="component-card__icon-container component-card__icon-container--bordered">
                                    <span class="material-symbols-rounded">qr_code_scanner</span>
                                </div>
                                <div class="component-card__text">
                                    <p class="component-card__description" style="color: var(--text-primary); font-weight: 500;">
                                        <?php echo $i18n->t('settings.2fa.step_qr'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="component-card">
                        <div class="component-flexible-row">
                            
                            <div class="component-flexible-row__content">
                                
                                <div class="component-card__content" style="align-items: flex-start;">
                                    <div class="component-card__icon-container component-card__icon-container--bordered component-card__icon-container--small">
                                        <span class="material-symbols-rounded">key</span>
                                    </div>
                                    <div class="component-card__text">
                                        <span class="component-card__title" style="font-size: 14px;">Manual Entry Key</span>
                                        <p class="component-card__description">Si no puedes escanear el código QR.</p>
                                    </div>
                                </div>
                                
                                <div class="component-input-wrapper component-input-wrapper--floating">
                                    <input type="text" id="manual-secret-input" class="component-text-input has-action" readonly placeholder=" ">
                                    <label for="manual-secret-input" class="component-label-floating">Código Manual</label>
                                    <button type="button" class="component-input-action" data-action="copy-input" data-target="manual-secret-input" title="Copiar código">
                                        <span class="material-symbols-rounded">content_copy</span>
                                    </button>
                                </div>
                            </div>

                            <div id="qr-container" class="component-flexible-row__side">
                                <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="component-card component-card--grouped">
                        <div class="component-group-item component-group-item--stacked">
                            
                            <div class="component-card__content w-100">
                                <div class="component-card__icon-container component-card__icon-container--bordered">
                                    <span class="material-symbols-rounded">lock</span>
                                </div>
                                <div class="component-card__text">
                                    <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.step_code'); ?></h2>
                                    <p class="component-card__description">Google Authenticator / Authy</p>
                                </div>
                            </div>

                            <div class="w-100 mt-16">
                                <div class="component-input-wrapper">
                                    <input type="text" id="input-2fa-verify" class="component-text-input input-code-2fa" placeholder="000 000" maxlength="7">
                                </div>
                            </div>

                            <div class="component-card__actions actions-right w-100 mt-16">
                                <button type="button" class="component-button primary" id="btn-confirm-2fa">
                                    <?php echo $i18n->t('settings.2fa.btn_verify'); ?>
                                </button>
                            </div>

                        </div>
                    </div>

                </div>

                <div id="step-success" class="disabled component-card">
                    <div style="text-align: center; padding: 20px;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: var(--color-success);">check_circle</span>
                        <h2 class="component-card__title" style="margin-top: 16px;"><?php echo $i18n->t('settings.2fa.success_title'); ?></h2>
                        <p class="component-card__description" style="margin-top: 8px;">
                            <?php echo $i18n->t('settings.2fa.success_desc'); ?>
                        </p>
                        
                        <div id="recovery-codes-list" style="background: var(--bg-hover-light); padding: 16px; border-radius: 8px; margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-family: monospace; font-size: 14px; text-align: center;"></div>
                        
                        <br>
                        <button type="button" class="component-button primary" onclick="window.location.reload()" style="width: 100%; justify-content: center;">
                            <?php echo $i18n->t('settings.2fa.btn_finish'); ?>
                        </button>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>