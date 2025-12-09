<?php
// includes/sections/register.php

// 1. Detección de sub-ruta actual
$requestUri = $_SERVER['REQUEST_URI'];
$isStep2 = strpos($requestUri, 'register/aditional-data') !== false;
$isStep3 = strpos($requestUri, 'register/verify') !== false;

// 2. Verificación de datos de sesión previos (State Check)
$hasDataForStep2 = isset($_SESSION['temp_register']) && !empty($_SESSION['temp_register']);
$hasDataForStep3 = isset($_SESSION['pending_verification_email']) && !empty($_SESSION['pending_verification_email']);

// 3. Determinar si hay un acceso inválido (Salto de pasos)
$invalidAccess = false;
$errorTitle = "";
$errorMessage = "";

if ($isStep2 && !$hasDataForStep2) {
    $invalidAccess = true;
    $errorTitle = "Faltan datos previos";
    $errorMessage = "No has completado el registro de credenciales (correo y contraseña). No puedes saltar pasos.";
} elseif ($isStep3 && !$hasDataForStep3) {
    $invalidAccess = true;
    $errorTitle = "Código no enviado";
    $errorMessage = "No hay un proceso de verificación activo. Debes completar el registro primero.";
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if ($invalidAccess): ?>
            <div class="auth-header">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f; margin-bottom: 10px;">history_toggle_off</span>
                <h1 style="color: #d32f2f;"><?php echo $errorTitle; ?></h1>
                <p><?php echo $errorMessage; ?></p>
            </div>

            <div class="alert error" style="margin-top: 20px;">
                Por favor, inicia el proceso desde el principio para asegurar la creación de tu cuenta.
            </div>

            <a href="<?php echo $basePath; ?>register" class="btn-primary" style="display: block; text-decoration: none; line-height: 55px; margin-top: 20px;">
                Volver al inicio del registro
            </a>

        <?php elseif ($isStep3): ?>
            <div class="auth-header">
                <h1>Verificación</h1>
                <p>Hemos enviado un código a <strong><?php echo htmlspecialchars($_SESSION['pending_verification_email']); ?></strong>.</p>
            </div>

            <input type="hidden" id="verify-action" name="action" value="verify_code">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="code" id="code" required placeholder=" " maxlength="6" style="letter-spacing: 4px; font-weight: bold; text-align: center;">
                    <label for="code" style="left: 50%; transform: translateX(-50%) translateY(-50%);">Código de 6 dígitos</label>
                </div>
            </div>

            <button type="button" id="btn-verify" class="btn-primary">Verificar y Crear Cuenta</button>

            <div style="margin-top: 15px; font-size: 14px; color: #666;">
                <a href="#" id="btn-resend-code" style="color: #999; pointer-events: none; text-decoration: none;">
                    Reenviar código de verificación <span id="register-timer">(60)</span>
                </a>
            </div>

            <div class="auth-footer">
                <p style="margin-top: 8px;"><a href="<?php echo $basePath; ?>register" style="font-size: 12px; color: #999;">Cancelar registro</a></p>
            </div>

        <?php elseif ($isStep2): ?>
            <div class="auth-header">
                <h1>Casi terminamos</h1>
                <p>Paso 2: Elige tu identidad</p>
            </div>

            <input type="hidden" id="register-action-2" name="action" value="register_step_2">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="username" id="username" required placeholder=" ">
                    <label for="username">Nombre de Usuario</label>
                </div>
            </div>

            <button type="button" id="btn-register-step-2" class="btn-primary">Enviar Código</button>
            
            <div class="auth-footer">
                <p><a href="<?php echo $basePath; ?>register">Volver (Editar correo)</a></p>
            </div>

        <?php else: ?>
            <div class="auth-header">
                <h1>Crear Cuenta</h1>
                <p>Paso 1: Credenciales básicas</p>
            </div>

            <input type="hidden" id="register-action-1" name="action" value="register_step_1">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="email" name="email" id="email" required placeholder=" ">
                    <label for="email">Correo Electrónico</label>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="password" required placeholder=" ">
                    <label for="password">Contraseña</label>
                </div>
            </div>

            <button type="button" id="btn-register-step-1" class="btn-primary">Continuar</button>

            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="<?php echo $basePath; ?>login">Inicia sesión</a></p>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error']) && !$invalidAccess): ?>
            <div class="alert error" style="margin-top: 16px; margin-bottom: 0;">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']); 
                ?>
            </div>
        <?php endif; ?>

    </div>
</div>