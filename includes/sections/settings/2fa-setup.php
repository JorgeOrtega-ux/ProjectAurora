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

        <div class="component-card" id="2fa-wizard-container">
            
            <?php if ($is2faEnabled): ?>
                <div class="component-group-item--stacked" style="text-align: center;">
                    <div style="margin-bottom: 20px;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: green;">check_circle</span>
                        <h2>El 2FA está activado</h2>
                        <p>Para desactivarlo, ingresa tu contraseña.</p>
                    </div>
                    
                    <div class="component-input-wrapper">
                        <input type="password" id="disable-2fa-password" class="component-text-input" placeholder="Tu contraseña actual">
                    </div>
                    
                    <div class="component-card__actions" style="margin-top: 20px; justify-content: center;">
                         <button class="component-button" data-nav="settings/login-and-security">Cancelar</button>
                         <button class="component-button danger" id="btn-disable-2fa">Confirmar y Desactivar</button>
                    </div>
                </div>
            <?php else: ?>
                <div id="step-1-auth" class="active">
                    <h2 class="component-card__title" style="margin-bottom: 12px;">Paso 1: Confirma tu identidad</h2>
                    <p class="component-card__description" style="margin-bottom: 20px;">
                        Antes de configurar el doble factor, necesitamos verificar tu contraseña actual.
                    </p>
                    
                    <div class="component-input-wrapper">
                        <input type="password" id="setup-2fa-password" class="component-text-input" placeholder="Tu contraseña actual">
                    </div>

                    <div class="component-card__actions actions-right" style="margin-top: 20px;">
                        <button class="component-button" data-nav="settings/login-and-security">Cancelar</button>
                        <button class="component-button primary" id="btn-start-2fa-setup">Continuar</button>
                    </div>
                </div>

                <div id="step-2-qr" class="disabled" style="flex-direction: column; align-items: center; text-align: center;">
                    <h2 class="component-card__title">Paso 2: Escanea el código</h2>
                    <p class="component-card__description">
                        Abre tu app (Google Authenticator, Authy) y escanea este código.
                    </p>
                    
                    <div style="margin: 20px 0; border: 1px solid #eee; padding: 10px; border-radius: 8px;">
                        <img id="qr-image" src="" alt="QR Code" style="width: 200px; height: 200px;">
                    </div>

                    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                        ¿No puedes escanear? Copia este secreto: <br>
                        <strong id="secret-text" style="font-family: monospace; font-size: 16px; color: #000;"></strong>
                    </p>

                    <div class="component-input-wrapper" style="max-width: 200px; margin: 0 auto;">
                        <input type="text" id="verify-2fa-code" class="component-text-input" placeholder="Código de 6 dígitos" maxlength="6" style="text-align: center; letter-spacing: 4px;">
                    </div>

                    <div class="component-card__actions" style="margin-top: 20px; justify-content: center;">
                        <button class="component-button primary" id="btn-verify-2fa-setup">Verificar y Activar</button>
                    </div>
                </div>

                <div id="step-3-backup" class="disabled" style="flex-direction: column; align-items: center;">
                    <span class="material-symbols-rounded" style="font-size: 48px; color: green; margin-bottom: 10px;">verified</span>
                    <h2 class="component-card__title">¡2FA Activado!</h2>
                    <p class="component-card__description" style="text-align: center; margin-bottom: 20px;">
                        Guarda estos códigos de recuperación en un lugar seguro. Los necesitarás si pierdes acceso a tu teléfono.
                    </p>

                    <div id="backup-codes-list" style="
                        display: grid; 
                        grid-template-columns: 1fr 1fr; 
                        gap: 10px; 
                        background: #f5f5fa; 
                        padding: 15px; 
                        border-radius: 8px; 
                        font-family: monospace; 
                        font-weight: bold;
                        margin-bottom: 20px;
                    ">
                        </div>

                    <div class="component-card__actions" style="justify-content: center;">
                        <button class="component-button primary" data-nav="settings/login-and-security">Finalizar</button>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>