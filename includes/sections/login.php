<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Iniciar Sesión</h1>
            <p>Bienvenido de nuevo a Project Aurora</p>
        </div>
        
        <input type="hidden" id="login-action" name="action" value="login">
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="email" name="email" id="email" required placeholder=" ">
                <label for="email">Correo Electrónico</label>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" required placeholder=" ">
                <label for="password">Contraseña</label>
            </div>
        </div>

        <div class="forgot-password">
            <a href="#">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="button" id="btn-login" class="btn-primary">Continuar</button>

        <?php if (!empty($error)): ?>
            <div class="alert error" style="margin-top: 16px; margin-bottom: 0;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success" style="margin-top: 16px; margin-bottom: 0;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="auth-footer">
            <p>¿No tienes cuenta? <a href="<?php echo $basePath; ?>register">Regístrate aquí</a></p>
        </div>
    </div>
</div>