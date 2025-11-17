<div class="section-content overflow-y active" data-section="register">
    
    <div class="section-center-wrapper">

        <div class="form-container">
            
            <h1>Registro (1/3)</h1>
            <p>Comencemos con tus credenciales.</p>

            <div class="floating-label-group">
                <input 
                    type="email" 
                    id="reg-email" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                    value="<?php echo isset($_SESSION['temp_register']['email']) ? htmlspecialchars($_SESSION['temp_register']['email']) : ''; ?>"
                >
                <label for="reg-email" class="floating-label">Correo Electrónico</label>
            </div>

            <div class="floating-label-group">
                <input 
                    type="password" 
                    id="reg-password" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                >
                <label for="reg-password" class="floating-label">Contraseña</label>
                
                <button type="button" class="password-toggle-btn">
                    <span class="material-symbols-rounded">visibility</span>
                </button>
            </div>

            <button class="form-button" id="btn-register-step1">Siguiente</button>

            <div id="register-error" class="form-error-message"></div>

            <div class="form-footer-link">
                ¿Ya tienes una cuenta? <a href="#" onclick="event.preventDefault(); navigateTo('login')">Iniciar sesión</a>
            </div>

        </div>

    </div>

</div>