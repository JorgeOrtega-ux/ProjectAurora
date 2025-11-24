<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$initialStep = 1;
if (isset($CURRENT_SECTION)) {
    if ($CURRENT_SECTION === 'register/additional-data') $initialStep = 2;
    if ($CURRENT_SECTION === 'register/verification-account') $initialStep = 3;
} else if (isset($_GET['step'])) {
    $initialStep = (int)$_GET['step'];
}
?>

<div class="section-content active" data-section="register">
    <div class="section-center-wrapper">
        <div class="form-container">
            
            <div data-step="register-1" class="auth-step-container <?php echo ($initialStep === 1) ? 'active' : ''; ?>">
                <h1 data-i18n="auth.register.title"><?php echo trans('auth.register.title'); ?></h1>
                <p data-i18n="auth.register.subtitle_1"><?php echo trans('auth.register.subtitle_1'); ?></p>
                
                <div class="floating-label-group">
                    <input 
                        type="email" 
                        data-input="reg-email" 
                        class="floating-input" 
                        required 
                        placeholder=" " 
                        value="<?php echo $_SESSION['temp_register']['email'] ?? ''; ?>"
                    >
                    <label class="floating-label" data-i18n="auth.login.email_label"><?php echo trans('auth.login.email_label'); ?></label>
                </div>

                <div class="floating-label-group">
                    <input 
                        type="password" 
                        data-input="reg-password" 
                        class="floating-input" 
                        required 
                        placeholder=" "
                        minlength="8"
                    >
                    <label class="floating-label" data-i18n="auth.register.password_hint"><?php echo trans('auth.register.password_hint'); ?></label>
                    <button type="button" class="floating-input-btn"><span class="material-symbols-rounded">visibility</span></button>
                </div>

                <button class="form-button" data-action="register-step1" data-i18n="auth.register.next"><?php echo trans('auth.register.next'); ?></button>
                
                <div class="form-footer-link">
                    <span data-i18n="auth.register.have_account"><?php echo trans('auth.register.have_account'); ?></span> 
                    <a href="#" data-nav="login" data-i18n="auth.register.login_link"><?php echo trans('auth.register.login_link'); ?></a>
                </div>
            </div>

            <div data-step="register-2" class="auth-step-container <?php echo ($initialStep === 2) ? 'active' : ''; ?>">
                <div class="auth-back-link">
                    </div>
                <h1 data-i18n="auth.register.subtitle_2"><?php echo trans('auth.register.subtitle_2'); ?></h1>
                <p data-i18n="auth.register.username_hint"><?php echo trans('auth.register.username_hint'); ?></p>
                
                <div class="floating-label-group">
                    <input 
                        type="text" 
                        data-input="reg-username" 
                        class="floating-input" 
                        required 
                        placeholder=" " 
                        minlength="8"
                        maxlength="32"
                        pattern="[a-zA-Z0-9_]+"
                        style="padding-right: 50px;" 
                    >
                    <label class="floating-label" data-i18n="auth.register.username_label"><?php echo trans('auth.register.username_label'); ?></label>
                    
                    <button type="button" class="floating-input-btn username-magic-btn" title="Generar usuario aleatorio">
                        <span class="material-symbols-rounded">auto_fix_high</span>
                    </button>
                </div>

                <button class="form-button" data-action="register-step2" data-i18n="global.continue"><?php echo trans('global.continue'); ?></button>
            </div>

            <div data-step="register-3" class="auth-step-container <?php echo ($initialStep === 3) ? 'active' : ''; ?>">
                <h1 data-i18n="auth.register.subtitle_3"><?php echo trans('auth.register.subtitle_3'); ?></h1>
                
                <p style="font-size:14px;">
                    <span data-i18n="auth.register.code_sent"><?php echo trans('auth.register.code_sent'); ?></span> 
                    <strong data-display="email-verify"><?php echo $_SESSION['temp_register']['email'] ?? 'tu correo'; ?></strong>.
                </p>
                
                <div class="floating-label-group">
                    <input 
                        type="text" 
                        data-input="reg-code" 
                        class="floating-input" 
                        required 
                        placeholder=" " 
                        maxlength="14" 
                        style="letter-spacing: 2px; text-transform: uppercase;"
                    >
                    <label class="floating-label" data-i18n="auth.register.code_label"><?php echo trans('auth.register.code_label'); ?></label>
                </div>

                <button class="form-button" data-action="register-step3" data-i18n="auth.register.verify_btn"><?php echo trans('auth.register.verify_btn'); ?></button>
                
                <div class="form-footer-link">
                    <a href="#" data-action="resend-register" class="disabled-link" data-i18n="auth.register.resend_code"><?php echo trans('auth.register.resend_code'); ?></a>
                </div>
            </div>

        </div>
    </div>
</div>