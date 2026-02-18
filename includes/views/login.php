<div class="view-content animate-fade-in">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">

            <div class="component-header-centered">
                <h1 id="auth-title">Iniciar Sesión</h1>
                <p id="auth-subtitle">Ingresa a tu cuenta para continuar</p>
            </div>

            <form id="form-login" class="component-stage-form">
                
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="email" id="login-email" class="component-text-input" required placeholder=" ">
                        <label for="login-email" class="component-label-floating">Correo electrónico</label>
                    </div>

                    <div class="component-input-wrapper">
                        <input type="password" id="login-password" class="component-text-input has-action" required placeholder=" ">
                        <label for="login-password" class="component-label-floating">Contraseña</label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                </div>

                <a href="#" class="component-link-simple">¿Olvidaste tu contraseña?</a>

                <button type="submit" class="component-button component-button--large primary">
                    Iniciar Sesión
                </button>

                <div id="login-error" class="component-message-error"></div>

                <div class="component-text-footer">
                    <p>¿No tienes una cuenta? <a href="/ProjectAurora/register" data-nav="/ProjectAurora/register">Regístrate</a></p>
                </div>
            </form>

        </div>
    </div>
</div>