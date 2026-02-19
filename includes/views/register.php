<?php
// Evaluamos la URL solicitada para ocultar la cabecera desde el servidor si es una sub-etapa
// Esto evita el parpadeo "FOUC" (Flash of Unstyled Content) antes de que JS valide la sesión
$uri = $_SERVER['REQUEST_URI'] ?? '';
$isSubStep = (strpos($uri, '/aditional-data') !== false || strpos($uri, '/verification-account') !== false);
$headerStyle = $isSubStep ? 'display: none;' : '';
?>
<div class="view-content animate-fade-in">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">

            <div class="component-header-centered" style="<?php echo $headerStyle; ?>">
                <h1 id="auth-title">Crear Cuenta</h1>
                <p id="auth-subtitle">Regístrate para comenzar</p>
            </div>

            <div id="register-fatal-error" class="component-fatal-error-container">
                <h2 class="component-fatal-error-title">Oops, an error occurred!</h2>
                <div id="register-fatal-error-code" class="component-json-error-box"></div>
            </div>

            <form id="form-register-1" class="component-stage-form" style="display: none;">
                <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="email" id="reg-email" class="component-text-input" placeholder=" ">
                        <label for="reg-email" class="component-label-floating">Correo electrónico</label>
                    </div>

                    <div class="component-input-wrapper">
                        <input type="password" id="reg-password" class="component-text-input has-action" placeholder=" ">
                        <label for="reg-password" class="component-label-floating">Contraseña</label>
                        <button type="button" class="component-input-action toggle-password-btn" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                </div>
                <button type="submit" id="btn-next-1" class="component-button component-button--large primary" style="margin-top: 16px;">
                    Continuar
                </button>

                <div id="register-error-1" class="component-message-error"></div>

                <div class="component-text-footer" style="margin-top: 12px;">
                    <p>¿Ya tienes una cuenta? <a href="/ProjectAurora/login" data-nav="/ProjectAurora/login">Inicia sesión</a></p>
                </div>
            </form>

            <form id="form-register-2" class="component-stage-form" style="display: none;">
                <button type="button" id="btn-back-1" class="component-button component-button--square-40" style="margin-bottom: 16px;">
                    <span class="material-symbols-rounded">arrow_back</span>
                </button>

                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="text" id="reg-username" class="component-text-input" placeholder=" ">
                        <label for="reg-username" class="component-label-floating">Nombre de usuario</label>
                    </div>
                </div>
                <button type="submit" id="btn-next-2" class="component-button component-button--large primary" style="margin-top: 16px;">
                    Continuar
                </button>

                <div id="register-error-2" class="component-message-error"></div>
            </form>

            <form id="form-register-3" class="component-stage-form" style="display: none;">
                <button type="button" id="btn-back-2" class="component-button component-button--square-40" style="margin-bottom: 16px;">
                    <span class="material-symbols-rounded">arrow_back</span>
                </button>

                <div style="text-align: center; margin-bottom: 16px; color: #666; font-size: 14px;">
                    <p>Hemos generado un código de seguridad para confirmar tu identidad.</p>
                    <div id="simulated-code-display" style="margin-top: 8px; font-size: 20px; font-weight: bold; color: #000; letter-spacing: 4px; background: #f5f5fa; padding: 8px; border-radius: 8px;"></div>
                </div>

                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="text" id="reg-code" class="component-text-input" placeholder=" " maxlength="6" style="font-size: 20px; letter-spacing: 8px; text-align: center; font-weight: bold;">
                        <label for="reg-code" class="component-label-floating" style="left: 12px; transform: translateY(-50%); width: 100%; text-align: center;">Código de 6 dígitos</label>
                    </div>
                </div>
                <button type="submit" id="btn-register-final" class="component-button component-button--large primary" style="margin-top: 16px;">
                    Verificar y Crear Cuenta
                </button>

                <div id="register-error-3" class="component-message-error"></div>
            </form>

        </div>
    </div>
</div>