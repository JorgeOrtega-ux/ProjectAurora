<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Verificación</h1>
            <p>Hemos enviado un código a tu correo.</p>
        </div>

        <input type="hidden" id="verify-action" name="action" value="verify_code">
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="text" name="code" id="code" required placeholder=" " maxlength="6" style="letter-spacing: 4px; font-weight: bold; text-align: center;">
                <label for="code" style="left: 50%; transform: translateX(-50%) translateY(-50%);">Código de 6 dígitos</label>
            </div>
        </div>

        <button type="button" id="btn-verify" class="btn-primary">Verificar y Crear Cuenta</button>

        <?php if (!empty($error)): ?>
            <div class="alert error" style="margin-top: 16px; margin-bottom: 0;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="auth-footer">
            <p>¿No recibiste el código? <a href="#">Reenviar</a></p>
        </div>
    </div>
</div>