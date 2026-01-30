<div class="component-layout-centered">
    <div class="component-card component-card--compact">
        
        <div class="component-header-centered">
            <h1><?php echo $i18n->t('auth.recover.title'); ?></h1>
            <p><?php echo $i18n->t('auth.recover.desc'); ?></p>
        </div>
        
        <div class="component-stage-form">
            <div class="component-form-group">
                <div class="component-input-wrapper component-input-wrapper--floating">
                    <input type="email" name="email_recovery" id="email_recovery" class="component-text-input" required placeholder=" ">
                    <label for="email_recovery" class="component-label-floating"><?php echo $i18n->t('auth.field.email'); ?></label>
                </div>
            </div>
        </div>

        <button type="button" id="btn-request-reset" class="component-button component-button--large primary"><?php echo $i18n->t('auth.recover.btn_send'); ?></button>
        
        <div class="component-text-footer">
            <a href="<?php echo $basePath; ?>login" id="link-back-login" data-nav="login"><?php echo $i18n->t('auth.recover.back_login'); ?></a>

            <span id="link-resend-recovery" class="component-link-action link-disabled" role="button" tabindex="0" style="display: none; pointer-events: none; color: rgb(153, 153, 153); font-size: 14px;">
                Reenviar correo de restablecimiento <span id="recovery-timer">(60)</span>
            </span>
        </div>
    </div>
</div>