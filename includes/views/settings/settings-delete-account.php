<?php
// includes/views/settings-delete-account.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="view-content">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.delete_account.title') ?></h1>
            <p class="component-page-description"><?= t('settings.delete_account.warning') ?></p>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <label>
                        <input type="checkbox" id="confirmDeleteCheckbox">
                        <span><?= t('settings.delete_account.checkbox') ?></span>
                    </label>
                </div>
                
                <div id="passwordConfirmationArea" class="disabled" data-state="delete-account-password">
                    <hr class="component-divider">
                    
                    <div class="component-form-group">
                        <label for="deleteAccountPassword" class="component-card__title">
                            <?= t('settings.delete_account.password_label') ?>
                        </label>
                        
                        <div class="component-input-wrapper">
                            <input type="password" id="deleteAccountPassword" class="component-text-input component-text-input--simple" placeholder="Tu contraseña actual" autocomplete="off">
                        </div>
                    </div>

                    <div class="component-card__actions">
                        <button type="button" class="component-button" data-action="delete-account-submit">
                            <?= t('settings.delete_account.btn_delete') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>