<div class="auth-wrapper">
    <div class="auth-card">
        <h1>Iniciar Sesión</h1>
        <p>Bienvenido de nuevo a Project Aurora</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <input type="email" name="email" id="email" required placeholder=" ">
                <label for="email">Correo Electrónico</label>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" required placeholder=" ">
                <label for="password">Contraseña</label>
            </div>

            <div class="forgot-password">
                <a href="#">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn-primary">Continuar</button>
        </form>

        <div class="auth-footer">
            <p>¿No tienes cuenta? <a href="<?php echo $basePath; ?>register">Regístrate aquí</a></p>
        </div>
    </div>
</div>