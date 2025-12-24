<?php
// includes/sections/register.php

// NOTA: $basePath ya viene definido desde loader.php o index.php, no hace falta redefinirlo.

$currentRoute = isset($section) ? $section : ($currentSection ?? 'register');
$isStep2 = $currentRoute === 'register/aditional-data';
$isStep3 = $currentRoute === 'register/verification-account';
$hasDataForStep2 = isset($_SESSION['temp_register']) && !empty($_SESSION['temp_register']);
$hasDataForStep3 = isset($_SESSION['pending_verification_email']) && !empty($_SESSION['pending_verification_email']);
$invalidAccess = false;
$errorMessage = "";

if ($isStep2 && !$hasDataForStep2) {
    http_response_code(409);
    $invalidAccess = true;
    $errorMessage = "Required session data missing.";
} elseif ($isStep3 && !$hasDataForStep3) {
    http_response_code(409);
    $invalidAccess = true;
    $errorMessage = "Verification context not found.";
}
?>

<div class="component-layout-centered">
    <div class="component-card component-card--compact">
        
        <?php if ($invalidAccess): ?>
            <div class="crash-header">
                <span class="material-symbols-rounded crash-icon">token</span>
                <h1 class="crash-title">Error 409</h1>
            </div>
            <div class="crash-code-box">
                <span class="crash-text-error"><?php echo $errorMessage; ?></span>
            </div>

        <?php elseif ($isStep3): ?>
            <div class="component-header-centered">
                <h1><?php echo $i18n->t('auth.register.title_3'); ?></h1>
                <p><?php echo $i18n->t('auth.register.subtitle_3'); ?> <strong><?php echo htmlspecialchars($_SESSION['pending_verification_email'] ?? ''); ?></strong>.</p>
            </div>

            <input type="hidden" id="verify-action" name="action" value="verify_code">
            
            <div class="component-stage-form">
                <div class="component-form-group">
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="text" name="code" id="verification_code" class="component-text-input input-code-verify" required placeholder=" " maxlength="6" style="letter-spacing: 4px; text-align: center; font-size: 20px;">
                        <label for="verification_code" class="component-label-floating" style="left: 50%; transform: translateX(-50%) translateY(-50%);"><?php echo $i18n->t('auth.register.field.code'); ?></label>
                    </div>
                </div>
            </div>

            <button type="button" id="btn-finish" class="component-button component-button--large primary"><?php echo $i18n->t('auth.register.btn.finish'); ?></button>

            <div class="component-text-footer">
                <a href="#" id="btn-resend-code" class="link-disabled" style="pointer-events: none; color: rgb(153, 153, 153); text-decoration: none; font-size: 14px;">
                    <?php echo $i18n->t('auth.register.resend_code'); ?> <span id="register-timer">(60)</span>
                </a>
            </div>

        <?php elseif ($isStep2): ?>
            <div class="component-header-centered">
                <h1><?php echo $i18n->t('auth.register.title_2'); ?></h1>
                <p><?php echo $i18n->t('auth.register.subtitle_2'); ?></p>
            </div>

            <input type="hidden" id="register-action-2" name="action" value="register_step_2">
            
            <div class="component-stage-form">
                <div class="component-form-group">
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="text" name="username" id="username" class="component-text-input has-action" required placeholder=" ">
                        <label for="username" class="component-label-floating"><?php echo $i18n->t('auth.field.username'); ?></label>
                        <button type="button" class="component-input-action" data-action="generate-username" tabindex="-1">
                            <span class="material-symbols-rounded">autorenew</span>
                        </button>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 8px;">
                <a href="<?php echo $basePath; ?>register" data-nav="register" class="component-button component-button--large btn-back" style="background: #eee; color: #333; width: 40%; display:flex; justify-content:center; align-items:center; text-decoration:none;">
                    <?php echo $i18n->t('auth.btn.back'); ?>
                </a>
                <button type="button" id="btn-next-2" class="component-button component-button--large primary" style="width: 60%;">
                    <?php echo $i18n->t('auth.btn.continue'); ?>
                </button>
            </div>

        <?php else: ?>
            <div class="component-header-centered">
                <h1><?php echo $i18n->t('auth.register.title_1'); ?></h1>
                <p><?php echo $i18n->t('auth.register.subtitle_1'); ?></p>
            </div>

            <input type="hidden" id="register-action-1" name="action" value="register_step_1">
            
            <div class="component-stage-form">
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
            </div>

            <button type="button" id="btn-next-1" class="component-button component-button--large primary"><?php echo $i18n->t('auth.btn.continue'); ?></button>

            <div class="component-text-footer">
                <p>
                    <?php echo $i18n->t('auth.has_account'); ?> 
                    <a href="<?php echo $basePath; ?>login" data-nav="login"><?php echo $i18n->t('auth.login_link'); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error']) && !$invalidAccess): ?>
            <div class="component-message component-message--error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

    </div>
</div>