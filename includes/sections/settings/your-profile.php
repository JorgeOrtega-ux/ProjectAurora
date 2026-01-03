<?php
// includes/sections/settings/your-profile.php

$currentAvatarPath = $_SESSION['avatar'] ?? '';
$isCustomAvatar = (strpos($currentAvatarPath, 'storage/profilePicture/custom/') !== false);
$classDefault = $isCustomAvatar ? 'disabled' : 'active'; 
$classCustom  = $isCustomAvatar ? 'active' : 'disabled'; 

$prefLang = $_SESSION['preferences']['language'] ?? 'es-latam';
$prefOpenLinks = $_SESSION['preferences']['open_links_new_tab'] ?? true;

$langLabels = [
    'es-latam' => 'Español (Latinoamérica)',
    'es-mx'    => 'Español (México)',
    'en-us'    => 'English (United States)',
    'en-gb'    => 'English (United Kingdom)',
    'fr-fr'    => 'Français (France)'
];

if (!array_key_exists($prefLang, $langLabels)) {
    $prefLang = 'es-latam';
}

$currentLangLabel = $langLabels[$prefLang];
?>

<div class="section-content active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->t('settings.profile.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->t('settings.profile.desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item" data-component="profile-picture-section">
                 <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="<?php echo htmlspecialchars($_SESSION['role'] ?? 'user'); ?>">
                        <img src="<?php echo $globalAvatarSrc; ?>" class="component-card__avatar-image" id="preview-avatar">
                        <div class="component-card__avatar-overlay" id="btn-trigger-upload">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.pic_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.profile.pic_desc'); ?></p>
                    </div>
                </div>

                <input type="file" id="upload-avatar" accept="image/png, image/jpeg, image/webp, image/gif" hidden>

                <div class="component-card__actions actions-right">
                    <div class="component-action-group <?php echo $classDefault; ?>" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button primary" id="btn-upload-init">
                            <?php echo $i18n->t('settings.profile.btn_upload'); ?>
                        </button>
                    </div>

                    <div class="component-action-group disabled" data-state="profile-picture-actions-preview">
                        <button type="button" class="component-button" data-action="profile-picture-cancel"><?php echo $i18n->t('settings.profile.btn_cancel'); ?></button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save"><?php echo $i18n->t('settings.profile.btn_save'); ?></button>
                    </div>

                    <div class="component-action-group <?php echo $classCustom; ?>" data-state="profile-picture-actions-custom">
                        <button type="button" class="component-button" data-action="profile-picture-delete" style="color: #d32f2f; border-color: #d32f2f30;">
                            <?php echo $i18n->t('settings.profile.btn_delete'); ?>
                        </button>
                        <button type="button" class="component-button primary" data-action="profile-picture-change">
                            <?php echo $i18n->t('settings.profile.btn_change'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.username_title'); ?></h2>
                        <div class="active" data-state="username-view-state">
                            <span class="text-display-value" id="display-username"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                        </div>
                        <div class="disabled w-100 input-group-responsive" data-state="username-edit-state">
                            <div class="component-input-wrapper flex-1">
                                <input type="text" class="component-text-input" id="input-username" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="username-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="username"><?php echo $i18n->t('settings.profile.btn_cancel'); ?></button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="username"><?php echo $i18n->t('settings.profile.btn_save'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="username-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="username"><?php echo $i18n->t('settings.profile.btn_edit'); ?></button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.email_title'); ?></h2>
                        <div class="active" data-state="email-view-state">
                            <span class="text-display-value" id="display-email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></span>
                        </div>
                        <div class="disabled w-100 input-group-responsive" data-state="email-edit-state">
                            <div class="component-input-wrapper flex-1">
                                <input type="email" class="component-text-input" id="input-email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="email-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="email"><?php echo $i18n->t('settings.profile.btn_cancel'); ?></button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="email"><?php echo $i18n->t('settings.profile.btn_save'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="email-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="email"><?php echo $i18n->t('settings.profile.btn_edit'); ?></button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.lang_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.profile.lang_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">
                                <?php echo (strpos($prefLang, 'es') !== false) ? 'language' : 'translate'; ?>
                            </span>
                            <span class="trigger-select-text"><?php echo $currentLangLabel; ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        
                        <div class="popover-module popover-module--searchable">
                            <div class="menu-content menu-content--flush">
                                
                                <div class="menu-search-header">
                                    <div class="component-input-wrapper">
                                        <input type="text" 
                                               class="component-text-input component-text-input--sm" 
                                               placeholder="<?php echo $i18n->t('header.search_placeholder'); ?>" 
                                               data-action="filter-languages">
                                    </div>
                                </div>

                                <div class="menu-list menu-list--scrollable overflow-y">
                                    <?php foreach($langLabels as $code => $label): 
                                        $isActive = ($code === $prefLang) ? 'active' : '';
                                        $icon = (strpos($code, 'es') !== false) ? 'language' : 'translate'; 
                                    ?>
                                        <div class="menu-link <?php echo $isActive; ?>" 
                                             data-action="select-option"
                                             data-value="<?php echo $code; ?>"
                                             data-label="<?php echo $label; ?>"
                                             data-type="language">
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
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.links_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.profile.links_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-open-links" <?php echo ($prefOpenLinks) ? 'checked' : ''; ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>