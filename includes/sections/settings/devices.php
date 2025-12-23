<div class="section-content active" data-section="settings/devices">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->t('settings.devices.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->t('settings.devices.desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.devices.revoke_all_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.devices.revoke_all_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" id="btn-revoke-all" style="color: #d32f2f; border-color: rgba(211, 47, 47, 0.3);">
                        <?php echo $i18n->t('settings.devices.btn_revoke_all'); ?>
                    </button>
                </div>
            </div>

            <hr class="component-divider">

            <div id="devices-list-container">
                <div style="padding: 24px; text-align: center;">
                    <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                    <p style="margin-top: 10px; color: #666;"><?php echo $i18n->t('settings.devices.loading'); ?></p>
                </div>
            </div>

        </div>

        <div class="component-card">
            <button class="component-button" data-nav="settings/login-security">
                <span class="material-symbols-rounded">arrow_back</span>
                <?php echo $i18n->t('settings.devices.btn_back'); ?>
            </button>
        </div>

    </div>
</div>