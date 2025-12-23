<div class="auth-wrapper">
    <div class="auth-card">
        
        <div class="auth-header">
            <h1 id="auth-title"><?php echo $i18n->t('auth.login.title'); ?></h1>
            <p id="auth-subtitle"><?php echo $i18n->t('auth.login.subtitle'); ?></p>
        </div>
        
        <div id="login-stage-1">
            <input type="hidden" id="login-action" name="action" value="login">
            
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

            <div class="forgot-password">
                <a href="<?php echo $basePath; ?>recover-password" data-nav="recover-password"><?php echo $i18n->t('auth.forgot_password'); ?></a>
            </div>

            <button type="button" id="btn-login" class="btn-primary"><?php echo $i18n->t('auth.btn.login'); ?></button>
        </div>

        <div id="login-stage-2" class="disabled" style="display:none;">
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" id="2fa-code" placeholder=" " maxlength="8" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
                    <label for="2fa-code" class="label-centered"><?php echo $i18n->t('auth.2fa.field_code'); ?></label>
                </div>
            </div>
            
            <p style="font-size: 13px; color: #666; margin: 12px 0;">
                <?php echo $i18n->t('auth.2fa.desc'); ?>
            </p>

            <button type="button" id="btn-verify-2fa" class="btn-primary"><?php echo $i18n->t('auth.btn.verify'); ?></button>
            
            <div style="margin-top: 16px;">
                 <a href="#" onclick="location.reload()" style="font-size: 13px; color: #666;"><?php echo $i18n->t('auth.2fa.back'); ?></a>
            </div>
        </div>

        <div class="auth-footer">
            <p><?php echo $i18n->t('auth.no_account'); ?> <a href="<?php echo $basePath; ?>register"><?php echo $i18n->t('auth.register_link'); ?></a></p>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error mt-16 mb-0">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>