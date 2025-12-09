<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Recuperar cuenta</h1>
            <p>Ingresa tu correo electrónico para buscar tu cuenta.</p>
        </div>
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="email" id="recover-email" required placeholder=" ">
                <label for="recover-email">Correo Electrónico</label>
            </div>
        </div>

        <button type="button" id="btn-recover-request" class="btn-primary">Enviar enlace</button>
        
        <div id="simulation-result" style="margin-top: 15px; word-break: break-all; display:none;" class="alert success"></div>

        <div class="auth-footer">
            <p><a href="<?php echo $basePath; ?>login">Volver al inicio de sesión</a></p>
        </div>
    </div>
</div>