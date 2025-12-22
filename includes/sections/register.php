<?php
// includes/sections/register.php

// 1. Detección de la sub-ruta actual
$requestUri = $_SERVER['REQUEST_URI'];
// Adaptamos las rutas a las que usas en ProjectAurora
$isStep2 = strpos($requestUri, 'register/aditional-data') !== false;
$isStep3 = strpos($requestUri, 'register/verification-account') !== false;

// 2. Verificación de datos de sesión previos (State Check)
// IMPORTANTE: Tu API (auth_handler.php) debe crear estas variables de sesión
// al completar los pasos anteriores exitosamente.
$hasDataForStep2 = isset($_SESSION['temp_register']) && !empty($_SESSION['temp_register']);
$hasDataForStep3 = isset($_SESSION['pending_verification_email']) && !empty($_SESSION['pending_verification_email']);

// 3. Determinar si hay un acceso inválido (Salto de pasos)
$invalidAccess = false;
$errorTitle = "";
$errorMessage = "";

if ($isStep2 && !$hasDataForStep2) {
    // Si intenta entrar al paso 2 sin haber completado el paso 1
    $invalidAccess = true;
    $errorTitle = "Datos faltantes";
    $errorMessage = "No has completado el registro de credenciales. No puedes saltar pasos.";
} elseif ($isStep3 && !$hasDataForStep3) {
    // Si intenta entrar a verificar sin tener un correo pendiente
    $invalidAccess = true;
    $errorTitle = "Verificación no disponible";
    $errorMessage = "No hay un proceso de verificación activo para esta sesión.";
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if ($invalidAccess): ?>
            <div class="auth-header">
                <span class="material-symbols-rounded auth-error-icon" style="font-size: 48px; color: #d32f2f; margin-bottom:10px;">history_toggle_off</span>
                <h1 class="text-danger" style="color: #d32f2f; margin: 0;"><?php echo $errorTitle; ?></h1>
                <p style="color: #666; margin-top: 5px;"><?php echo $errorMessage; ?></p>
            </div>

            <div class="alert error mt-20" style="background: #ffebee; color: #b71c1c; padding: 10px; border-radius: 8px; margin-top: 20px; font-size: 14px;">
                Por seguridad, inicia el proceso desde el principio.
            </div>

            <a href="<?php echo $basePath; ?>register" class="btn-primary btn-block-link" style="display: flex; justify-content: center; align-items: center; text-decoration: none; margin-top: 20px;">
                Volver al inicio
            </a>

        <?php elseif ($isStep3): ?>
            <div class="auth-header">
                <h1>Verifica tu cuenta</h1>
                <p>Ingresa el código enviado a <strong><?php echo htmlspecialchars($_SESSION['pending_verification_email']); ?></strong>.</p>
            </div>

            <input type="hidden" id="verify-action" name="action" value="verify_code">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="code" id="verification_code" class="input-code-verify" required placeholder=" " maxlength="6" style="letter-spacing: 4px; text-align: center; font-size: 20px;">
                    <label for="verification_code" class="label-centered">Código de verificación</label>
                </div>
            </div>

            <button type="button" id="btn-finish" class="btn-primary">Verificar Cuenta</button>

            <div class="auth-resend-wrapper">
                <a href="#" id="btn-resend-code" class="link-disabled">
                    Reenviar código <span id="register-timer">(60)</span>
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
                    <button type="button" class="btn-generate-username" tabindex="-1" title="Generar nombre aleatorio">
                        <span class="material-symbols-rounded">autorenew</span>
                    </button>
                </div>
            </div>

            <div style="display: flex; gap: 8px;">
                <a href="<?php echo $basePath; ?>register" class="btn-primary mt-16 btn-back" style="background: #eee; color: #333; width: 40%; display:flex; justify-content:center; align-items:center; text-decoration:none;">Volver</a>
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
                    <button type="button" class="btn-toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
            </div>

            <button type="button" id="btn-next-1" class="btn-primary">Continuar</button>

            <div class="auth-footer">
                <p>
                    ¿Ya tienes cuenta? 
                    <a href="<?php echo $basePath; ?>login" class="link-primary">Inicia sesión</a>
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