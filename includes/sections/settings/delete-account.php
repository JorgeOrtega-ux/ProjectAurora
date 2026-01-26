<?php
// includes/sections/settings/delete-account.php
?>
<div class="section-content active" data-section="settings/delete-account">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" style="color: #d32f2f;"><?php echo $i18n->t('settings.delete.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->t('settings.delete.desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered" style="color: #d32f2f; background: #ffebee; border-color: #ffcdd2;">
                        <span class="material-symbols-rounded">warning</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.delete.warn_title'); ?></h2>
                        <p class="component-card__description">
                            <?php echo $i18n->t('settings.delete.warn_desc'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.delete.confirm_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.delete.confirm_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="check-confirm-delete">
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked disabled" id="delete-confirmation-area">
                <div class="component-card__content w-100">
                    <div class="component-card__text w-100">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.delete.pass_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.delete.pass_desc'); ?></p>
                        
                        <div class="component-input-wrapper mt-16">
                            <input type="password" class="component-text-input" id="delete-password-input" placeholder="<?php echo $i18n->t('settings.delete.pass_placeholder'); ?>">
                        </div>
                    </div>
                </div>

                <div class="component-card__actions actions-right w-100">
                    <button type="button" class="component-button" id="btn-delete-final" style="background-color: #d32f2f; color: white; border: none; width: 100%;">
                        <?php echo $i18n->t('settings.delete.btn_final'); ?>
                    </button>
                </div>
            </div>

        </div>

    </div>
</div>