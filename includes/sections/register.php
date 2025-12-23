<?php
// includes/sections/register.php

// 0. Corrección de ruta base
$basePath = isset($basePath) ? $basePath : '/ProjectAurora/';

// 1. Detección de la sub-ruta actual
$currentRoute = isset($section) ? $section : ($currentSection ?? 'register');

$isStep2 = $currentRoute === 'register/aditional-data';
$isStep3 = $currentRoute === 'register/verification-account';

// 2. Verificación de datos de sesión previos
$hasDataForStep2 = isset($_SESSION['temp_register']) && !empty($_SESSION['temp_register']);
$hasDataForStep3 = isset($_SESSION['pending_verification_email']) && !empty($_SESSION['pending_verification_email']);

// 3. Lógica de Error 409 (Backend Real)
$invalidAccess = false;
$errorMessage = "";

if ($isStep2 && !$hasDataForStep2) {
    http_response_code(409);
    $invalidAccess = true;
    // MENSAJE EDITADO: Sin mencionar "Step 2"
    $errorMessage = "Required session data missing. Flow sequence violation.";

} elseif ($isStep3 && !$hasDataForStep3) {
    http_response_code(409);
    $invalidAccess = true;
    $errorMessage = "Verification context not found. Session may have expired.";
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if ($invalidAccess): ?>
            <div class="crash-header">
                <span class="material-symbols-rounded crash-icon">
                    token
                </span>
                
                <h1 class="crash-title">
                    ¡Ups, ha ocurrido un error!
                </h1>
            </div>

            <div class="crash-code-box">
                <span class="crash-text-meta">Route Error (409): {</span><br>
                &nbsp;&nbsp;"error": {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;"message": "<span class="crash-text-error"><?php echo $errorMessage; ?></span>",<br>
                &nbsp;&nbsp;&nbsp;&nbsp;"type": "invalid_request_error",<br>
                &nbsp;&nbsp;&nbsp;&nbsp;"param": null,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;"code": "invalid_state"<br>
                &nbsp;&nbsp;}<br>
                }
            </div>

        <?php elseif ($isStep3): ?>
            <div class="auth-header">
                <h1>Verifica tu cuenta</h1>
                <p>Ingresa el código enviado a <strong><?php echo htmlspecialchars($_SESSION['pending_verification_email'] ?? ''); ?></strong>.</p>
            </div>

            <input type="hidden" id="verify-action" name="action" value="verify_code">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="code" id="verification_code" class="input-code-verify" required placeholder=" " maxlength="6" style="letter-spacing: 4px; text-align: center; font-size: 20px;">
                    <label for="verification_code" class="label-centered">Código de verificación</label>
                </div>
            </div>

            <button type="button" id="btn-finish" class="btn-primary">Verificar Cuenta</button>

            <div class="auth-resend-wrapper" style="margin-top: 16px;">
                <a href="#" id="btn-resend-code" class="link-disabled" style="pointer-events: none; color: rgb(153, 153, 153); text-decoration: none; font-size: 14px;">
                    Reenviar código de verificación <span id="register-timer">(60)</span>
                </a>
            </div>

        <?php elseif ($isStep2): ?>
            <div class="auth-header">
                <h1>Te damos la bienvenida</h1>
                <p>Elige cómo quieres que te llamen.</p>
            </div>

            <input type="hidden" id="register-action-2" name="action" value="register_step_2">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="username" id="username" required placeholder=" ">
                    <label for="username">Nombre de usuario</label>
                    <button type="button" class="btn-input-action" data-action="generate-username" tabindex="-1" title="Generar nombre aleatorio">
                        <span class="material-symbols-rounded">autorenew</span>
                    </button>
                </div>
            </div>

            <div style="display: flex; gap: 8px;">
                <a href="<?php echo $basePath; ?>register" data-nav="register" class="btn-primary mt-16 btn-back" style="background: #eee; color: #333; width: 40%; display:flex; justify-content:center; align-items:center; text-decoration:none;">Volver</a>
                
                <button type="button" id="btn-next-2" class="btn-primary mt-16" style="width: 60%;">Continuar</button>
            </div>

        <?php else: ?>
            <div class="auth-header">
                <h1>Crear Cuenta</h1>
                <p>Ingresa tus datos de acceso</p>
            </div>

            <input type="hidden" id="register-action-1" name="action" value="register_step_1">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="email" name="email" id="email" required placeholder=" ">
                    <label for="email">Correo electrónico</label>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="password" required placeholder=" ">
                    <label for="password">Contraseña</label>
                    <button type="button" class="btn-input-action" data-action="toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
            </div>

            <button type="button" id="btn-next-1" class="btn-primary">Continuar</button>

            <div class="auth-footer">
                <p>
                    ¿Ya tienes cuenta? 
                    <a href="<?php echo $basePath; ?>login" data-nav="login" class="link-primary">Inicia sesión</a>
                </p>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error']) && !$invalidAccess): ?>
            <div class="alert error mt-16 mb-0">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

    </div>
</div>