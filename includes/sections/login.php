<div class="auth-wrapper">
    <div class="auth-card">
        
        <div class="auth-header">
            <h1>Iniciar Sesión</h1>
            <p>Bienvenido de nuevo</p>
        </div>
        
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

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error mt-16 mb-0">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="auth-footer">
            <p>¿No tienes una cuenta? <a href="<?php echo $basePath; ?>register">Regístrate aquí</a></p>
        </div>
    </div>
</div>