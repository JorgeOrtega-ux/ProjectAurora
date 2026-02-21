<div class="view-content">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">
            <div class="component-header-centered">
                <h1 id="auth-title"><?= t('login.title') ?></h1>
                <p id="auth-subtitle"><?= t('login.subtitle') ?></p>
            </div>

            <div id="form-login" class="component-stage-form">
                <input type="hidden" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="email" id="login-email" class="component-text-input" required placeholder=" ">
                        <label for="login-email" class="component-label-floating"><?= t('login.email') ?></label>
                    </div>
                    <div class="component-input-wrapper">
                        <input type="password" id="login-password" class="component-text-input has-action" required placeholder=" ">
                        <label for="login-password" class="component-label-floating"><?= t('login.password') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                </div>
                <a href="/ProjectAurora/forgot-password" data-nav="/ProjectAurora/forgot-password" class="component-link-simple"><?= t('login.forgot') ?></a>
                <button type="button" id="btn-login" class="component-button component-button--large primary"><?= t('login.btn') ?></button>
                <div id="login-error" class="component-message-error" style="display: none;"></div>
                <div class="component-text-footer">
                    <p><?= t('login.no_account') ?> <a href="/ProjectAurora/register" data-nav="/ProjectAurora/register"><?= t('login.register') ?></a></p>
                </div>
            </div>

            <div id="form-login-2fa" class="component-stage-form" style="display: none;">
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="text" id="login-2fa-code" class="component-text-input" required placeholder=" " autocomplete="one-time-code">
                        <label for="login-2fa-code" class="component-label-floating">Código de autenticación</label>
                    </div>
                    <p style="font-size: 13px; color: var(--color-text-muted); margin-top: 8px;">
                        Abre tu app de autenticación e ingresa el código de 6 dígitos. También puedes usar uno de tus códigos de recuperación de 8 dígitos.
                    </p>
                </div>
                <button type="button" id="btn-verify-2fa" class="component-button component-button--large primary">Verificar</button>
                <div id="login-2fa-error" class="component-message-error" style="display: none;"></div>
                <div class="component-text-footer">
                    <p><a href="/ProjectAurora/login" id="btn-back-login" class="component-link-simple">Volver al inicio de sesión</a></p>
                </div>
            </div>

        </div>
    </div>
</div>