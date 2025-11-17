<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Si no hay username (se saltaron el paso 2), redirigir
if (empty($_SESSION['temp_register']['username'])) {
    echo "<script>window.location.href = window.BASE_PATH + 'register/additional-data';</script>";
    exit;
}
$userEmail = $_SESSION['temp_register']['email'] ?? 'tu correo';
?>
<div class="section-content overflow-y active" data-section="register/verification-account">
    
    <div class="section-center-wrapper">

        <div class="form-container">
            
            <h1>Verificación (3/3)</h1>
            <p style="font-size:14px;">Hemos enviado un código de acceso a <strong><?php echo htmlspecialchars($userEmail); ?></strong>.</p>

            <div class="floating-label-group">
                <input 
                    type="text" 
                    id="reg-code" 
                    class="floating-input" 
                    required 
                    placeholder=" " 
                    maxlength="12" 
                    style="letter-spacing: 2px; text-transform: uppercase; font-weight:bold;"
                >
                <label for="reg-code" class="floating-label">Código (12 caracteres)</label>
            </div>

            <button class="form-button" id="btn-register-step3">Verificar y Crear Cuenta</button>

            <div id="register-error-3" class="form-error-message"></div>
            
            <div class="form-footer-link">
               <a href="#" onclick="event.preventDefault(); navigateTo('register/additional-data')">Cambiar datos</a>
            </div>

        </div>

    </div>

</div>