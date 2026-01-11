<?php
// includes/sections/settings/profile.php

// Usamos el NUEVO servicio
require_once __DIR__ . '/../../../api/services/SettingsServices.php';
$settingsService = new SettingsServices();

if (!isset($_SESSION['user_id'])) {
    echo "Sesión expirada.";
    exit;
}

// Obtener datos reales
$userData = $settingsService->getUserProfile($_SESSION['user_id']);

$currentAvatar = $userData['profile_picture_url'] ?? 'public/assets/img/avatars/default.png';
$currentUsername = $userData['username'];
$currentEmail = $userData['email'];
$userRole = $userData['account_role'];

// Determinar botones (Default vs Custom)
$isDefaultAvatar = strpos($currentAvatar, 'ui-avatars.com') !== false;
$classDefault = $isDefaultAvatar ? 'active' : 'disabled'; 
$classCustom  = $isDefaultAvatar ? 'disabled' : 'active'; 
?>

<div class="section-content active" data-section="settings/your-profile">
    <input type="hidden" id="profile-csrf" value="<?php echo $_SESSION['csrf_token']; ?>">

    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo __('settings.profile_title'); ?></h1>
            <p class="component-page-description"><?php echo __('settings.profile_desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item" data-component="profile-picture-section">
                 <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="<?php echo htmlspecialchars($userRole); ?>">
                        <img src="<?php echo htmlspecialchars($currentAvatar); ?>" class="component-card__avatar-image" id="preview-avatar">
                        <div class="component-card__avatar-overlay" id="btn-trigger-upload">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo __('settings.profile_pic_title'); ?></h2>
                        <p class="component-card__description"><?php echo __('settings.profile_pic_desc'); ?></p>
                    </div>
                </div>

                <input type="file" id="upload-avatar" accept="image/png, image/jpeg, image/webp, image/gif" hidden>

                <div class="component-card__actions actions-right">
                    <div class="component-action-group <?php echo $classDefault; ?>" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button primary" id="btn-upload-init">
                            <?php echo __('settings.profile_btn_upload'); ?>
                        </button>
                    </div>
                    <div class="component-action-group disabled" data-state="profile-picture-actions-preview">
                        <button type="button" class="component-button" data-action="profile-picture-cancel"><?php echo __('settings.profile_btn_cancel'); ?></button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save"><?php echo __('settings.profile_btn_save'); ?></button>
                    </div>
                    <div class="component-action-group <?php echo $classCustom; ?>" data-state="profile-picture-actions-custom">
                        <button type="button" class="component-button" data-action="profile-picture-delete" style="color: #d32f2f; border-color: #d32f2f30;">
                            <?php echo __('settings.profile_btn_delete'); ?>
                        </button>
                        <button type="button" class="component-button primary" data-action="profile-picture-change">
                            <?php echo __('settings.profile_btn_change'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo __('settings.profile_username_title'); ?></h2>
                        <div class="active" data-state="username-view-state">
                            <span class="text-display-value" id="display-username"><?php echo htmlspecialchars($currentUsername); ?></span>
                        </div>
                        <div class="disabled w-100 input-group-responsive" data-state="username-edit-state">
                            <div class="component-input-wrapper flex-1">
                                <input type="text" class="component-text-input" id="input-username" value="<?php echo htmlspecialchars($currentUsername); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="username-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="username"><?php echo __('settings.profile_btn_cancel'); ?></button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="username"><?php echo __('settings.profile_btn_save'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="username-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="username"><?php echo __('settings.profile_btn_edit'); ?></button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo __('settings.profile_email_title'); ?></h2>
                        <div class="active" data-state="email-view-state">
                            <span class="text-display-value" id="display-email"><?php echo htmlspecialchars($currentEmail); ?></span>
                        </div>
                        <div class="disabled w-100 input-group-responsive" data-state="email-edit-state">
                            <div class="component-input-wrapper flex-1">
                                <input type="email" class="component-text-input" id="input-email" value="<?php echo htmlspecialchars($currentEmail); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="email-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="email"><?php echo __('settings.profile_btn_cancel'); ?></button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="email"><?php echo __('settings.profile_btn_save'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="email-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="email"><?php echo __('settings.profile_btn_edit'); ?></button>
                </div>
            </div>

        </div>
    </div>
</div>