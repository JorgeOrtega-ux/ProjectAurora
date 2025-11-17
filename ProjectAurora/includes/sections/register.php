<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Lógica de visualización inicial basada en la URL o parámetro GET (para AJAX)
$initialStep = 1;

// Detectar si venimos por Router PHP (Carga completa)
if (isset($CURRENT_SECTION)) {
    if ($CURRENT_SECTION === 'register/additional-data') $initialStep = 2;
    if ($CURRENT_SECTION === 'register/verification-account') $initialStep = 3;
} 
// Detectar si venimos por AJAX (Url Manager)
else if (isset($_GET['step'])) {
    $initialStep = (int)$_GET['step'];
}
?>

<div class="section-content overflow-y active" data-section="register">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <div id="step-container-1" style="display: <?php echo ($initialStep === 1) ? 'block' : 'none'; ?>;">
                <h1>Registro (1/3)</h1>
                <p>Comencemos con tus credenciales.</p>
                <div class="floating-label-group">
                    <input type="email" id="reg-email" class="floating-input" required placeholder=" " value="<?php echo $_SESSION['temp_register']['email'] ?? ''; ?>">
                    <label for="reg-email" class="floating-label">Correo Electrónico</label>
                </div>
                <div class="floating-label-group">
                    <input type="password" id="reg-password" class="floating-input" required placeholder=" ">
                    <label for="reg-password" class="floating-label">Contraseña</label>
                    <button type="button" class="password-toggle-btn"><span class="material-symbols-rounded">visibility</span></button>
                </div>
                <button class="form-button" id="btn-register-step1">Siguiente</button>
                <div id="register-error-1" class="form-error-message"></div>
                <div class="form-footer-link">¿Ya tienes una cuenta? <a href="#" onclick="event.preventDefault(); navigateTo('login')">Iniciar sesión</a></div>
            </div>

            <div id="step-container-2" style="display: <?php echo ($initialStep === 2) ? 'block' : 'none'; ?>;">
                <div style="margin-bottom: 10px;">
                    <a href="#" id="btn-back-step1" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                        <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Volver
                    </a>
                </div>
                <h1>Crea tu identidad (2/3)</h1>
                <p>Elige un nombre de usuario único.</p>
                <div class="floating-label-group">
                    <input type="text" id="reg-username" class="floating-input" required placeholder=" " maxlength="50">
                    <label for="reg-username" class="floating-label">Nombre de Usuario</label>
                </div>
                <button class="form-button" id="btn-register-step2">Continuar</button>
                <div id="register-error-2" class="form-error-message"></div>
            </div>

            <div id="step-container-3" style="display: <?php echo ($initialStep === 3) ? 'block' : 'none'; ?>;">
                <h1>Verificación (3/3)</h1>
                <p style="font-size:14px;">Hemos enviado un código a <strong id="display-email-verify"><?php echo $_SESSION['temp_register']['email'] ?? 'tu correo'; ?></strong>.</p>
                <div class="floating-label-group">
                    <input type="text" id="reg-code" class="floating-input" required placeholder=" " maxlength="12" style="letter-spacing: 2px; text-transform: uppercase; font-weight:bold;">
                    <label for="reg-code" class="floating-label">Código</label>
                </div>
                <button class="form-button" id="btn-register-step3">Verificar y Crear Cuenta</button>
                <div id="register-error-3" class="form-error-message"></div>
                <div class="form-footer-link"><a href="#" id="btn-back-step2">Cambiar datos</a></div>
            </div>

        </div>
    </div>
</div>