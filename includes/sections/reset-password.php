<?php
$token = $_GET['token'] ?? '';
?>
<div class="component-layout-centered">
    <div class="component-card component-card--compact">
        
        <?php if(empty($token)): ?>
            <div class="component-header-centered">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f;">broken_image</span>
                <h1 style="color: #d32f2f;"><?php echo $i18n->t('auth.reset.invalid_title'); ?></h1>
                <p><?php echo $i18n->t('auth.reset.invalid_desc'); ?></p>
            </div>
            <a href="<?php echo $basePath; ?>login" class="component-button component-button--large primary mt-16" style="text-decoration: none;"><?php echo $i18n->t('auth.reset.btn_login'); ?></a>

        <?php else: ?>
            <div class="component-header-centered">
                <h1><?php echo $i18n->t('auth.reset.title'); ?></h1>
                <p><?php echo $i18n->t('auth.reset.desc'); ?></p>
            </div>
            
            <input type="hidden" id="reset_token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="component-stage-form">
                <div class="component-form-group">
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="password" id="new_password" class="component-text-input has-action" required placeholder=" ">
                        <label for="new_password" class="component-label-floating"><?php echo $i18n->t('auth.reset.field_new'); ?></label>
                        <button type="button" class="component-input-action" data-action="toggle-password" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                    
                    <div class="component-input-wrapper component-input-wrapper--floating">
                        <input type="password" id="confirm_password" class="component-text-input" required placeholder=" ">
                        <label for="confirm_password" class="component-label-floating"><?php echo $i18n->t('auth.reset.field_confirm'); ?></label>
                    </div>
                </div>
            </div>

            <button type="button" id="btn-submit-new-password" class="component-button component-button--large primary"><?php echo $i18n->t('auth.reset.btn_change'); ?></button>

        <?php endif; ?>
    </div>
</div>