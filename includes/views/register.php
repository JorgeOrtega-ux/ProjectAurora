<div class="view-content animate-fade-in">
    <div class="auth-wrapper">
        <div class="auth-container">
            <h1 class="auth-title">Crear cuenta</h1>
            <form id="form-register" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="input-group">
                    <input type="text" id="reg-username" class="input-field" placeholder=" " required>
                    <label for="reg-username" class="input-label">Nombre de usuario</label>
                </div>
                <div class="input-group">
                    <input type="email" id="reg-email" class="input-field" placeholder=" " required>
                    <label for="reg-email" class="input-label">Correo electrónico</label>
                </div>
                <div class="input-group">
                    <input type="password" id="reg-password" class="input-field" placeholder=" " required>
                    <label for="reg-password" class="input-label">Contraseña</label>
                </div>
                <button type="submit" class="auth-button">Continuar</button>
            </form>
            <div class="auth-links">
                <span style="font-size: 14px; color: #666;">
                    ¿Ya tienes una cuenta? 
                    <a href="/ProjectAurora/login" class="link-secondary link-bold" data-nav="/ProjectAurora/login">Inicia sesión</a>
                </span>
            </div>
        </div>
    </div>
</div>