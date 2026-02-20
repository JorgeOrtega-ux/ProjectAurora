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
                <div id="login-error" class="component-message-error"></div>
                <div class="component-text-footer">
                    <p><?= t('login.no_account') ?> <a href="/ProjectAurora/register" data-nav="/ProjectAurora/register"><?= t('login.register') ?></a></p>
                </div>
            </div>
        </div>
    </div>
</div>