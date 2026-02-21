<?php
$hasToken = isset($_GET['token']) && !empty($_GET['token']);
$initialStyle = !$hasToken ? 'display: none;' : '';
?>
<div class="view-content">
    <div class="component-layout-centered">
        <div class="component-card component-card--compact">

            <div class="component-header-centered" style="<?php echo $initialStyle; ?>">
                <h1 id="auth-title"><?= t('reset.title') ?></h1>
                <p id="auth-subtitle"><?= t('reset.subtitle') ?></p>
            </div>

            <div id="reset-fatal-error" class="component-fatal-error-container">
                <h2 class="component-fatal-error-title">Oops, an error occurred!</h2>
                <div id="reset-fatal-error-code" class="component-json-error-box"></div>
            </div>

            <div id="form-reset-password" class="component-stage-form" style="<?php echo $initialStyle; ?>">
                <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="component-form-group">
                    <div class="component-input-wrapper">
                        <input type="password" id="reset-password-1" class="component-text-input has-action" required placeholder=" ">
                        <label for="reset-password-1" class="component-label-floating"><?= t('reset.new_pass') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>

                    <div class="component-input-wrapper">
                        <input type="password" id="reset-password-2" class="component-text-input has-action" required placeholder=" ">
                        <label for="reset-password-2" class="component-label-floating"><?= t('reset.confirm_pass') ?></label>
                        <button type="button" class="component-input-action" tabindex="-1">
                            <span class="material-symbols-rounded">visibility</span>
                        </button>
                    </div>
                </div>

                <button type="button" id="btn-reset-password" class="component-button component-button--large primary" style="margin-top: 16px;">
                    <?= t('reset.btn') ?>
                </button>

                <div id="reset-error" class="component-message-error"></div>
                <div id="reset-success" style="display: none; color: var(--color-success); font-weight: 500; text-align: center; margin-top: 16px; padding: 12px; background: var(--color-success-bg); border: 1px solid var(--color-success); border-radius: 8px;">
                    <?= t('reset.success') ?>
                </div>
            </div>

        </div>
    </div>
</div>