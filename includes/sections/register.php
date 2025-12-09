<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Crear Cuenta</h1>
            <p>Únete a Project Aurora</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <input type="hidden" name="action" value="register">
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="text" name="username" id="username" required placeholder=" ">
                <label for="username">Nombre de Usuario</label>
            </div>

            <div class="form-group">
                <input type="email" name="email" id="email" required placeholder=" ">
                <label for="email">Correo Electrónico</label>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" required placeholder=" ">
                <label for="password">Contraseña</label>
            </div>
        </div>

        <button type="submit" class="btn-primary">Registrarse</button>

        <div class="auth-footer">
            <p>¿Ya tienes cuenta? <a href="<?php echo $basePath; ?>login">Inicia sesión</a></p>
        </div>
    </div>
</div>