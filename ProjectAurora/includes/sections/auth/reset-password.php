<?php
// includes/sections/auth/reset-password.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Capturar el token de la URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
?>

<div class="section-content active" data-section="reset-password">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <?php if (empty($token)): ?>
                <div style="text-align:center;">
                    <span class="material-symbols-rounded" style="font-size:48px; color:#d32f2f;">link_off</span>
                    <h1>Enlace inválido</h1>
                    <p>No se encontró un token de seguridad en el enlace.</p>
                    <button class="form-button" onclick="navigateTo('login')" style="margin-top:20px;">Ir al inicio</button>
                </div>
            <?php else: ?>
                <div class="auth-step-container active">
                    <h1>Nueva Contraseña</h1>
                    <p>Crea una contraseña segura para tu cuenta.</p>
                    
                    <input type="hidden" data-input="reset-token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="floating-label-group">
                        <input type="password" data-input="reset-pass" class="floating-input" required placeholder=" " minlength="8">
                        <label class="floating-label">Nueva Contraseña</label>
                        <button type="button" class="floating-input-btn"><span class="material-symbols-rounded">visibility</span></button>
                    </div>

                    <button class="form-button" data-action="reset-final-submit">Cambiar Contraseña</button>
                    <div data-error="reset-error" class="form-error-message"></div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>