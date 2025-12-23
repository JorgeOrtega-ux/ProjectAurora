<div class="auth-wrapper">
    <div class="auth-card">
        
        <div class="auth-header">
            <h1 id="auth-title">Iniciar Sesión</h1>
            <p id="auth-subtitle">Bienvenido de nuevo</p>
        </div>
        
        <div id="login-stage-1">
            <input type="hidden" id="login-action" name="action" value="login">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="email" name="email" id="email" required placeholder=" ">
                    <label for="email">Correo electrónico</label>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="password" required placeholder=" ">
                    <label for="password">Contraseña</label>
                    <button type="button" class="btn-input-action" data-action="toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
            </div>

            <div class="forgot-password">
                <a href="<?php echo $basePath; ?>recover-password" data-nav="recover-password">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="button" id="btn-login" class="btn-primary">Iniciar Sesión</button>
        </div>

        <div id="login-stage-2" class="disabled" style="display:none;">
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" id="2fa-code" placeholder=" " maxlength="8" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
                    <label for="2fa-code" class="label-centered">Código de 2FA</label>
                </div>
            </div>
            
            <p style="font-size: 13px; color: #666; margin: 12px 0;">
                Ingresa el código de tu aplicación o un código de recuperación.
            </p>

            <button type="button" id="btn-verify-2fa" class="btn-primary">Verificar</button>
            
            <div style="margin-top: 16px;">
                 <a href="#" onclick="location.reload()" style="font-size: 13px; color: #666;">Volver atrás</a>
            </div>
        </div>

        <div class="auth-footer">
            <p>¿No tienes una cuenta? <a href="<?php echo $basePath; ?>register">Regístrate aquí</a></p>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error mt-16 mb-0">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>