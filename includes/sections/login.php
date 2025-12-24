<div class="component-layout-centered">
    <div class="component-card component-card--compact">
        
        <div class="component-header-centered">
            <h1 id="auth-title"><?php echo $i18n->t('auth.login.title'); ?></h1>
            <p id="auth-subtitle"><?php echo $i18n->t('auth.login.subtitle'); ?></p>
        </div>
        
        <div id="login-stage-1" class="component-stage-form">
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

            <a href="<?php echo $basePath; ?>recover-password" data-nav="recover-password" class="component-link-simple"><?php echo $i18n->t('auth.forgot_password'); ?></a>

            <button type="button" id="btn-login" class="component-button component-button--large primary"><?php echo $i18n->t('auth.btn.login'); ?></button>
        </div>

        <div id="login-stage-2" class="component-stage-form disabled">
            <div class="component-input-wrapper component-input-wrapper--floating" style="margin-bottom: 8px;">
                <input type="text" id="2fa-code" class="component-text-input" placeholder=" " maxlength="8" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
                <label for="2fa-code" class="component-label-floating" style="left: 50%; transform: translateX(-50%) translateY(-50%);"><?php echo $i18n->t('auth.2fa.field_code'); ?></label>
            </div>
            
            <p style="font-size: 13px; color: #666; margin: 12px 0; text-align: center;">
                <?php echo $i18n->t('auth.2fa.desc'); ?>
            </p>

            <button type="button" id="btn-verify-2fa" class="component-button component-button--large primary"><?php echo $i18n->t('auth.btn.verify'); ?></button>
            
            <div style="margin-top: 16px; text-align: center;">
                 <a href="#" onclick="location.reload()" style="font-size: 13px; color: #666; text-decoration: none;"><?php echo $i18n->t('auth.2fa.back'); ?></a>
            </div>
        </div>

        <div class="component-text-footer">
            <p><?php echo $i18n->t('auth.no_account'); ?> <a href="<?php echo $basePath; ?>register"><?php echo $i18n->t('auth.register_link'); ?></a></p>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="component-message component-message--error">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>