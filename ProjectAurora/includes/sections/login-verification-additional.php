<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Si no hay sesión temporal de login (se accedió directo por URL), el router ya redirigió, 
// pero por seguridad doble verificamos.
if (empty($_SESSION['temp_login_2fa']['email'])) {
    echo "<script>window.location.href = window.BASE_PATH + 'login';</script>";
    exit;
}

$maskedEmail = $_SESSION['temp_login_2fa']['email'];
// Enmascarar email para visual (ej: jorg***@gmail.com)
$parts = explode('@', $maskedEmail);
if(count($parts) == 2){
    $maskedEmail = substr($parts[0], 0, 3) . '***@' . $parts[1];
}
?>
<div class="section-content overflow-y active" data-section="login/verification-additional">
    
    <div class="section-center-wrapper">

        <div class="form-container">
            
            <div style="margin-bottom: 10px;">
                <a href="#" onclick="event.preventDefault(); navigateTo('login')" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                    <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Cancelar
                </a>
            </div>

            <h1>Verificación de Seguridad</h1>
            <p>Tu cuenta tiene activada la verificación en dos pasos.</p>
            <p style="font-size:14px; margin-top:10px;">Ingresa el código enviado a <strong><?php echo htmlspecialchars($maskedEmail); ?></strong></p>

            <div class="floating-label-group">
                <input 
                    type="text" 
                    id="login-2fa-code" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                    maxlength="12"
                    style="letter-spacing: 2px; text-transform: uppercase; font-weight:bold;"
                >
                <label for="login-2fa-code" class="floating-label">Código de Seguridad</label>
            </div>

            <button class="form-button" id="btn-login-2fa-submit">Verificar Acceso</button>

            <div id="login-2fa-error" class="form-error-message"></div>

        </div>

    </div>

</div>