<?php
// includes/views/settings-delete-account.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="view-content">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="component-header-card" style="border-color: #fca5a5; background-color: #fef2f2;">
            <h1 class="component-page-title" style="color: #dc2626;"><?= t('settings.delete_account.title') ?></h1>
            <p class="component-page-description" style="color: #991b1b;"><?= t('settings.delete_account.warning') ?></p>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content w-100">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; width: 100%;">
                        <input type="checkbox" id="confirmDeleteCheckbox" style="width: 18px; height: 18px; cursor: pointer; accent-color: #dc2626; flex-shrink: 0;">
                        <span style="color: var(--text-primary); font-size: 14px; font-weight: 500;"><?= t('settings.delete_account.checkbox') ?></span>
                    </label>
                </div>
                
                <div id="passwordConfirmationArea" class="w-100 disabled" data-state="delete-account-password" style="flex-direction: column; gap: 16px; margin-top: 8px;">
                    <hr class="component-divider">
                    
                    <div class="component-form-group">
                        <label for="deleteAccountPassword" class="component-card__title" style="margin-bottom: 4px; color: #dc2626;">
                            <?= t('settings.delete_account.password_label') ?>
                        </label>
                        
                        <div class="component-input-wrapper" style="max-width: 350px; border-color: #fca5a5;">
                            <input type="password" id="deleteAccountPassword" class="component-text-input component-text-input--simple" placeholder="Tu contraseña actual" autocomplete="off">
                        </div>
                    </div>

                    <div class="component-card__actions mt-16">
                        <button type="button" class="component-button" data-action="delete-account-submit" style="background-color: #dc2626; color: white; border-color: #dc2626;">
                            <?= t('settings.delete_account.btn_delete') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>