<div class="section-content overflow-y active" data-section="register">
    
    <div class="section-center-wrapper">

        <div class="form-container">
            
            <h1>Registro</h1>
            <p>Crea tu cuenta para continuar.</p>

            <div class="floating-label-group">
                <input 
                    type="email" 
                    id="register-email" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                >
                <label for="register-email" class="floating-label">Correo Electrónico</label>
            </div>

            <div class="floating-label-group">
                <input 
                    type="password" 
                    id="register-password" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                >
                <label for="register-password" class="floating-label">Contraseña</label>
                
                <button type="button" class="password-toggle-btn">
                    <span class="material-symbols-rounded">visibility</span>
                </button>
            </div>

            <button class="form-button" id="btn-register-submit">Continuar</button>

            <div class="form-footer-link">
                ¿Ya tienes una cuenta? <a href="#" onclick="event.preventDefault(); navigateTo('login')">Iniciar sesión</a>
            </div>

        </div>

    </div>

</div>