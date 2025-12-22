<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">Iniciar Sesión</h1>
        <p class="auth-subtitle">Bienvenido a Project Aurora</p>
        
        <form id="loginForm" class="auth-form">
            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="email" placeholder="ejemplo@correo.com" required>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            
            <div class="form-links">
                <a href="#" class="forgot-pass">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="component-button primary full-width">Continuar</button>
        </form>

        <div class="auth-footer">
            <p>¿No tienes una cuenta? <a href="<?php echo $basePath; ?>register">Regístrate</a></p>
        </div>
    </div>
</div>