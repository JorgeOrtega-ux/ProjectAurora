<?php
// includes/sections/settings/your-profile.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/database/db.php';

$currentUser = [];
$userId = $_SESSION['user_id'] ?? 0;

if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT u.username, u.email, u.role, u.uuid, p.language, p.open_links_new_tab FROM users u LEFT JOIN user_preferences p ON u.id = p.user_id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $userDB = $stmt->fetch();
        if ($userDB) {
            $currentUser = $userDB;
            if (empty($currentUser['language'])) $currentUser['language'] = 'en-US';
            if (!isset($currentUser['open_links_new_tab'])) $currentUser['open_links_new_tab'] = 1;
        }
    } catch (Exception $e) {}
}

if (empty($currentUser)) {
    $currentUser = [
        'username' => $_SESSION['username'] ?? 'Usuario',
        'email'    => 'No disponible',
        'role'     => $_SESSION['role'] ?? 'user',
        'uuid'     => $_SESSION['uuid'] ?? '',
        'language' => 'en-US',
        'open_links_new_tab' => 1
    ];
}

$hasCustomAvatar = false;
$finalAvatarSrc = '';

if (!empty($currentUser['uuid'])) {
    $uuid = $currentUser['uuid'];
    $relCustom  = 'assets/uploads/avatars/custom/' . $uuid . '.png';
    $relDefault = 'assets/uploads/avatars/default/' . $uuid . '.png';
    $absCustom  = __DIR__ . '/../../../public/' . $relCustom;

    if (file_exists($absCustom)) {
        $hasCustomAvatar = true;
        $finalAvatarSrc = (isset($basePath) ? $basePath : '/ProjectAurora/') . $relCustom . '?v=' . microtime(true);
    } else {
        $finalAvatarSrc = (isset($basePath) ? $basePath : '/ProjectAurora/') . $relDefault . '?v=' . microtime(true);
    }
}

$languagesMap = [
    'es-419' => ['label' => 'Español (Latinoamérica)', 'icon' => 'language'],
    'en-US'  => ['label' => 'English (US)', 'icon' => 'translate'],
    'en-GB'  => ['label' => 'English (UK)', 'icon' => 'translate'],
    'fr-FR'  => ['label' => 'Français (France)', 'icon' => 'language_french'],
    'pt-BR'  => ['label' => 'Português (Brasil)', 'icon' => 'public'],
];

$currentLangCode = $currentUser['language'];
$currentLangData = $languagesMap[$currentLangCode] ?? $languagesMap['en-US'];
?>

<div class="section-content active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-lang-key="settings.profile.title"><?= __('settings.profile.title') ?></h1>
            <p class="component-page-description" data-lang-key="settings.profile.desc"><?= __('settings.profile.desc') ?></p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="profile-picture-section" data-has-custom="<?php echo $hasCustomAvatar ? 'true' : 'false'; ?>">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="<?php echo htmlspecialchars($currentUser['role']); ?>">
                        <img src="<?php echo htmlspecialchars($finalAvatarSrc); ?>"
                            class="component-card__avatar-image"
                            data-element="profile-picture-preview-image">
                        <div class="component-card__avatar-overlay" data-action="trigger-profile-picture-upload">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-lang-key="settings.profile.photo_title"><?= __('settings.profile.photo_title') ?></h2>
                        <p class="component-card__description" data-lang-key="settings.profile.photo_desc"><?= __('settings.profile.photo_desc') ?></p>
                    </div>
                </div>

                <input type="file" accept="image/png, image/jpeg, image/webp" hidden data-element="profile-picture-upload-input">

                <div class="component-card__actions actions-right">
                    <div class="active" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button danger"
                            data-action="profile-picture-remove-trigger"
                            style="<?php echo $hasCustomAvatar ? '' : 'display:none;'; ?>">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                        <button type="button" class="component-button primary" data-action="profile-picture-upload-trigger">
                            <span class="material-symbols-rounded">upload</span>
                            <span data-element="upload-btn-text" data-lang-key="<?php echo $hasCustomAvatar ? 'settings.profile.change_btn' : 'settings.profile.upload_btn'; ?>">
                                <?php echo $hasCustomAvatar ? __('settings.profile.change_btn') : __('settings.profile.upload_btn'); ?>
                            </span>
                        </button>
                    </div>
                    <div class="disabled" data-state="profile-picture-actions-preview">
                        <button type="button" class="component-button" data-action="profile-picture-cancel-trigger" data-lang-key="global.cancel"><?= __('global.cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save-trigger-btn" data-lang-key="global.save"><?= __('global.save') ?></button>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-lang-key="settings.profile.username_title"><?= __('settings.profile.username_title') ?></h2>

                        <div class="active" data-state="username-view-state">
                            <span style="font-size: 13px; color: #333;" data-element="username-display-text">
                                <?php echo htmlspecialchars($currentUser['username']); ?>
                            </span>
                        </div>

                        <div class="disabled w-100 input-group-responsive" data-state="username-edit-state">
                            <div class="component-input-wrapper" style="flex: 1;">
                                <input type="text" class="component-text-input"
                                    value="<?php echo htmlspecialchars($currentUser['username']); ?>"
                                    data-element="username-input">
                            </div>
                            <div class="component-card__actions disabled" data-state="username-actions-edit" style="margin: 0;">
                                <button type="button" class="component-button" data-action="username-cancel-trigger" data-lang-key="global.cancel"><?= __('global.cancel') ?></button>
                                <button type="button" class="component-button primary" data-action="username-save-trigger-btn" data-lang-key="global.save"><?= __('global.save') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="username-actions-view">
                    <button type="button" class="component-button" data-action="username-edit-trigger" data-lang-key="global.edit"><?= __('global.edit') ?></button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-lang-key="settings.profile.email_title"><?= __('settings.profile.email_title') ?></h2>

                        <div class="active" data-state="email-view-state">
                            <span style="font-size: 13px; color: #333;" data-element="email-display-text">
                                <?php echo htmlspecialchars($currentUser['email']); ?>
                            </span>
                        </div>

                        <div class="disabled w-100 input-group-responsive" data-state="email-edit-state">
                            <div class="component-input-wrapper" style="flex: 1;">
                                <input type="email" class="component-text-input"
                                    value="<?php echo htmlspecialchars($currentUser['email']); ?>"
                                    data-element="email-input">
                            </div>
                            <div class="component-card__actions disabled" data-state="email-actions-edit" style="margin: 0;">
                                <button type="button" class="component-button" data-action="email-cancel-trigger" data-lang-key="global.cancel"><?= __('global.cancel') ?></button>
                                <button type="button" class="component-button primary" data-action="email-save-trigger-btn" data-lang-key="global.save"><?= __('global.save') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="email-actions-view">
                    <button type="button" class="component-button" data-action="email-edit-trigger" data-lang-key="global.edit"><?= __('global.edit') ?></button>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-lang-key="settings.profile.language_title"><?= __('settings.profile.language_title') ?></h2>
                        <p class="component-card__description" data-lang-key="settings.profile.language_desc"><?= __('settings.profile.language_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-ui-type="dropdown" data-align="left" data-pref="language">
                        <div class="trigger-selector" data-action="toggle-dropdown">
                            <span class="material-symbols-rounded trigger-select-icon">language</span>
                            <span class="trigger-select-text"><?php echo $currentLangData['label']; ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list">
                                <?php foreach ($languagesMap as $code => $data): ?>
                                    <div class="menu-link body-text <?php echo ($code === $currentLangCode) ? 'active' : ''; ?>"
                                        data-value="<?php echo $code; ?>">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">language</span>
                                        </div>
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
                        <h2 class="component-card__title" data-lang-key="settings.profile.links_title"><?= __('settings.profile.links_title') ?></h2>
                        <p class="component-card__description" data-lang-key="settings.profile.links_desc"><?= __('settings.profile.links_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-links-new-tab"
                            <?php echo ($currentUser['open_links_new_tab'] == 1) ? 'checked' : ''; ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>