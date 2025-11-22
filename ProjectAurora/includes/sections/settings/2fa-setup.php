<?php
// includes/sections/settings/2fa-setup.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Autenticación en 2 Pasos</h1>
            <p class="component-page-description">Protege tu cuenta requiriendo un código adicional al iniciar sesión.</p>
        </div>

        <div class="component-card component-card--grouped active" data-step="2fa-step-1">
            <div class="component-group-item component-group-item--stacked-right">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">lock</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Confirma tu identidad</h2>
                        <p class="component-card__description">Por seguridad, ingresa tu contraseña actual para comenzar la configuración.</p>
                    </div>
                </div>
                
                <div class="component-input-wrapper w-100">
                    <input type="password" class="component-text-input full-width" data-element="2fa-current-password" placeholder="Contraseña actual">
                </div>

                <div class="component-card__actions">
                    <button type="button" class="component-button primary" data-action="verify-pass-2fa">
                        Continuar
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped disabled" data-step="2fa-step-2">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">qr_code_scanner</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Configura tu aplicación</h2>
                        <p class="component-card__description">Escanea este código con Google Authenticator, Authy o Microsoft Authenticator.</p>
                    </div>
                </div>

                <div style="width: 100%; display: flex; justify-content: center; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <div id="qrcode-display"></div>
                </div>

                <div style="text-align: center; width: 100%;">
                    <p style="font-size: 13px; color: #666; margin-bottom: 5px;">¿No puedes escanearlo?</p>
                    <div style="background: #eee; padding: 8px; border-radius: 4px; display: inline-block; font-family: monospace; letter-spacing: 1px;">
                        <strong id="manual-secret-text">Cargando...</strong>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container">
                        <span class="material-symbols-rounded">pin</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Verificar código</h2>
                        <p class="component-card__description">Ingresa el código de 6 dígitos que aparece en tu aplicación.</p>
                    </div>
                </div>
                <div class="component-input-wrapper w-100">
                    <input type="text" class="component-text-input full-width" 
                           data-element="2fa-verify-code" 
                           placeholder="000000" 
                           maxlength="6" 
                           style="letter-spacing: 4px; font-size: 18px; text-align: center;">
                </div>
                <div class="component-card__actions actions-right w-100">
                     <button type="button" class="component-button" onclick="location.reload()">Cancelar</button>
                    <button type="button" class="component-button primary" data-action="confirm-enable-2fa">
                        Activar 2FA
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped disabled" data-step="2fa-step-3">
            <div class="component-group-item component-group-item--stacked">
                <div style="text-align: center; width: 100%; padding: 20px 0;">
                    <span class="material-symbols-rounded" style="font-size: 64px; color: #4caf50;">check_circle</span>
                    <h2 style="margin: 10px 0; font-size: 24px;">¡Autenticación activada!</h2>
                    <p style="color: #666;">Tu cuenta ahora es mucho más segura.</p>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container" style="background: #fff3e0; border-color: #ffe0b2;">
                        <span class="material-symbols-rounded" style="color: #f57c00;">warning</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #e65100;">Códigos de recuperación</h2>
                        <p class="component-card__description">Si pierdes tu teléfono, estos códigos son la única forma de entrar. Guárdalos en un lugar seguro.</p>
                    </div>
                </div>

                <div id="backup-codes-list" style="
                    background: #333; 
                    color: #fff; 
                    padding: 20px; 
                    border-radius: 8px; 
                    font-family: monospace; 
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    gap: 10px; 
                    text-align: center;
                    width: 100%;
                ">
                    </div>

                <div class="component-card__actions actions-right w-100">
                    <button type="button" class="component-button primary" onclick="navigateTo('settings/login-security')">
                        He guardado mis códigos
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>