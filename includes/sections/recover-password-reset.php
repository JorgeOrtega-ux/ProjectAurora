<?php
// Validar que tengamos token desde el router, si no, redirigir
if (!isset($resetToken) || empty($resetToken)) {
    echo "<script>window.location.href = '" . $basePath . "login';</script>";
    exit;
}
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Nueva Contraseña</h1>
            <p>Crea una contraseña segura para tu cuenta.</p>
        </div>
        
        <input type="hidden" id="reset-token" value="<?php echo htmlspecialchars($resetToken); ?>">
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="password" id="new-password" required placeholder=" ">
                <label for="new-password">Nueva contraseña</label>
            </div>
             <div class="form-group">
                <input type="password" id="confirm-password" required placeholder=" ">
                <label for="confirm-password">Confirmar contraseña</label>
            </div>
        </div>

        <button type="button" id="btn-recover-reset" class="btn-primary">Cambiar Contraseña</button>

        <div class="auth-footer">
            <p><a href="<?php echo $basePath; ?>login">Cancelar</a></p>
        </div>
    </div>
</div>