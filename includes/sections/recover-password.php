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
        
        <div id="resend-container" style="margin-top: 15px; font-size: 14px; color: #666; display: none;">
            <a href="#" id="btn-resend-link" style="color: #999; pointer-events: none; text-decoration: none;">
                Reenviar correo de recuperación <span id="recover-timer">(60)</span>
            </a>
        </div>

        <div id="simulation-result" style="margin-top: 15px; word-break: break-all; display:none;" class="alert success"></div>

        <div class="auth-footer">
            <p><a href="<?php echo $basePath; ?>login">Volver al inicio de sesión</a></p>
        </div>
    </div>
</div>