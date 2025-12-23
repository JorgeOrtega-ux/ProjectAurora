<div class="auth-wrapper">
    <div class="auth-card">
        
        <div class="auth-header">
            <h1>Recuperar cuenta</h1>
            <p>Ingresa tu correo para buscar tu cuenta.</p>
        </div>
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="email" name="email_recovery" id="email_recovery" required placeholder=" ">
                <label for="email_recovery">Correo electrónico</label>
            </div>
        </div>

        <button type="button" id="btn-request-reset" class="btn-primary">Enviar enlace</button>
        <div id="recovery-message-area"></div>

        <div class="auth-footer">
            <a href="<?php echo $basePath; ?>login" data-nav="login" class="link-primary">Volver al inicio de sesión</a>
        </div>
    </div>
</div>