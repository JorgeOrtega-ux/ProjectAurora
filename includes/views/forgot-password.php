<div class="view-content">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">

            <div class="component-header-centered">
                <h1 id="auth-title">Recuperar Contraseña</h1>
                <p id="auth-subtitle">Ingresa tu correo para recibir un enlace de recuperación</p>
            </div>

            <form id="form-forgot-password" class="component-stage-form">
                <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="email" id="forgot-email" class="component-text-input" required placeholder=" ">
                        <label for="forgot-email" class="component-label-floating">Correo electrónico</label>
                    </div>
                </div>

                <div id="simulated-link-container" style="display: none; text-align: center; margin: 16px 0; color: #666; font-size: 14px;">
                    <p>Enlace simulado enviado al correo:</p>
                    <a id="simulated-link-display" href="#" style="word-break: break-all; font-weight: bold; color: #000; margin-top: 8px; display: block;"></a>
                </div>

                <button type="submit" id="btn-forgot-password" class="component-button component-button--large primary" style="margin-top: 16px;">
                    Enviar Enlace
                </button>

                <div id="forgot-error" class="component-message-error"></div>

                <div class="component-text-footer" style="margin-top: 16px;">
                    <p><a href="/ProjectAurora/login" data-nav="/ProjectAurora/login">Volver al inicio de sesión</a></p>
                </div>
            </form>

        </div>
    </div>
</div>