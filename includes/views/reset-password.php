<div class="view-content animate-fade-in">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">

            <div class="component-header-centered">
                <h1 id="auth-title">Nueva Contraseña</h1>
                <p id="auth-subtitle">Ingresa tu nueva contraseña para la cuenta</p>
            </div>

            <form id="form-reset-password" class="component-stage-form">
                <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="password" id="reset-password-1" class="component-text-input has-action" required placeholder=" ">
                        <label for="reset-password-1" class="component-label-floating">Nueva contraseña</label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>

                    <div class="component-input-wrapper">
                        <input type="password" id="reset-password-2" class="component-text-input has-action" required placeholder=" ">
                        <label for="reset-password-2" class="component-label-floating">Confirmar contraseña</label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                </div>

                <button type="submit" id="btn-reset-password" class="component-button component-button--large primary" style="margin-top: 16px;">
                    Actualizar Contraseña
                </button>

                <div id="reset-error" class="component-message-error"></div>
                <div id="reset-success" style="display: none; color: #16a34a; font-weight: 500; text-align: center; margin-top: 16px; padding: 12px; background: #f0fdf4; border: 1px solid #16a34a; border-radius: 8px;">
                    Contraseña actualizada correctamente. Redirigiendo...
                </div>
            </form>

        </div>
    </div>
</div>