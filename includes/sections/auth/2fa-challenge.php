<?php
// includes/sections/auth/2fa-challenge.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad extra: si no hay sesión temporal de 2fa, volver al login
if (!isset($_SESSION['temp_2fa_user_id'])) {
    echo "<script>window.location.href = '" . $basePath . "login';</script>";
    exit;
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <span class="material-symbols-rounded" style="font-size: 48px; color: #000; margin-bottom: 10px;">lock_person</span>
            <h1>Verificación de Seguridad</h1>
            <p>Ingresa el código de 6 dígitos de tu aplicación autenticadora.</p>
        </div>
        
        <input type="hidden" id="login-2fa-action" name="action" value="verify_2fa_login">
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="text" id="2fa-code-input" required placeholder=" " maxlength="6" style="text-align: center; letter-spacing: 5px; font-size: 20px;">
                <label for="2fa-code-input" style="left: 50%; transform: translateX(-50%) translateY(-50%);">Código 2FA</label>
            </div>
        </div>

        <button type="button" id="btn-verify-2fa-login" class="btn-primary">Verificar</button>

        <div class="auth-footer">
<p><a href="<?php echo $basePath; ?>login" class="text-link">Volver al inicio de sesión</a></p>        </div>
    </div>
</div>