<?php
// includes/views/settings-2fa.php
if (session_status() === PHP_SESSION_NONE) session_start();
$is2FAEnabled = false;
if (isset($_SESSION['user_id'])) {
    global $dbConnection;
    if ($dbConnection) {
        $stmt = $dbConnection->prepare("SELECT two_factor_enabled FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $is2FAEnabled = (bool)$stmt->fetchColumn();
    }
}
?>
<div class="view-content" id="2fa-view-container">
    <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
    
    <div class="component-wrapper" id="2fa-content-area">
        <div class="component-header-card">
            <h1 class="component-page-title">Autenticación de dos factores (2FA)</h1>
            <p class="component-page-description">Protege tu cuenta añadiendo una capa adicional de seguridad con tu dispositivo móvil.</p>
        </div>

        <?php if ($is2FAEnabled): ?>
            
            <div class="component-card component-card--grouped">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded" style="color: #16a34a;">verified_user</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Tu cuenta está protegida</h2>
                            <p class="component-card__description">La autenticación de dos factores está actualmente activada.</p>
                        </div>
                    </div>
                    
                    <div class="component-card__actions actions-right w-100" style="justify-content: flex-end;">
                        <button type="button" class="component-button" id="btn-disable-2fa" style="color: #d32f2f; border-color: rgba(211, 47, 47, 0.3);">
                            Desactivar 2FA
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
                            <h2 class="component-card__title">Códigos de recuperación</h2>
                            <p class="component-card__description">
                                Estos códigos te permiten recuperar tu cuenta si pierdes acceso a tu dispositivo.<br>
                                <span style="font-weight: 500; color: #1a1a1a;">
                                    Códigos restantes: <span id="recovery-count-display">...</span>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <button type="button" class="component-button" id="btn-show-regen-area">
                            Generar nuevos códigos
                        </button>
                    </div>
                </div>

                <div id="regen-confirmation-area" class="disabled" style="flex-direction: column; width: 100%;">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content w-100">
                            <div class="component-card__text w-100">
                                <h2 class="component-card__title">Confirmar contraseña</h2>
                                <p class="component-card__description">Por razones de seguridad, ingresa tu contraseña para generar nuevos códigos.</p>
                                <div class="component-input-wrapper mt-16">
                                    <input type="password" class="component-text-input" id="regen-password-input" placeholder=" ">
                                    <label for="regen-password-input" class="component-label-floating">Contraseña</label>
                                </div>
                            </div>
                        </div>
                        <div class="component-card__actions actions-right w-100">
                             <button type="button" class="component-button" id="btn-cancel-regen">Cancelar</button>
                            <button type="button" class="component-button primary" id="btn-submit-regen">Generar códigos</button>
                        </div>
                    </div>
                </div>

                <div id="new-codes-area" class="disabled" style="flex-direction: column; width: 100%;">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content w-100">
                            <div class="component-card__text w-100">
                                <h2 class="component-card__title" style="color: #16a34a;">Nuevos códigos generados</h2>
                                <p class="component-card__description">Guarda estos códigos en un lugar seguro. Tus códigos anteriores ya no son válidos.</p>
                            </div>
                        </div>
                        <div id="new-recovery-codes-list" class="component-chip-grid w-100"></div>
                    </div>
                </div>

            </div>
        <?php else: ?>

            <div id="step-qr-container" class="w-100" style="display: flex; flex-direction: column; gap: 16px;">
                
                <div class="component-card component-card--grouped component-accordion-item" data-accordion-id="1" style="margin-bottom: 0; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div class="component-group-item component-accordion-header">
                        <div class="component-card__content">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded">qr_code_scanner</span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title">Paso 1: Escanear código QR</h2>
                                <p class="component-card__description">Usa una aplicación de autenticación para escanear este código.</p>
                            </div>
                        </div>
                        <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
                    </div>

                    <div class="component-accordion-content">
                        <hr class="component-divider" style="margin-bottom: 16px;"> 
                        <div class="component-group-item component-group-item--stacked" style="padding: 0;">
                            <div class="component-visual-group">
                                <div class="component-visual-box box-grow">
                                    <ul class="component-step-list">
                                        <li class="component-step-item">
                                            <div class="component-step-icon">1</div>
                                            <span>Descarga <strong>Google Authenticator</strong> o <strong>Authy</strong>.</span>
                                        </li>
                                        <li class="component-step-item">
                                            <div class="component-step-icon">2</div>
                                            <span>Selecciona <strong>"Escanear código QR"</strong> en la app.</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="component-visual-box box-qr">
                                    <div id="qr-container" style="width: 100%; height: 100%;">
                                         <div class="component-spinner-button" style="border-color: #000; border-left-color: transparent;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="component-card component-card--grouped component-accordion-item" data-accordion-id="2" style="margin-bottom: 0; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div class="component-group-item component-accordion-header">
                        <div class="component-card__content">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded">key</span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title">Paso 2: Ingreso manual (Opcional)</h2>
                                <p class="component-card__description">Si no puedes escanear el QR, ingresa este código manualmente.</p>
                            </div>
                        </div>
                        <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
                    </div>

                    <div class="component-accordion-content">
                        <hr class="component-divider" style="margin-bottom: 16px;">
                        <div class="component-group-item component-group-item--stacked" style="padding: 0;">
                            <div class="component-card__content">
                                <div class="component-card__text">
                                    <h2 class="component-card__title">Clave de configuración</h2>
                                    <p class="component-card__description">Copia esta clave y pégala en tu aplicación de autenticación.</p>
                                </div>
                            </div>
                            <div class="w-100 component-stage-form">
                                <div class="component-input-wrapper">
                                    <input type="text" id="manual-secret-input" class="component-text-input has-action" readonly placeholder="Cargando...">
                                    <label for="manual-secret-input" class="component-label-floating">Clave de configuración</label>
                                    <button type="button" class="component-input-action" data-action="copy-input" data-target="manual-secret-input" data-tooltip="Copiar">
                                        <span class="material-symbols-rounded">content_copy</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="component-card component-card--grouped component-accordion-item" data-accordion-id="3" style="margin-bottom: 0; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div class="component-group-item component-accordion-header" style="cursor: default;">
                        <div class="component-card__content">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded">lock_clock</span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title">Paso 3: Verificar código</h2>
                                <p class="component-card__description">Ingresa el código temporal para confirmar la activación.</p>
                            </div>
                        </div>
                    </div>

                    <div class="component-accordion-content">
                        <hr class="component-divider" style="margin-bottom: 16px;">
                        <div class="component-group-item component-group-item--stacked" style="padding: 0;">
                            <div class="w-100 component-stage-form">
                                <div class="component-input-wrapper">
                                    <input type="text" id="input-2fa-verify" class="component-text-input" placeholder=" " maxlength="6" style="font-family: monospace; letter-spacing: 4px; font-size: 18px; text-align: center; font-weight: bold;">
                                    <label for="input-2fa-verify" class="component-label-floating" style="left: 12px; transform: translateY(-50%); width: 100%; text-align: center;">Código de 6 dígitos</label>
                                </div>
                            </div>
                            <div class="component-card__actions actions-right w-100">
                                <button type="button" class="component-button primary w-100" id="btn-confirm-2fa">
                                    Verificar y activar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="component-card component-card--grouped disabled" id="step-success" style="margin-top: 16px;">
                <div class="component-group-item component-group-item--stacked">
                    
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered" style="background-color: #f0fdf4; border-color: #16a34a;">
                            <span class="material-symbols-rounded" style="color: #16a34a;">check</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title" style="color: #16a34a;">¡2FA Activado!</h2>
                            <p class="component-card__description">Tu cuenta ahora está protegida. A continuación, guarda tus códigos de recuperación de emergencia.</p>
                        </div>
                    </div>

                    <div id="recovery-codes-list" class="component-chip-grid w-100"></div>

                    <div class="component-card__actions w-100" style="margin-top: 16px;">
                        <button type="button" class="component-button primary w-100" onclick="window.location.reload()">
                            Finalizar configuración
                        </button>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>