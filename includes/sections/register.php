<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">Crear Cuenta</h1>
        <p class="auth-subtitle">Únete a Project Aurora</p>
        
        <form id="registerForm" class="auth-form">
            <div class="form-group">
                <label>Nombre de usuario</label>
                <input type="text" name="username" placeholder="Usuario" required>
            </div>
            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="email" placeholder="ejemplo@correo.com" required>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="component-button primary full-width">Registrarse</button>
        </form>

        <div class="auth-footer">
            <p>¿Ya tienes una cuenta? <a href="<?php echo $basePath; ?>login">Inicia sesión</a></p>
        </div>
    </div>
</div>