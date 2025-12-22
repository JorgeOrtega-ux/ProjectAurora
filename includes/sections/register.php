<style>
    /* Estilos locales para manejar la visibilidad de los pasos */
    .reg-step { display: none; }
    .reg-step.active { display: block; animation: fadeIn 0.3s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <div class="auth-header">
            <h1 id="step-title">Crear Cuenta</h1>
            <p id="step-desc">Ingresa tus datos de acceso</p>
        </div>
        
        <div id="registerContainer" class="form-groups-wrapper">
            
            <div id="step-1" class="reg-step">
                <div class="form-group">
                    <input type="email" name="email" id="email" placeholder=" " required>
                    <label for="email">Correo electrónico</label>
                </div>
                <div class="form-group">
                    <input type="password" name="password" id="password" placeholder=" " required>
                    <label for="password">Contraseña</label>
                    <button type="button" class="btn-toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
                <button type="button" id="btn-next-1" class="btn-primary mt-16">Continuar</button>
            </div>

            <div id="step-2" class="reg-step">
                <div class="form-group">
                    <input type="text" name="username" id="username" placeholder=" " required>
                    <label for="username">Nombre de usuario</label>
                    <button type="button" class="btn-generate-username" tabindex="-1" title="Generar nombre aleatorio">
                        <span class="material-symbols-rounded">autorenew</span>
                    </button>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-primary mt-16 btn-back" style="background: #eee; color: #333; width: 40%;" data-go="1">Volver</button>
                    <button type="button" id="btn-next-2" class="btn-primary mt-16" style="width: 60%;">Continuar</button>
                </div>
            </div>

            <div id="step-3" class="reg-step">
                <p style="font-size: 13px; margin-bottom: 12px; color: #666;">
                    Hemos enviado un código de 6 dígitos a tu correo.
                </p>
                <div class="form-group">
                    <input type="text" name="verification_code" id="verification_code" placeholder=" " maxlength="6" style="letter-spacing: 4px; text-align: center; font-size: 20px;" required>
                    <label for="verification_code">Código de verificación</label>
                </div>
                <button type="button" id="btn-finish" class="btn-primary mt-16">Verificar y Crear Cuenta</button>
            </div>

        </div>

        <div class="auth-footer">
            <p>
                ¿Ya tienes cuenta? 
                <a href="<?php echo $basePath; ?>login" class="link-primary">Inicia sesión</a>
            </p>
        </div>
    </div>
</div>

<script>
    window.CURRENT_URI = '<?php echo $_SERVER['REQUEST_URI']; ?>';
</script>