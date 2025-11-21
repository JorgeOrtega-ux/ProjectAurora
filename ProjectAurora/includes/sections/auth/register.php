<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$initialStep = 1;
if (isset($CURRENT_SECTION)) {
    if ($CURRENT_SECTION === 'register/additional-data') $initialStep = 2;
    if ($CURRENT_SECTION === 'register/verification-account') $initialStep = 3;
} else if (isset($_GET['step'])) {
    $initialStep = (int)$_GET['step'];
}
?>

<div class="section-content overflow-y active" data-section="register">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <div data-step="register-1" class="auth-step-container <?php echo ($initialStep === 1) ? 'active' : ''; ?>">
                <h1>Registro</h1>
                <p>Comencemos con tus credenciales.</p>
                
                <div class="floating-label-group">
                    <input 
                        type="email" 
                        data-input="reg-email" 
                        class="floating-input" 
                        required 
                        placeholder=" " 
                        value="<?php echo $_SESSION['temp_register']['email'] ?? ''; ?>"
                    >
                    <label class="floating-label">Correo Electrónico</label>
                </div>

                <div class="floating-label-group">
                    <input 
                        type="password" 
                        data-input="reg-password" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                        minlength="8"
                    >
                    <label class="floating-label">Contraseña (Mín. 8 caracteres)</label>
                    <button type="button" class="floating-input-btn"><span class="material-symbols-rounded">visibility</span></button>
                </div>

                <button class="form-button" data-action="register-step1">Siguiente</button>
                <div data-error="register-1" class="form-error-message"></div>
                <div class="form-footer-link">¿Ya tienes una cuenta? <a href="#" onclick="event.preventDefault(); navigateTo('login')">Iniciar sesión</a></div>
            </div>

            <div data-step="register-2" class="auth-step-container <?php echo ($initialStep === 2) ? 'active' : ''; ?>">
                <div class="auth-back-link">
                    </div>
                <h1>Crea tu identidad</h1>
                <p>Elige un nombre de usuario único.</p>
                
                <div class="floating-label-group">
                    <input 
                        type="text" 
                        data-input="reg-username" 
                        class="floating-input" 
                        required 
                        placeholder=" " 
                        minlength="8"
                        maxlength="32"
                        pattern="[a-zA-Z0-9_]+"
                        style="padding-right: 50px;" 
                    >
                    <label class="floating-label">Usuario (8-32 letras, núm, _)</label>
                    
                    <button type="button" class="floating-input-btn username-magic-btn" title="Generar usuario aleatorio">
                        <span class="material-symbols-rounded">auto_fix_high</span>
                    </button>
                </div>

                <button class="form-button" data-action="register-step2">Continuar</button>
                <div data-error="register-2" class="form-error-message"></div>
            </div>

            <div data-step="register-3" class="auth-step-container <?php echo ($initialStep === 3) ? 'active' : ''; ?>">
                <h1>Verificación</h1>
                <p style="font-size:14px;">Hemos enviado un código a <strong data-display="email-verify"><?php echo $_SESSION['temp_register']['email'] ?? 'tu correo'; ?></strong>.</p>
                
                <div class="floating-label-group">
                    <input 
                        type="text" 
                        data-input="reg-code" 
                        class="floating-input" 
                        required 
                        placeholder=" " 
                        maxlength="14" 
                        style="letter-spacing: 2px; text-transform: uppercase;"
                    >
                    <label class="floating-label">Código</label>
                </div>

                <button class="form-button" data-action="register-step3">Verificar y Crear Cuenta</button>
                <div data-error="register-3" class="form-error-message"></div>
                
                <div class="form-footer-link">
                    <a href="#" data-action="resend-register" class="disabled-link">Reenviar código de verificación (60)</a>
                </div>
            </div>

        </div>
    </div>
</div>