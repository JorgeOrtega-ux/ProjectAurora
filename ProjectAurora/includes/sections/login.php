<div class="section-content overflow-y active" data-section="login">
    
    <div class="section-center-wrapper">

        <div class="form-container">
            
            <h1>Iniciar Sesión</h1>
            <p>Bienvenido de nuevo.</p>

            <div class="floating-label-group">
                <input 
                    type="email" 
                    id="login-email" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                >
                <label for="login-email" class="floating-label">Correo Electrónico</label>
            </div>

            <div class="floating-label-group">
                <input 
                    type="password" 
                    id="login-password" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                >
                <label for="login-password" class="floating-label">Contraseña</label>
                
                <button type="button" class="password-toggle-btn">
                    <span class="material-symbols-rounded">visibility</span>
                </button>
            </div>

            <button class="form-button" id="btn-login-submit">Continuar</button>

            <div class="form-footer-link">
                ¿No tienes una cuenta? <a href="#" onclick="event.preventDefault(); navigateTo('register')">Regístrate</a>
            </div>

        </div>

    </div>

</div>