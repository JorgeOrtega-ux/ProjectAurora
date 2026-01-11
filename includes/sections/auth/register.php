<?php
// includes/sections/auth/register.php

$step = 1;
if ($currentSection === 'register/additional-data') $step = 2;
if ($currentSection === 'register/verification-account') $step = 3;

if (($step === 2 || $step === 3) && !isset($_SESSION['reg_temp_email'])) {
    echo "<script>window.location.href = '" . $basePath . "register';</script>";
    exit;
}
?>

<div class="component-layout-centered">
    <div class="component-card--compact">
        
        <div class="component-header-centered">
            <?php if ($step === 1): ?>
                <h1><?php echo __('auth.register.title'); ?></h1>
                <p><?php echo __('auth.register.subtitle'); ?></p>
            <?php elseif ($step === 2): ?>
                <h1><?php echo __('auth.register.step2_title'); ?></h1>
                <p><?php echo __('auth.register.step2_subtitle'); ?></p>
            <?php elseif ($step === 3): ?>
                <h1><?php echo __('auth.register.step3_title'); ?></h1>
                <p><?php echo __('auth.register.step3_subtitle'); ?></p>
            <?php endif; ?>

            <div id="auth-error-container" style="color: #d32f2f; font-size: 14px; background: #ffebee; padding: 12px; border-radius: 8px; margin-top: 8px; display: none; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded" style="font-size: 18px;">error</span>
                <span id="auth-error-text"></span>
            </div>
        </div>
        
        <div class="component-stage-form">
            
            <?php if ($step === 1): ?>
                <div class="component-form-group">
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="email" id="reg-email" class="component-text-input" placeholder=" " 
                               value="<?php echo htmlspecialchars($_SESSION['reg_temp_email'] ?? ''); ?>">
                        <label for="reg-email" class="component-label-floating"><?php echo __('auth.field.email'); ?></label>
                    </div>

                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="password" id="reg-password" class="component-text-input" placeholder=" ">
                        <label for="reg-password" class="component-label-floating"><?php echo __('auth.field.password'); ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                </div>
                <button type="button" id="btn-register-step1" class="component-button component-button--large primary">
                    <?php echo __('auth.btn.next'); ?> <span class="material-symbols-rounded" style="font-size: 18px; margin-left: 4px;">arrow_forward</span>
                </button>
            <?php endif; ?>

            <?php if ($step === 2): ?>
                <div class="component-form-group">
                     <p style="font-size: 13px; color: #666; text-align: center;">
                        Creando cuenta para: <strong><?php echo htmlspecialchars($_SESSION['reg_temp_email'] ?? ''); ?></strong>
                    </p>
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="text" id="reg-username" class="component-text-input" placeholder=" " autofocus>
                        <label for="reg-username" class="component-label-floating"><?php echo __('auth.field.username'); ?></label>
                    </div>
                </div>
                <button type="button" id="btn-register-step2" class="component-button component-button--large primary">
                    <?php echo __('auth.btn.next'); ?> <span class="material-symbols-rounded" style="font-size: 18px; margin-left: 4px;">arrow_forward</span>
                </button>
            <?php endif; ?>

            <?php if ($step === 3): ?>
                <div class="component-form-group">
                    <div style="text-align: center; margin-bottom: 12px;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: #000;">mark_email_unread</span>
                    </div>
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="text" id="reg-code" class="component-text-input" placeholder=" " maxlength="6" style="letter-spacing: 4px; font-weight: 700; text-align: center;" autocomplete="off">
                        <label for="reg-code" class="component-label-floating" style="left: 50%; transform: translateX(-50%) translateY(-50%);"><?php echo __('auth.field.code'); ?></label>
                    </div>
                    <p style="font-size: 12px; color: #999; text-align: center;">Revisa tu bandeja de entrada</p>
                </div>
                <button type="button" id="btn-verify" class="component-button component-button--large primary">
                    <?php echo __('auth.btn.verify'); ?>
                </button>
            <?php endif; ?>

        </div>

        <div class="component-text-footer">
            <p>
                <?php echo __('auth.has_account'); ?> 
                <a href="<?php echo $basePath; ?>login" data-nav="login"><?php echo __('auth.login_link'); ?></a>
            </p>
        </div>
    </div>
</div>