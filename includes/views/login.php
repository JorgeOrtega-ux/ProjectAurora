<div class="view-content animate-fade-in">
    <div class="auth-wrapper">
        <div class="auth-container">
            
            <h1 class="auth-title">Iniciar sesión</h1>

            <form onsubmit="event.preventDefault();" style="display: flex; flex-direction: column; gap: 16px;">
                
                <div class="input-group">
                    <input type="email" id="login-email" class="input-field" placeholder=" " required>
                    <label for="login-email" class="input-label">Correo electrónico</label>
                </div>

                <div class="input-group">
                    <input type="password" id="login-password" class="input-field" placeholder=" " required>
                    <label for="login-password" class="input-label">Contraseña</label>
                </div>

                <button type="submit" class="auth-button">
                    Continuar
                </button>

            </form>

            <div class="auth-links">
                <a href="#" class="link-secondary">¿Olvidaste la contraseña?</a>
                
                <span style="font-size: 14px; color: #666;">
                    ¿No tienes una cuenta? 
                    <a href="/ProjectAurora/register" class="link-secondary link-bold" data-nav="/ProjectAurora/register">Regístrate</a>
                </span>
            </div>

        </div>
    </div>
</div>