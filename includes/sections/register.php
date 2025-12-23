<?php
// includes/sections/register.php
$basePath = isset($basePath) ? $basePath : '/ProjectAurora/';
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

<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if ($invalidAccess): ?>
            <div class="crash-header">
                <span class="material-symbols-rounded crash-icon">token</span>
                <h1 class="crash-title">Error 409</h1>
            </div>
            <div class="crash-code-box">
                <span class="crash-text-error"><?php echo $errorMessage; ?></span>
            </div>

        <?php elseif ($isStep3): ?>
            <div class="auth-header">
                <h1><?php echo $i18n->t('auth.register.title_3'); ?></h1>
                <p><?php echo $i18n->t('auth.register.subtitle_3'); ?> <strong><?php echo htmlspecialchars($_SESSION['pending_verification_email'] ?? ''); ?></strong>.</p>
            </div>

            <input type="hidden" id="verify-action" name="action" value="verify_code">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="code" id="verification_code" class="input-code-verify" required placeholder=" " maxlength="6" style="letter-spacing: 4px; text-align: center; font-size: 20px;">
                    <label for="verification_code" class="label-centered"><?php echo $i18n->t('auth.register.field.code'); ?></label>
                </div>
            </div>

            <button type="button" id="btn-finish" class="btn-primary"><?php echo $i18n->t('auth.register.btn.finish'); ?></button>

            <div class="auth-resend-wrapper" style="margin-top: 16px;">
                <a href="#" id="btn-resend-code" class="link-disabled" style="pointer-events: none; color: rgb(153, 153, 153); text-decoration: none; font-size: 14px;">
                    <?php echo $i18n->t('auth.register.resend_code'); ?> <span id="register-timer">(60)</span>
                </a>
            </div>

        <?php elseif ($isStep2): ?>
            <div class="auth-header">
                <h1><?php echo $i18n->t('auth.register.title_2'); ?></h1>
                <p><?php echo $i18n->t('auth.register.subtitle_2'); ?></p>
            </div>

            <input type="hidden" id="register-action-2" name="action" value="register_step_2">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" name="username" id="username" required placeholder=" ">
                    <label for="username"><?php echo $i18n->t('auth.field.username'); ?></label>
                    <button type="button" class="btn-input-action" data-action="generate-username" tabindex="-1">
                        <span class="material-symbols-rounded">autorenew</span>
                    </button>
                </div>
            </div>

            <div style="display: flex; gap: 8px;">
                <a href="<?php echo $basePath; ?>register" data-nav="register" class="btn-primary mt-16 btn-back" style="background: #eee; color: #333; width: 40%; display:flex; justify-content:center; align-items:center; text-decoration:none;">
                    <?php echo $i18n->t('auth.btn.back'); ?>
                </a>
                <button type="button" id="btn-next-2" class="btn-primary mt-16" style="width: 60%;">
                    <?php echo $i18n->t('auth.btn.continue'); ?>
                </button>
            </div>

        <?php else: ?>
            <div class="auth-header">
                <h1><?php echo $i18n->t('auth.register.title_1'); ?></h1>
                <p><?php echo $i18n->t('auth.register.subtitle_1'); ?></p>
            </div>

            <input type="hidden" id="register-action-1" name="action" value="register_step_1">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="email" name="email" id="email" required placeholder=" ">
                    <label for="email"><?php echo $i18n->t('auth.field.email'); ?></label>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="password" required placeholder=" ">
                    <label for="password"><?php echo $i18n->t('auth.field.password'); ?></label>
                    <button type="button" class="btn-input-action" data-action="toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
            </div>

            <button type="button" id="btn-next-1" class="btn-primary"><?php echo $i18n->t('auth.btn.continue'); ?></button>

            <div class="auth-footer">
                <p>
                    <?php echo $i18n->t('auth.has_account'); ?> 
                    <a href="<?php echo $basePath; ?>login" data-nav="login" class="link-primary"><?php echo $i18n->t('auth.login_link'); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error']) && !$invalidAccess): ?>
            <div class="alert error mt-16 mb-0">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

    </div>
</div>