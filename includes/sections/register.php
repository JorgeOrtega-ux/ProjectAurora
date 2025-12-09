<?php
// Detectar la sub-ruta actual
$requestUri = $_SERVER['REQUEST_URI'];
$isStep2 = strpos($requestUri, 'register/aditional-data') !== false;
?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if (!$isStep2): ?>
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

        <?php else: ?>
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
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert error" style="margin-top: 16px; margin-bottom: 0;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

    </div>
</div>