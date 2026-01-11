<div class="component-layout-centered">
    <div class="component-card--compact">
        
        <div class="component-header-centered">
            <h1><?php echo __('auth.register.title') ?? 'Crear cuenta'; ?></h1>
            <p><?php echo __('auth.register.subtitle') ?? 'Únete a nosotros hoy'; ?></p>
            <?php if(isset($_GET['error'])): ?>
                <p style="color: #d32f2f; font-size: 14px; background: #ffebee; padding: 8px; border-radius: 4px;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <form action="<?php echo $basePath; ?>auth-action.php" method="POST" class="component-stage-form">
            <input type="hidden" name="action" value="register">

            <div class="component-form-group">
                
                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="email" name="email" id="reg-email" class="component-text-input" required placeholder=" ">
                    <label for="reg-email" class="component-label-floating"><?php echo __('auth.field.email') ?? 'Correo electrónico'; ?></label>
                </div>

                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="text" name="username" id="reg-username" class="component-text-input" required placeholder=" ">
                    <label for="reg-username" class="component-label-floating"><?php echo __('auth.field.username') ?? 'Nombre de usuario'; ?></label>
                </div>

                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="password" name="password" id="reg-password" class="component-text-input" required placeholder=" ">
                    <label for="reg-password" class="component-label-floating"><?php echo __('auth.field.password') ?? 'Contraseña'; ?></label>
                    
                    <button type="button" class="component-input-action" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>

            </div>

            <button type="submit" id="btn-register" class="component-button component-button--large primary">
                <?php echo __('auth.btn.register') ?? 'Crear cuenta'; ?>
            </button>
        </form>

        <div class="component-text-footer">
            <p>
                <?php echo __('auth.has_account') ?? '¿Ya tienes cuenta?'; ?> 
                <a href="<?php echo $basePath; ?>login"><?php echo __('auth.login_link') ?? 'Inicia sesión'; ?></a>
            </p>
        </div>
    </div>
</div>