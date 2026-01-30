<?php
// includes/sections/auth/login.php

// Detectar ruta actual para manejar estados iniciales
$currentRoute = isset($section) ? $section : ($currentSection ?? 'login');
$isVerificationStep = $currentRoute === 'login/verification-aditional';

// Seguridad: Si intentan entrar directo a la URL de verificación sin sesión pendiente, mandar al inicio
if ($isVerificationStep && !isset($_SESSION['2fa_pending_user_id'])) {
    echo "<script>window.location.href = '" . $basePath . "login';</script>";
    exit;
}

// Clases CSS iniciales basadas en la ruta
$stage1Class = $isVerificationStep ? 'disabled' : '';
$stage2Class = $isVerificationStep ? '' : 'disabled';
?>

<div class="component-layout-centered">
    <div class="component-card component-card--compact">

        <div class="component-header-centered">
            <h1 id="auth-title"><?php echo $isVerificationStep ? $i18n->t('auth.2fa.title') : $i18n->t('auth.login.title'); ?></h1>
            <p id="auth-subtitle"><?php echo $isVerificationStep ? $i18n->t('auth.2fa.subtitle') : $i18n->t('auth.login.subtitle'); ?></p>
        </div>

        <div id="login-stage-1" class="component-stage-form <?php echo $stage1Class; ?>">
            <input type="hidden" id="login-action" name="action" value="login">

            <div class="component-form-group">
                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="email" name="email" id="email" class="component-text-input" required placeholder=" ">
                    <label for="email" class="component-label-floating"><?php echo $i18n->t('auth.field.email'); ?></label>
                </div>

                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="password" name="password" id="password" class="component-text-input has-action" required placeholder=" ">
                    <label for="password" class="component-label-floating"><?php echo $i18n->t('auth.field.password'); ?></label>
                    <button type="button" class="component-input-action" data-action="toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
            </div>

            <div id="turnstile-container" style="position: absolute; opacity: 0; pointer-events: none;"></div>

            <a href="<?php echo $basePath; ?>recover-password" data-nav="recover-password" class="component-link-simple"><?php echo $i18n->t('auth.forgot_password'); ?></a>

            <button type="button" id="btn-login" class="component-button component-button--large primary"><?php echo $i18n->t('auth.btn.login'); ?></button>

            <div class="component-text-footer">
                <p><?php echo $i18n->t('auth.no_account'); ?> <a href="<?php echo $basePath; ?>register" data-nav="register"><?php echo $i18n->t('auth.register_link'); ?></a></p>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="component-message component-message--error">
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="login-stage-2" class="component-stage-form <?php echo $stage2Class; ?>">
            
            <div class="component-form-group">
                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="text" id="2fa-code" class="component-text-input" placeholder=" " maxlength="10" style="text-align: center; letter-spacing: 4px; font-size: 18px;" autocomplete="off">
                    <label for="2fa-code" class="component-label-floating" id="label-2fa-code" style="left: 50%; transform: translateX(-50%) translateY(-50%);">
                        <?php echo $i18n->t('auth.2fa.field_code'); ?>
                    </label>
                </div>
            </div>

            <a href="#" id="toggle-recovery-mode" class="component-link-simple">
                Usar código de recuperación
            </a>

            <button type="button" id="btn-verify-2fa" class="component-button component-button--large primary"><?php echo $i18n->t('auth.btn.verify'); ?></button>

            <div class="component-text-footer">
                <p>
                    <a href="<?php echo $basePath; ?>login" data-nav="login">
                        <?php echo $i18n->t('auth.2fa.back'); ?>
                    </a>
                </p>
            </div>
        </div>

    </div>
</div>