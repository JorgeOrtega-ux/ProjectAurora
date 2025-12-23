<?php
// Capturamos el token de la URL (GET)
$token = $_GET['token'] ?? '';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if(empty($token)): ?>
            <div class="auth-header">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f;">broken_image</span>
                <h1 style="color: #d32f2f;">Enlace inválido</h1>
                <p>No se ha proporcionado un token de seguridad.</p>
            </div>
            <a href="<?php echo $basePath; ?>login" class="btn-primary">Ir al Login</a>

        <?php else: ?>
            <div class="auth-header">
                <h1>Nueva Contraseña</h1>
                <p>Crea una nueva contraseña segura.</p>
            </div>
            
            <input type="hidden" id="reset_token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="password" id="new_password" required placeholder=" ">
                    <label for="new_password">Nueva contraseña</label>
                    <button type="button" class="btn-input-action" data-action="toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
                
                <div class="form-group">
                    <input type="password" id="confirm_password" required placeholder=" ">
                    <label for="confirm_password">Confirmar contraseña</label>
                </div>
            </div>

            <button type="button" id="btn-submit-new-password" class="btn-primary">Cambiar Contraseña</button>

        <?php endif; ?>
    </div>
</div>