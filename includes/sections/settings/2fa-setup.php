<?php
// includes/sections/settings/2fa-setup.php
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
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered" style="color: var(--color-success); background: var(--color-success-bg);">
                            <span class="material-symbols-rounded">verified_user</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?php echo $i18n->t('settings.2fa.protected_title'); ?></h2>
                            <p class="component-card__description">
                                <?php echo $i18n->t('settings.2fa.protected_desc'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                            <div class="component-card__text">
                            <p class="component-card__description">
                                Para desactivar la protección, pulsa el botón.
                            </p>
                            </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <button type="button" class="component-button" id="btn-disable-2fa" style="color: var(--color-error); border-color: rgba(211, 47, 47, 0.3);">
                            <?php echo $i18n->t('settings.2fa.btn_disable'); ?>
                        </button>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="component-card component-card--grouped active" id="step-qr">
                
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">qr_code_scanner</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Configurar aplicación</h2>
                            <p class="component-card__description">Sigue los pasos para vincular tu dispositivo.</p>
                        </div>
                    </div>
                    
                    <div class="component-visual-group">
                        
                        <div class="component-visual-box box-grow">
                            <ul class="visual-step-list">
                                <li class="visual-step-item">
                                    <div class="visual-step-icon">1</div>
                                    <span>Descarga <strong>Google Authenticator</strong> o <strong>Authy</strong> en tu móvil.</span>
                                </li>
                                <li class="visual-step-item">
                                    <div class="visual-step-icon">2</div>
                                    <span>Selecciona la opción <strong>"Escanear código QR"</strong> en la app.</span>
                                </li>
                                <li class="visual-step-item">
                                    <div class="visual-step-icon">3</div>
                                    <span>Apunta tu cámara al código de la derecha.</span>
                                </li>
                            </ul>
                        </div>

                        <div class="component-visual-box box-qr">
                            <div id="qr-container">
                                 <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">key</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Configuración manual</h2>
                            <p class="component-card__description">Si no puedes escanear el código QR, introduce esta clave en tu aplicación.</p>
                        </div>
                    </div>

                    <div class="w-100 component-stage-form active">
                        <div class="component-input-wrapper component-input-wrapper--floating">
                            <input type="text" id="manual-secret-input" class="component-text-input has-action" readonly placeholder="Cargando...">
                            <label for="manual-secret-input" class="component-label-floating">Clave de configuración</label>
                            <button type="button" class="component-input-action" data-action="copy-input" data-target="manual-secret-input" title="Copiar">
                                <span class="material-symbols-rounded">content_copy</span>
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">lock_clock</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Verificar código</h2>
                            <p class="component-card__description">Ingresa el código de 6 dígitos generado.</p>
                        </div>
                    </div>

                    <div class="w-100 component-stage-form active">
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

                    <div class="component-card__actions w-100 mt-16">
                        <button type="button" class="component-button primary w-100" onclick="window.location.reload()">
                            <?php echo $i18n->t('settings.2fa.btn_finish'); ?>
                        </button>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>