<?php
// includes/sections/auth/reset-password.php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Capturar el token de la URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$tokenIsValid = false;

// 2. Validar el token en la Base de Datos (Server Side Render)
// Nota: $pdo está disponible porque este archivo se carga desde loader.php o index.php
if (!empty($token) && isset($pdo)) {
    try {
        // [SEGURIDAD] Hasheamos el token recibido por GET para compararlo con la BD
        $tokenHash = hash('sha256', $token);

        // Buscamos usando el hash
        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE code = ? AND code_type = 'recovery' AND expires_at > NOW()");
        $stmt->execute([$tokenHash]);
        
        if ($stmt->fetch()) {
            $tokenIsValid = true;
        }
    } catch (Exception $e) {
        // Si hay error de BD, asumimos inválido por seguridad
        $tokenIsValid = false;
    }
}
?>

<div class="section-content active" data-section="reset-password">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <?php if ($tokenIsValid): ?>
                <div class="auth-step-container active">
                    <h1>Nueva Contraseña</h1>
                    <p>Crea una contraseña segura para tu cuenta.</p>
                    
                    <input type="hidden" data-input="reset-token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="floating-label-group">
                        <input type="password" data-input="reset-pass" class="floating-input" required placeholder=" " minlength="8">
                        <label class="floating-label">Nueva Contraseña</label>
                        <button type="button" class="floating-input-btn"><span class="material-symbols-rounded">visibility</span></button>
                    </div>

                    <div class="floating-label-group">
                        <input type="password" data-input="reset-pass-confirm" class="floating-input" required placeholder=" " minlength="8">
                        <label class="floating-label">Repetir Contraseña</label>
                        <button type="button" class="floating-input-btn"><span class="material-symbols-rounded">visibility</span></button>
                    </div>

                    <button class="form-button" data-action="reset-final-submit">Cambiar Contraseña</button>
                    <div data-error="reset-error" class="form-error-message"></div>
                </div>

            <?php else: ?>
                <div style="text-align: center; margin-bottom: 20px;">
                    <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f;">link_off</span>
                </div>

                <div style="
                    border: 1px solid #e0e0e0; 
                    border-radius: 8px; 
                    padding: 20px; 
                    text-align: left; 
                    background-color: #fff;
                ">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #d32f2f;">Error: Enlace no válido</h3>
                    <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.5;">
                        El enlace de recuperación es inválido o ha expirado. Es posible que ya haya sido utilizado.
                        <br><br>
                        Por favor, <a href="#" onclick="event.preventDefault(); navigateTo('forgot-password')" style="color: #000; font-weight: 600; text-decoration: underline;">solicita uno nuevo aquí</a>.
                    </p>
                </div>

                <div style="margin-top: 25px; text-align: center;">
                    <a href="#" onclick="event.preventDefault(); navigateTo('login')" style="color:#666; text-decoration:none; font-size:14px; font-weight:500;">
                        <span class="material-symbols-rounded" style="font-size:16px; vertical-align: text-bottom;">arrow_back</span> 
                        Volver al inicio
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>