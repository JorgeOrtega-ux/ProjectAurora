<?php
// Detectar la sub-ruta actual
$requestUri = $_SERVER['REQUEST_URI'];
$isStep2 = strpos($requestUri, 'register/aditional-data') !== false;
$isStep3 = strpos($requestUri, 'register/verify') !== false;
?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if ($isStep3): ?>
            <div class="auth-header">
                <h1>Verificación</h1>
                <p>Hemos enviado un código a tu correo.</p>
            </div>

            <input type="hidden" id="verify-action" name="action" value="verify_code">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="code" id="code" required placeholder=" " maxlength="6" style="letter-spacing: 4px; font-weight: bold; text-align: center;">
                    <label for="code" style="left: 50%; transform: translateX(-50%) translateY(-50%);">Código de 6 dígitos</label>
                </div>
            </div>

            <button type="button" id="btn-verify" class="btn-primary">Verificar y Crear Cuenta</button>

            <div class="auth-footer">
                <p>¿No recibiste el código? <a href="#">Reenviar</a></p>
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
                <p><a href="<?php echo $basePath; ?>register">Volver</a></p>
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

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error" style="margin-top: 16px; margin-bottom: 0;">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']); 
                ?>
            </div>
        <?php endif; ?>

    </div>
</div>