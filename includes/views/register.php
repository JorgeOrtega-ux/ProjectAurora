<div class="view-content animate-fade-in">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">

            <div class="component-header-centered">
                <h1 id="auth-title">Crear Cuenta</h1>
                <p id="auth-subtitle">Regístrate para comenzar</p>
            </div>

            <form id="form-register" class="component-stage-form">
                
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="text" id="reg-username" class="component-text-input" required placeholder=" ">
                        <label for="reg-username" class="component-label-floating">Nombre de usuario</label>
                    </div>

                    <div class="component-input-wrapper">
                        <input type="email" id="reg-email" class="component-text-input" required placeholder=" ">
                        <label for="reg-email" class="component-label-floating">Correo electrónico</label>
                    </div>

                    <div class="component-input-wrapper">
                        <input type="password" id="reg-password" class="component-text-input has-action" required placeholder=" ">
                        <label for="reg-password" class="component-label-floating">Contraseña</label>
                        </div>
                </div>

                <button type="submit" class="component-button component-button--large primary">
                    Crear Cuenta
                </button>

                <div id="register-error" class="component-message-error"></div>

                <div class="component-text-footer">
                    <p>¿Ya tienes una cuenta? <a href="/ProjectAurora/login" data-nav="/ProjectAurora/login">Inicia sesión</a></p>
                </div>
            </form>

        </div>
    </div>
</div>