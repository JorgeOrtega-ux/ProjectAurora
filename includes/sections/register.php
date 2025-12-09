<div class="auth-wrapper">
    <div class="auth-card">
        <h1>Crear Cuenta</h1>
        <p>Únete a Project Aurora</p>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="register">
            
            <div class="form-group">
                <label>Nombre de Usuario</label>
                <input type="text" name="username" required placeholder="Usuario123">
            </div>

            <div class="form-group">
                <label>Correo Electrónico</label>
                <input type="email" name="email" required placeholder="ejemplo@correo.com">
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required placeholder="******">
            </div>

            <button type="submit" class="btn-primary">Registrarse</button>
        </form>

        <div class="auth-footer">
            <p>¿Ya tienes cuenta? <a href="<?php echo $basePath; ?>login">Inicia sesión</a></p>
        </div>
    </div>
</div>