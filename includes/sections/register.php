<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Crear Cuenta</h1>
            <p>Únete a la plataforma</p>
        </div>
        
        <div id="registerContainer" class="form-groups-wrapper">
            
            <div class="form-group">
                <input type="text" name="username" id="username" required placeholder=" ">
                <label for="username">Nombre de usuario</label>
                <button type="button" class="btn-generate-username" tabindex="-1" title="Generar nombre aleatorio">
                    <span class="material-symbols-rounded">autorenew</span>
                </button>
            </div>

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

        <button type="button" id="btn-register" class="btn-primary">Registrarse</button>

        <div class="auth-footer">
            <p>
                ¿Ya tienes cuenta? 
                <a href="<?php echo $basePath; ?>login" class="link-primary">Inicia sesión</a>
            </p>
        </div>
    </div>
</div>