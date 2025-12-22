<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Iniciar Sesión</h1>
            <p>Bienvenido de nuevo</p>
        </div>
        
        <div id="loginContainer" class="form-groups-wrapper">
            <div class="form-group">
                <input type="email" name="email" id="email" required placeholder=" ">
                <label for="email">Correo electrónico</label>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" required placeholder=" ">
                <label for="password">Contraseña</label>
                <button type="button" class="btn-toggle-password" tabindex="-1">
                    <span class="material-symbols-rounded">visibility</span>
                </button>
            </div>
        </div>

        <div class="forgot-password">
            <a href="#">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="button" id="btn-login" class="btn-primary">Iniciar Sesión</button>

        <div class="auth-footer">
            <p>¿No tienes una cuenta? <a href="<?php echo $basePath; ?>register">Regístrate aquí</a></p>
        </div>
    </div>
</div>