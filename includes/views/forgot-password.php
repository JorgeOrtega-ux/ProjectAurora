<div class="view-content">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">

            <div class="component-header-centered">
                <h1 id="auth-title"><?= t('forgot.title') ?></h1>
                <p id="auth-subtitle"><?= t('forgot.subtitle') ?></p>
            </div>

            <div id="form-forgot-password" class="component-stage-form">
                <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="email" id="forgot-email" class="component-text-input" required placeholder=" ">
                        <label for="forgot-email" class="component-label-floating"><?= t('forgot.email') ?></label>
                    </div>
                </div>

                <div id="simulated-link-container" style="display: none; text-align: center; margin: 16px 0; color: #666; font-size: 14px;">
                    <p><?= t('forgot.simulated_msg') ?></p>
                    <a id="simulated-link-display" href="#" style="word-break: break-all; font-weight: bold; color: #000; margin-top: 8px; display: block;"></a>
                </div>

                <button type="button" id="btn-forgot-password" class="component-button component-button--large primary" style="margin-top: 16px;">
                    <?= t('forgot.btn') ?>
                </button>

                <div id="forgot-error" class="component-message-error"></div>

                <div class="component-text-footer" style="margin-top: 16px;">
                    <p><a href="/ProjectAurora/login" data-nav="/ProjectAurora/login"><?= t('forgot.back') ?></a></p>
                </div>
            </div>

        </div>
    </div>
</div>