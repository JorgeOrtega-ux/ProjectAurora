<?php
// [CORRECCIÓN] Validar si la variable existe. 
// Si se carga vía AJAX (fetch), $CURRENT_SECTION no estará definida por el router.
if (!isset($CURRENT_SECTION)) {
    $CURRENT_SECTION = 'login';
}

$isStep2 = ($CURRENT_SECTION === 'login/verification-additional');
$maskedEmailDisplay = 'tu correo';

// Solo intentamos acceder a la sesión si existe y es el paso 2
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
            
            <div data-step="login-1" class="auth-step-container <?php echo $isStep2 ? '' : 'active'; ?>">
                <h1>Iniciar Sesión</h1>
                <p>Bienvenido de nuevo.</p>

                <div class="floating-label-group">
                    <input 
                        type="email" 
                        data-input="login-email" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                    >
                    <label class="floating-label">Correo Electrónico</label>
                </div>

                <div class="floating-label-group">
                    <input 
                        type="password" 
                        data-input="login-password" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                    >
                    <label class="floating-label">Contraseña</label>
                    
                    <button type="button" class="floating-input-btn">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>

                <div class="auth-link-wrapper">
                    <a href="#" onclick="event.preventDefault(); navigateTo('forgot-password')" style="color:#666; text-decoration:none; font-size:14px; font-weight:500;">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <button class="form-button" data-action="login-submit">Continuar</button>

                <div data-error="login-error" class="form-error-message"></div>

                <div class="form-footer-link">
                    ¿No tienes una cuenta? <a href="#" onclick="event.preventDefault(); navigateTo('register')">Regístrate</a>
                </div>
            </div>

            <div data-step="login-2" class="auth-step-container <?php echo $isStep2 ? 'active' : ''; ?>">
                <div class="auth-back-link">
                    <a href="#" data-action="login-2fa-back" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                        <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Cancelar
                    </a>
                </div>

                <h1>Verificación de Seguridad</h1>
                <p>Tu cuenta tiene activada la verificación en dos pasos.</p>
                <p style="font-size:14px; margin-top:10px;">Ingresa el código enviado a <strong data-display="login-2fa-email"><?php echo htmlspecialchars($maskedEmailDisplay); ?></strong></p>

                <div class="floating-label-group">
                    <input 
                        type="text" 
                        data-input="login-2fa-code" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                        maxlength="12"
                        style="letter-spacing: 2px; text-transform: uppercase; font-weight:bold;"
                    >
                    <label class="floating-label">Código de Seguridad</label>
                </div>

                <button class="form-button" data-action="login-2fa-submit">Verificar Acceso</button>

                <div data-error="login-2fa" class="form-error-message"></div>
            </div>

        </div>
    </div>
</div>