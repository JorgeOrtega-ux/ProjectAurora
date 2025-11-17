<?php
// Detectar estado inicial basado en la URL del Router
$isStep2 = ($CURRENT_SECTION === 'login/verification-additional');

// Preparar datos si estamos en el paso 2 (recarga de página)
$maskedEmailDisplay = 'tu correo';
if ($isStep2 && isset($_SESSION['temp_login_2fa']['email'])) {
    $rawEmail = $_SESSION['temp_login_2fa']['email'];
    $parts = explode('@', $rawEmail);
    if(count($parts) == 2){
        $maskedEmailDisplay = substr($parts[0], 0, 3) . '***@' . $parts[1];
    }
}
?>
<div class="section-content overflow-y active" data-section="login">
    
    <div class="section-center-wrapper">

        <div class="form-container">
            
            <div id="login-step-1" style="display: <?php echo $isStep2 ? 'none' : 'block'; ?>;">
                <h1>Iniciar Sesión</h1>
                <p>Bienvenido de nuevo.</p>

                <div class="floating-label-group">
                    <input 
                        type="email" 
                        id="login-email" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                    >
                    <label for="login-email" class="floating-label">Correo Electrónico</label>
                </div>

                <div class="floating-label-group">
                    <input 
                        type="password" 
                        id="login-password" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                    >
                    <label for="login-password" class="floating-label">Contraseña</label>
                    
                    <button type="button" class="password-toggle-btn">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>

                <div style="text-align: right; margin-top: -10px; margin-bottom: 10px;">
                    <a href="#" onclick="event.preventDefault(); navigateTo('forgot-password')" style="color:#666; text-decoration:none; font-size:14px; font-weight:500;">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <button class="form-button" id="btn-login-submit">Continuar</button>

                <div id="login-error" class="form-error-message"></div>

                <div class="form-footer-link">
                    ¿No tienes una cuenta? <a href="#" onclick="event.preventDefault(); navigateTo('register')">Regístrate</a>
                </div>
            </div>

            <div id="login-step-2" style="display: <?php echo $isStep2 ? 'block' : 'none'; ?>;">
                <div style="margin-bottom: 10px;">
                    <a href="#" id="btn-login-2fa-back" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                        <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Cancelar
                    </a>
                </div>

                <h1>Verificación de Seguridad</h1>
                <p>Tu cuenta tiene activada la verificación en dos pasos.</p>
                <p style="font-size:14px; margin-top:10px;">Ingresa el código enviado a <strong id="login-2fa-email-display"><?php echo htmlspecialchars($maskedEmailDisplay); ?></strong></p>

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

</div>