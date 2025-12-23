<div class="auth-wrapper">
    <div class="auth-card">
        
        <div class="auth-header">
            <h1><?php echo $i18n->trans('auth.recover.title'); ?></h1>
            <p><?php echo $i18n->trans('auth.recover.desc'); ?></p>
        </div>
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="email" name="email_recovery" id="email_recovery" required placeholder=" ">
                <label for="email_recovery"><?php echo $i18n->trans('auth.field.email'); ?></label>
            </div>
        </div>

        <button type="button" id="btn-request-reset" class="btn-primary"><?php echo $i18n->trans('auth.recover.btn_send'); ?></button>
        <div id="recovery-message-area"></div>

        <div class="auth-footer">
            <a href="<?php echo $basePath; ?>login" data-nav="login" class="link-primary"><?php echo $i18n->trans('auth.recover.back_login'); ?></a>
        </div>
    </div>
</div>