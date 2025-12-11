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
            <h1 class="component-page-title">Configurar 2FA</h1>
            <p class="component-page-description">Sigue los pasos para proteger tu cuenta.</p>
        </div>

        <div class="component-card component-card--grouped" id="2fa-wizard-container">
            
            <?php if ($is2faEnabled): ?>
                <div class="component-group-item component-group-item--stacked wizard-step-container" style="justify-content: center;">
                    <div class="wizard-icon-container success">
                        <span class="material-symbols-rounded">check_circle</span>
                    </div>
                    
                    <div class="component-card__text text-center">
                        <h2 class="component-card__title">El 2FA está activado</h2>
                        <p class="component-card__description">Para desactivarlo, ingresa tu contraseña actual.</p>
                    </div>
                    
                    <div class="component-input-wrapper input-small-center">
                        <input class="component-text-input" id="disable-2fa-password" type="password" placeholder="Tu contraseña actual">
                    </div>
                    
                    <div class="component-card__actions centered-actions">
                         <button class="component-button" data-nav="settings/login-and-security">Cancelar</button>
                         <button class="component-button danger" id="btn-disable-2fa">Confirmar y Desactivar</button>
                    </div>
                </div>

            <?php else: ?>
                
                <div class="component-group-item component-group-item--stacked active" id="step-1-auth">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Paso 1: Confirma tu identidad</h2>
                        <p class="component-card__description">
                            Antes de configurar el doble factor, necesitamos verificar tu contraseña actual.
                        </p>
                    </div>
                    
                    <div class="component-input-wrapper">
                        <input class="component-text-input" id="setup-2fa-password" type="password" placeholder="Tu contraseña actual">
                    </div>

                    <div class="component-card__actions actions-right">
                        <button class="component-button" data-nav="settings/login-and-security">Cancelar</button>
                        <button class="component-button primary" id="btn-start-2fa-setup">Continuar</button>
                    </div>
                </div>

                <div class="component-group-item component-group-item--stacked wizard-step-container disabled" id="step-2-qr">
                    <div class="component-card__text text-center">
                        <h2 class="component-card__title">Paso 2: Escanea el código</h2>
                        <p class="component-card__description">
                            Abre tu app de autenticación (Google Authenticator, Authy) y escanea este código.
                        </p>
                    </div>
                    
                    <div class="qr-display-area">
                        <div id="qr-code-container"></div>
                    </div>

                    <div class="wizard-manual-entry">
                        <p>¿No puedes escanear? Copia este secreto:</p>
                        <strong id="secret-text"></strong>
                    </div>

                    <div class="component-input-wrapper input-small-center">
                        <input class="component-text-input text-center-input" id="verify-2fa-code" type="text" placeholder="000 000" maxlength="6">
                    </div>

                    <div class="component-card__actions centered-actions">
                        <button class="component-button primary" id="btn-verify-2fa-setup">Verificar y Activar</button>
                    </div>
                </div>

                <div class="component-group-item component-group-item--stacked wizard-step-container disabled" id="step-3-backup">
                    <div class="wizard-icon-container success">
                        <span class="material-symbols-rounded">verified</span>
                    </div>

                    <div class="component-card__text text-center">
                        <h2 class="component-card__title">¡2FA Activado!</h2>
                        <p class="component-card__description">
                            Guarda estos códigos de recuperación en un lugar seguro. Los necesitarás si pierdes acceso a tu teléfono.
                        </p>
                    </div>

                    <div class="backup-codes-grid" id="backup-codes-list">
                        </div>

                    <div class="component-card__actions centered-actions">
                        <button class="component-button primary" data-nav="settings/login-and-security">Finalizar</button>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>