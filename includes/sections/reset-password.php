<?php
$token = $_GET['token'] ?? '';
?>
<div class="auth-wrapper">
    <div class="auth-card">
        
        <?php if(empty($token)): ?>
            <div class="auth-header">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f;">broken_image</span>
                <h1 style="color: #d32f2f;"><?php echo $i18n->t('auth.reset.invalid_title'); ?></h1>
                <p><?php echo $i18n->t('auth.reset.invalid_desc'); ?></p>
            </div>
            <a href="<?php echo $basePath; ?>login" class="btn-primary"><?php echo $i18n->t('auth.reset.btn_login'); ?></a>

        <?php else: ?>
            <div class="auth-header">
                <h1><?php echo $i18n->t('auth.reset.title'); ?></h1>
                <p><?php echo $i18n->t('auth.reset.desc'); ?></p>
            </div>
            
            <input type="hidden" id="reset_token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="password" id="new_password" required placeholder=" ">
                    <label for="new_password"><?php echo $i18n->t('auth.reset.field_new'); ?></label>
                    <button type="button" class="btn-input-action" data-action="toggle-password" tabindex="-1">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
                
                <div class="form-group">
                    <input type="password" id="confirm_password" required placeholder=" ">
                    <label for="confirm_password"><?php echo $i18n->t('auth.reset.field_confirm'); ?></label>
                </div>
            </div>

            <button type="button" id="btn-submit-new-password" class="btn-primary"><?php echo $i18n->t('auth.reset.btn_change'); ?></button>

        <?php endif; ?>
    </div>
</div>