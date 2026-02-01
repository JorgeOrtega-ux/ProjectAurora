<?php
// includes/sections/admin/user-details.php

require_once __DIR__ . '/../../libs/Utils.php';
require_once __DIR__ . '/../../../api/services/AdminService.php';

// Validar ID
$targetId = $_GET['id'] ?? null;

if (!$targetId) {
    echo "<script>window.location.href = '?page=admin/users';</script>";
    exit;
}

// Instanciar servicio
$currentAdminId = $_SESSION['user_id'] ?? 0; 
$adminService = new AdminService($pdo, $i18n, $currentAdminId);

// Obtener datos
$response = $adminService->getUserDetails($targetId);

if (!$response['success']) {
    echo "<script>window.location.href = '?page=admin/users&error=user_not_found';</script>";
    exit;
}

$user = $response['user'];
$prefs = $user['preferences'];

// [NUEVO] Lógica 2FA
$is2FAActive = isset($user['two_factor_enabled']) && $user['two_factor_enabled'] === 1;
?>

<div class="component-wrapper" data-section="admin-user-details" data-user-id="<?php echo htmlspecialchars($user['id']); ?>">
    
    <script type="application/json" id="server-user-data">
        <?php echo json_encode($user); ?>
    </script>

    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <?php echo $i18n->t('admin.users_module.toolbar.manage_account'); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    </div>
            </div>
        </div>
    </div>

    <div class="component-header-card">
        <h1 class="component-page-title" data-element="user-fullname"><?php echo htmlspecialchars($user['username']); ?></h1>
        <p class="component-page-description"><?php echo $i18n->t('settings.profile.desc'); ?></p>
    </div>

    <div class="component-card component-card--grouped mt-4">
        
        <div class="component-group-item" data-component="admin-profile-picture">
             <div class="component-card__content">
                <div class="component-card__profile-picture" data-element="user-avatar-container" data-role="<?php echo htmlspecialchars($user['role']); ?>">
                    <img src="<?php echo $user['avatar_src']; ?>" class="component-card__avatar-image" id="admin-preview-avatar">
                    <div class="component-card__avatar-overlay" id="admin-btn-trigger-upload">
                        <span class="material-symbols-rounded">photo_camera</span>
                    </div>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.pic_title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('settings.profile.pic_desc'); ?></p>
                </div>
            </div>

            <input type="file" id="admin-upload-avatar" accept="image/png, image/jpeg, image/webp, image/gif" hidden>

            <div class="component-card__actions actions-right">
                <?php 
                    $isCustom = $user['is_custom_avatar'];
                    $stateDefault = !$isCustom ? 'active' : 'disabled';
                    $stateCustom = $isCustom ? 'active' : 'disabled';
                ?>

                <div class="component-action-group <?php echo $stateDefault; ?>" data-state="default">
                    <button type="button" class="component-button primary" id="admin-btn-upload-init">
                        <?php echo $i18n->t('settings.profile.btn_upload'); ?>
                    </button>
                </div>
                
                <div class="component-action-group disabled" data-state="preview">
                    <button type="button" class="component-button" data-action="cancel-upload"><?php echo $i18n->t('settings.profile.btn_cancel'); ?></button>
                    <button type="button" class="component-button primary" data-action="save-upload"><?php echo $i18n->t('settings.profile.btn_save'); ?></button>
                </div>

                <div class="component-action-group <?php echo $stateCustom; ?>" data-state="custom">
                    <button type="button" class="component-button" data-action="delete-avatar" style="color: #d32f2f; border-color: #d32f2f30;">
                        <?php echo $i18n->t('settings.profile.btn_delete'); ?>
                    </button>
                    <button type="button" class="component-button primary" data-action="change-avatar">
                        <?php echo $i18n->t('settings.profile.btn_change'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <hr class="component-divider">

        <div class="component-group-item" data-component="admin-username-section">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.username_title'); ?></h2>
                    
                    <div class="active" data-state="view">
                        <span class="text-display-value" id="admin-display-username"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    
                    <div class="disabled w-100 input-group-responsive" data-state="edit">
                        <div class="component-input-wrapper flex-1">
                            <input type="text" class="component-text-input" id="admin-input-username" value="<?php echo htmlspecialchars($user['username']); ?>">
                        </div>
                        <div class="component-card__actions m-0">
                            <button type="button" class="component-button" data-action="cancel-edit-field" data-target="username"><?php echo $i18n->t('settings.profile.btn_cancel'); ?></button>
                            <button type="button" class="component-button primary" data-action="save-field" data-target="username"><?php echo $i18n->t('settings.profile.btn_save'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="component-card__actions actions-right active" data-state="view-actions">
                <button type="button" class="component-button" data-action="start-edit-field" data-target="username"><?php echo $i18n->t('settings.profile.btn_edit'); ?></button>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-group-item" data-component="admin-email-section">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.email_title'); ?></h2>
                    
                    <div class="active" data-state="view">
                        <span class="text-display-value" id="admin-display-email"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    
                    <div class="disabled w-100 input-group-responsive" data-state="edit">
                        <div class="component-input-wrapper flex-1">
                            <input type="email" class="component-text-input" id="admin-input-email" value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        <div class="component-card__actions m-0">
                            <button type="button" class="component-button" data-action="cancel-edit-field" data-target="email"><?php echo $i18n->t('settings.profile.btn_cancel'); ?></button>
                            <button type="button" class="component-button primary" data-action="save-field" data-target="email"><?php echo $i18n->t('settings.profile.btn_save'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="component-card__actions actions-right active" data-state="view-actions">
                <button type="button" class="component-button" data-action="start-edit-field" data-target="email"><?php echo $i18n->t('settings.profile.btn_edit'); ?></button>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-group-item">
            <div class="component-card__content">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">shield</span>
                </div>

                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('admin.user_details.2fa_title'); ?></h2>
                    <?php if ($is2FAActive): ?>
                        <p class="component-card__description" style="color: var(--color-success);">
                            <?php echo $i18n->t('admin.user_details.2fa_enabled'); ?>
                        </p>
                    <?php else: ?>
                        <p class="component-card__description" style="color: var(--text-secondary);">
                            <?php echo $i18n->t('admin.user_details.2fa_disabled_desc'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="component-card__actions actions-right">
                <?php if ($is2FAActive): ?>
                    <button type="button" class="component-button" data-action="disable-2fa" style="color: #d32f2f; border-color: rgba(211, 47, 47, 0.3);">
                        <?php echo $i18n->t('global.disable'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="component-card component-card--grouped mt-4">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.lang_title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('settings.profile.lang_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown" id="admin-lang-selector">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">language</span>
                        <?php
                            $langs = [
                                'es-latam' => 'Español (Latinoamérica)',
                                'es-mx' => 'Español (México)',
                                'en-us' => 'English (US)',
                                'en-gb' => 'English (UK)',
                                'fr-fr' => 'Français'
                            ];
                            $currentLangLabel = $langs[$prefs['language']] ?? '...';
                        ?>
                        <span class="trigger-select-text" data-element="current-lang-label"><?php echo $currentLangLabel; ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    
                    <div class="popover-module popover-module--searchable">
                        <div class="menu-content menu-content--flush">
                            <div class="menu-search-header">
                                <div class="component-input-wrapper">
                                    <input type="text" 
                                           class="component-text-input component-text-input--sm" 
                                           placeholder="<?php echo $i18n->t('header.search_placeholder'); ?>" 
                                           data-action="filter-options">
                                </div>
                            </div>
                            <div class="menu-list menu-list--scrollable overflow-y">
                                <?php foreach($langs as $code => $label): 
                                    $icon = (strpos($code, 'es') !== false) ? 'language' : 'translate';
                                    $isActive = ($code === $prefs['language']) ? 'active' : '';
                                ?>
                                    <div class="menu-link <?php echo $isActive; ?>" data-action="select-option" data-type="language" data-value="<?php echo $code; ?>" data-label="<?php echo $label; ?>">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded"><?php echo $icon; ?></span>
                                        </div>
                                        <div class="menu-link-text"><?php echo $label; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('settings.accessibility.theme_title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('settings.accessibility.theme_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown" id="admin-theme-selector">
                    <?php 
                        $themes = [
                            'sync' => ['label' => $i18n->t('settings.accessibility.theme_system'), 'icon' => 'settings_brightness'],
                            'light' => ['label' => $i18n->t('settings.accessibility.theme_light'), 'icon' => 'light_mode'],
                            'dark' => ['label' => $i18n->t('settings.accessibility.theme_dark'), 'icon' => 'dark_mode']
                        ];
                        $currentTheme = $themes[$prefs['theme']] ?? $themes['sync'];
                    ?>
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon" data-element="current-theme-icon"><?php echo $currentTheme['icon']; ?></span>
                        <span class="trigger-select-text" data-element="current-theme-label"><?php echo $currentTheme['label']; ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="popover-module">
                        <div class="menu-list">
                            <?php foreach($themes as $key => $data): 
                                $isActive = ($key === $prefs['theme']) ? 'active' : '';
                            ?>
                            <div class="menu-link <?php echo $isActive; ?>" data-action="select-option" data-type="theme" data-value="<?php echo $key; ?>" data-label="<?php echo $data['label']; ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $data['icon']; ?></span></div>
                                <div class="menu-link-text"><?php echo $data['label']; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped">
        <div class="component-group-item">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.links_title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('settings.profile.links_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" id="admin-pref-open-links" data-action="toggle-pref" data-type="open_links_new_tab" <?php echo $prefs['open_links_new_tab'] ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>
        
        <hr class="component-divider">

        <div class="component-group-item">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('settings.accessibility.toast_title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('settings.accessibility.toast_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" id="admin-pref-extended-toast" data-action="toggle-pref" data-type="extended_toast" <?php echo $prefs['extended_toast'] ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

</div>