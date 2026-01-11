<div class="component-layout-centered">
    <div class="component-card--compact">
        
        <div class="component-header-centered">
            <h1><?php echo __('auth.login.title') ?? 'Iniciar sesión'; ?></h1>
            <p><?php echo __('auth.login.subtitle') ?? 'Bienvenido de nuevo'; ?></p>
            
            <div id="auth-error-container" style="color: #d32f2f; font-size: 14px; background: #ffebee; padding: 12px; border-radius: 8px; margin-top: 8px; display: none; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded" style="font-size: 18px;">error</span>
                <span id="auth-error-text"></span>
            </div>
        </div>
        
        <div class="component-stage-form">
            <input type="hidden" id="login-csrf" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="component-form-group">
                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="email" id="login-email" class="component-text-input" required placeholder=" ">
                    <label for="login-email" class="component-label-floating"><?php echo __('auth.field.email') ?? 'Correo electrónico'; ?></label>
                </div>

                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="password" id="login-password" class="component-text-input" required placeholder=" ">
                    <label for="login-password" class="component-label-floating"><?php echo __('auth.field.password') ?? 'Contraseña'; ?></label>
                    
                    <button type="button" class="component-input-action" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
            </div>

            <a href="<?php echo $basePath; ?>recover-password" class="component-link-simple">
                <?php echo __('auth.forgot_password') ?? '¿Olvidaste tu contraseña?'; ?>
            </a>

            <button type="button" id="btn-login-action" class="component-button component-button--large primary">
                <?php echo __('auth.btn.login') ?? 'Acceder'; ?>
            </button>
        </div>

        <div class="component-text-footer">
            <p>
                <?php echo __('auth.no_account') ?? '¿No tienes cuenta?'; ?> 
                <a href="<?php echo $basePath; ?>register" data-nav="register"><?php echo __('auth.register_link') ?? 'Regístrate'; ?></a>
            </p>
        </div>
    </div>
</div>