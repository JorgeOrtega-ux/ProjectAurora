<?php
// includes/sections/settings/2fa-setup.php

require_once __DIR__ . '/../../../api/services/SettingsService.php';

// [CORRECCIÓN] Pasamos $redis al constructor
// La variable $redis ya existe porque viene del extract($services) en public/loader.php
$settingsService = new SettingsService($pdo, $i18n, $_SESSION['user_id'], $redis);

// Verificar límite de seguridad para mostrar/ocultar el QR
$isRateLimited = $settingsService->checkSecurityLimit('2fa_init_attempt', 10, 60);

// Definir clases y estilos basados en el límite
$containerClass = $isRateLimited ? 'disabled-interactive' : '';
$qrBoxStyle = $isRateLimited ? 'display: none;' : '';

$is2FAEnabled = isset($_SESSION['two_factor_enabled']) && (int)$_SESSION['two_factor_enabled'] === 1;
?>

<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper" id="2fa-content-area">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->t('settings.2fa.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->t('settings.2fa.desc'); ?></p>
        </div>

        <?php if ($is2FAEnabled): ?>
            
            <div class="component-card component-card--grouped">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">verified_user</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.protected_title'); ?></h2>
                            <p class="component-card__description">
                                <?php echo $i18n->t('settings.2fa.protected_desc'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="component-card__actions actions-right actions-force-end">
                        <button type="button" class="component-button" id="btn-disable-2fa" style="color: var(--color-error); border-color: rgba(211, 47, 47, 0.3);">
                            <?php echo $i18n->t('settings.2fa.btn_disable'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="component-card component-card--grouped">
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">password</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.recovery.title'); ?></h2>
                            <p class="component-card__description">
                                <?php echo $i18n->t('settings.2fa.recovery.desc'); ?> <br>
                                <span style="font-weight: 500; color: var(--text-primary);">
                                    <?php echo $i18n->t('settings.2fa.recovery.remaining'); ?> <span id="recovery-count-display">...</span>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <button type="button" class="component-button" id="btn-show-regen-area">
                            <?php echo $i18n->t('settings.2fa.recovery.btn_generate'); ?>
                        </button>
                    </div>
                </div>

                <div class="component-group-item component-group-item--stacked disabled" id="regen-confirmation-area">
                    <hr class="component-divider w-100 mb-0">
                    
                    <div class="component-card__content w-100 mt-16">
                        <div class="component-card__text w-100">
                            <p class="component-card__description"><?php echo $i18n->t('settings.2fa.recovery.confirm_pass'); ?></p>
                            
                            <div class="component-input-wrapper mt-16">
                                <input type="password" class="component-text-input" id="regen-password-input" placeholder="<?php echo $i18n->t('auth.field.password'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="component-card__actions actions-right w-100">
                         <button type="button" class="component-button" id="btn-cancel-regen">
                            <?php echo $i18n->t('settings.profile.btn_cancel'); ?>
                        </button>
                        <button type="button" class="component-button primary" id="btn-submit-regen">
                            <?php echo $i18n->t('settings.2fa.recovery.btn_generate'); ?>
                        </button>
                    </div>
                </div>

                 <div class="component-group-item component-group-item--stacked disabled" id="new-codes-area">
                    <hr class="component-divider w-100 mb-0">
                    <div class="component-card__content w-100" style="text-align: center;">
                        <div class="component-card__text w-100">
                            <h2 class="component-card__title" style="color: var(--color-success);"><?php echo $i18n->t('settings.2fa.recovery.new_codes_title'); ?></h2>
                            <p class="component-card__description"><?php echo $i18n->t('settings.2fa.recovery.new_codes_desc'); ?></p>
                        </div>
                        
                        <div id="new-recovery-codes-list" style="background: var(--bg-hover-light); padding: 16px; border-radius: 8px; width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-family: monospace; font-size: 14px; text-align: center; border: 1px solid var(--border-light); margin-top: 16px;"></div>
                    </div>
                 </div>

            </div>
            <?php else: ?>

            <div class="component-card component-card--grouped <?php echo $containerClass; ?>" id="step-qr-container">
                
                <div class="component-accordion-item" data-accordion-id="1">
                    <div class="component-group-item component-accordion-header">
                        <div class="component-card__content">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded">qr_code_scanner</span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.step1_title'); ?></h2>
                                <p class="component-card__description"><?php echo $i18n->t('settings.2fa.step1_desc'); ?></p>
                            </div>
                        </div>
                        <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
                    </div>

                    <div class="component-accordion-content">
                        <hr class="component-divider"> <div class="component-group-item component-group-item--stacked">
                            <div class="component-visual-group">
                                <div class="component-visual-box box-grow">
                                    <ul class="component-step-list">
                                        <li class="component-step-item">
                                            <div class="component-step-icon">1</div>
                                            <span>Descarga <strong>Google Authenticator</strong> o <strong>Authy</strong>.</span>
                                        </li>
                                        <li class="component-step-item">
                                            <div class="component-step-icon">2</div>
                                            <span>Selecciona <strong>"Escanear código QR"</strong>.</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="component-visual-box box-qr" style="<?php echo $qrBoxStyle; ?>">
                                    <div id="qr-container">
                                         <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-accordion-item" data-accordion-id="2">
                    <div class="component-group-item component-accordion-header">
                        <div class="component-card__content">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded">key</span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.step2_title'); ?></h2>
                                <p class="component-card__description"><?php echo $i18n->t('settings.2fa.step2_desc'); ?></p>
                            </div>
                        </div>
                        <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
                    </div>

                    <div class="component-accordion-content">
                        <hr class="component-divider">
                        
                        <div class="component-group-item component-group-item--stacked">
                            
                            <div class="component-card__content">
                                <div class="component-card__text">
                                    <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.step2_inner_title'); ?></h2>
                                    <p class="component-card__description"><?php echo $i18n->t('settings.2fa.step2_inner_desc'); ?></p>
                                </div>
                            </div>

                            <div class="w-100 component-stage-form">
                                <div class="component-input-wrapper component-input-wrapper--floating">
                                    <input type="text" id="manual-secret-input" class="component-text-input has-action" readonly placeholder="Cargando...">
                                    <label for="manual-secret-input" class="component-label-floating">Clave de configuración</label>
                                    <button type="button" class="component-input-action" data-action="copy-input" data-target="manual-secret-input" title="Copiar">
                                        <span class="material-symbols-rounded">content_copy</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-accordion-item" data-accordion-id="3">
                    <div class="component-group-item component-accordion-header">
                        <div class="component-card__content">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded">lock_clock</span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.step3_title'); ?></h2>
                                <p class="component-card__description"><?php echo $i18n->t('settings.2fa.step3_desc'); ?></p>
                            </div>
                        </div>
                        <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
                    </div>

                    <div class="component-accordion-content">
                        <hr class="component-divider">
                        
                        <div class="component-group-item component-group-item--stacked">
                            
                            <div class="component-card__content">
                                <div class="component-card__text">
                                    <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.step3_inner_title'); ?></h2>
                                    <p class="component-card__description"><?php echo $i18n->t('settings.2fa.step3_inner_desc'); ?></p>
                                </div>
                            </div>

                            <div class="w-100 component-stage-form">
                                <div class="component-input-wrapper">
                                    <input type="text" id="input-2fa-verify" class="component-text-input" placeholder="000 000" maxlength="7" style="font-family: monospace; letter-spacing: 2px; font-size: 16px; text-align: center;">
                                </div>
                            </div>
                            
                            <div class="component-card__actions actions-right w-100">
                                <button type="button" class="component-button primary w-100" id="btn-confirm-2fa">
                                    <?php echo $i18n->t('settings.2fa.btn_verify'); ?>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="component-card component-card--grouped disabled" id="step-success">
                <div class="component-group-item component-group-item--stacked" style="align-items: center; text-align: center;">
                    <div class="component-card__icon-container component-card__icon-container--bordered" style="color: var(--color-success); background: var(--color-success-bg); width: 64px; height: 64px;">
                        <span class="material-symbols-rounded" style="font-size: 32px;">check</span>
                    </div>
                    
                    <div class="component-card__text" style="align-items: center;">
                        <h2 class="component-card__title" style="font-size: 18px;"><?php echo $i18n->t('settings.2fa.success_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.2fa.success_desc'); ?></p>
                    </div>

                    <div id="recovery-codes-list" style="background: var(--bg-hover-light); padding: 16px; border-radius: 8px; width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-family: monospace; font-size: 14px; text-align: center; border: 1px solid var(--border-light);"></div>

                    <div class="component-card__actions w-100">
                        <button type="button" class="component-button primary w-100" onclick="window.location.reload()">
                            <?php echo $i18n->t('settings.2fa.btn_finish'); ?>
                        </button>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>