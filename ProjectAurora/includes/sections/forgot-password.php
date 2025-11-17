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
            
            <div style="margin-bottom: 10px;">
                <a href="#" onclick="event.preventDefault(); navigateTo('login')" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                    <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Volver al Login
                </a>
            </div>

            <div data-step="rec-1" style="display: <?php echo ($initialStep === 1) ? 'block' : 'none'; ?>;">
                <h1>Recuperar Cuenta (1/3)</h1>
                <p>Ingresa tu correo para buscar tu cuenta.</p>
                
                <div class="floating-label-group">
                    <input type="email" data-input="rec-email" class="floating-input" required placeholder=" " value="<?php echo $_SESSION['temp_recovery']['email'] ?? ''; ?>">
                    <label class="floating-label">Correo Electrónico</label>
                </div>

                <button class="form-button" data-action="rec-step1">Enviar Código</button>
                <div data-error="rec-1" class="form-error-message"></div>
            </div>

            <div data-step="rec-2" style="display: <?php echo ($initialStep === 2) ? 'block' : 'none'; ?>;">
                <h1>Verificación (2/3)</h1>
                <p style="font-size:14px;">Enviamos un código a <strong data-display="rec-email"><?php echo $_SESSION['temp_recovery']['email'] ?? 'tu correo'; ?></strong>.</p>
                
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

            <div data-step="rec-3" style="display: <?php echo ($initialStep === 3) ? 'block' : 'none'; ?>;">
                <h1>Nueva Contraseña (3/3)</h1>
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