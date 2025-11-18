<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$initialStep = 1;
if (isset($_SESSION['temp_recovery']['step'])) {
    $initialStep = $_SESSION['temp_recovery']['step'];
}
?>

<div class="section-content overflow-y active" data-section="forgot-password">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <div class="auth-back-link">
                </div>

            <div data-step="rec-1" class="auth-step-container <?php echo ($initialStep === 1) ? 'active' : ''; ?>">
                <h1>Recuperar Cuenta</h1>
                <p>Ingresa tu correo para buscar tu cuenta.</p>
                
                <div class="floating-label-group">
                    <input type="email" data-input="rec-email" class="floating-input" required placeholder=" " value="<?php echo $_SESSION['temp_recovery']['email'] ?? ''; ?>">
                    <label class="floating-label">Correo Electrónico</label>
                </div>

                <button class="form-button" data-action="rec-step1">Enviar Código</button>
                <div data-error="rec-1" class="form-error-message"></div>
            </div>

            <div data-step="rec-2" class="auth-step-container <?php echo ($initialStep === 2) ? 'active' : ''; ?>">
                <h1>Verificación</h1>
                <p style="font-size:14px;">
                    Si la cuenta <strong data-display="rec-email"><?php echo htmlspecialchars($_SESSION['temp_recovery']['email'] ?? 'tu correo'); ?></strong> existe, 
                    hemos enviado un código de verificación.
                </p>
                
                <div class="floating-label-group">
                    <input type="text" data-input="rec-code" class="floating-input" required placeholder=" " maxlength="12" style="letter-spacing: 2px; text-transform: uppercase; font-weight:bold;">
                    <label class="floating-label">Código de Recuperación</label>
                </div>

                <button class="form-button" data-action="rec-step2">Verificar Código</button>
                <div data-error="rec-2" class="form-error-message"></div>
                
                <div class="form-footer-link">
                    <a href="#" data-action="rec-resend">Reenviar código / Cambiar correo</a>
                </div>
            </div>
            <div data-step="rec-3" class="auth-step-container <?php echo ($initialStep === 3) ? 'active' : ''; ?>">
                <h1>Nueva Contraseña</h1>
                <p>Crea una nueva contraseña segura.</p>
                
                <div class="floating-label-group">
                    <input type="password" data-input="rec-pass" class="floating-input" required placeholder=" " minlength="8">
                    <label class="floating-label">Nueva Contraseña</label>
                    <button type="button" class="password-toggle-btn"><span class="material-symbols-rounded">visibility</span></button>
                </div>

                <button class="form-button" data-action="rec-step3">Actualizar Contraseña</button>
                <div data-error="rec-3" class="form-error-message"></div>
            </div>

        </div>
    </div>
</div>