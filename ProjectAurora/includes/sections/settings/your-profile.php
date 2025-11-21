<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = $basePath ?? '/ProjectAurora/';
$userId = $_SESSION['user_id'];

// 1. Obtener datos del usuario
$stmt = $pdo->prepare("SELECT username, email, avatar, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$currentUsername = $currentUser['username'] ?? 'Usuario';
$currentEmail = $currentUser['email'] ?? 'correo@ejemplo.com';
$userAvatar = $currentUser['avatar'] ?? null;
$userRole = $currentUser['role'] ?? 'user';

// 2. Obtener preferencias
$stmtPrefs = $pdo->prepare("SELECT usage_intent, language, open_links_in_new_tab FROM user_preferences WHERE user_id = ?");
$stmtPrefs->execute([$userId]);
$prefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

$currentUsage = $prefs['usage_intent'] ?? 'personal';
$currentLang = $prefs['language'] ?? 'en-us';
$openLinksInNewTab = isset($prefs['open_links_in_new_tab']) ? (int)$prefs['open_links_in_new_tab'] : 1;

$usageIcons = [
    'personal' => 'person',
    'student' => 'school',
    'teacher' => 'history_edu',
    'small_business' => 'storefront',
    'large_business' => 'domain'
];
$usageDisplayIcon = $usageIcons[$currentUsage] ?? 'person'; 

$langDisplayText = 'English (United States)';
if($currentLang == 'es-latam') $langDisplayText = 'Español (Latinoamérica)';
if($currentLang == 'es-mx') $langDisplayText = 'Español (México)';
if($currentLang == 'en-gb') $langDisplayText = 'English (United Kingdom)';

$avatarUrl = null;
if ($userAvatar && !empty($userAvatar)) {
    $avatarUrl = $basePath . $userAvatar . '?t=' . time();
}

$isDefaultAvatar = false;
if (empty($userAvatar) || strpos($userAvatar, '/default/') !== false) {
    $isDefaultAvatar = true;
}
$hasCustomAvatar = !$isDefaultAvatar && ($avatarUrl !== null);
?>

<div class="section-content active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.profile.title"><?php echo trans('settings.profile.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.profile.description"><?php echo trans('settings.profile.description'); ?></p>
        </div>

        <div class="component-card component-card--edit-mode" data-component="avatar-section">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="file" class="visually-hidden" data-element="avatar-upload-input" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp">

            <div class="component-card__content">
                <div class="component-card__avatar" data-element="avatar-preview-container" data-role="<?php echo htmlspecialchars($userRole); ?>">
                    <?php if ($avatarUrl): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="component-card__avatar-image" data-element="avatar-preview-image">
                    <?php else: ?>
                        <img src="" alt="Sin avatar" class="component-card__avatar-image" data-element="avatar-preview-image" style="display: none;">
                        <span class="material-symbols-rounded default-avatar-icon" style="font-size: 32px; color: #999;">person</span>
                    <?php endif; ?>

                    <div class="component-card__avatar-overlay" data-action="trigger-avatar-upload">
                        <span class="material-symbols-rounded">photo_camera</span>
                    </div>
                </div>

                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.avatar_title"><?php echo trans('settings.profile.avatar_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.profile.avatar_desc"><?php echo trans('settings.profile.avatar_desc'); ?></p>
                    <p class="component-card__meta" data-i18n="settings.profile.avatar_meta"><?php echo trans('settings.profile.avatar_meta'); ?></p>
                </div>
            </div>

            <div class="component-card__actions">
                <div data-state="avatar-actions-default" class="<?php echo !$hasCustomAvatar ? 'active' : 'disabled'; ?>">
                    <button type="button" class="component-button" data-action="avatar-upload-trigger" data-i18n="settings.profile.upload_btn"><?php echo trans('settings.profile.upload_btn'); ?></button>
                </div>

                <div data-state="avatar-actions-custom" class="<?php echo $hasCustomAvatar ? 'active' : 'disabled'; ?>">
                    <button type="button" class="component-button" data-action="avatar-remove-trigger" data-i18n="global.delete"><?php echo trans('global.delete'); ?></button>
                    <button type="button" class="component-button" data-action="avatar-change-trigger" data-i18n="settings.profile.change_btn"><?php echo trans('settings.profile.change_btn'); ?></button>
                </div>

                <div data-state="avatar-actions-preview" class="disabled">
                    <button type="button" class="component-button" data-action="avatar-cancel-trigger" data-i18n="global.cancel"><?php echo trans('global.cancel'); ?></button>
                    <button type="button" class="component-button" data-action="avatar-save-trigger-btn" data-i18n="global.save"><?php echo trans('global.save'); ?></button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode" data-component="username-section">
            <div class="component-card__content">
                <div class="component-card__text" style="width: 100%;">
                    <h2 class="component-card__title" data-i18n="settings.profile.username_title"><?php echo trans('settings.profile.username_title'); ?></h2>
                    <div data-state="username-view-state" class="active">
                        <p class="component-card__description" data-element="username-display-text">
                            <?php echo htmlspecialchars($currentUsername); ?>
                        </p>
                    </div>
                    <div data-state="username-edit-state" class="disabled">
                        <div class="input-with-actions">
                            <input type="text" class="component-text-input" data-element="username-input"
                                value="<?php echo htmlspecialchars($currentUsername); ?>"
                                required minlength="8" maxlength="32">
                            <div data-state="username-actions-edit" class="disabled">
                                <button type="button" class="component-button" data-action="username-cancel-trigger" data-i18n="global.cancel"><?php echo trans('global.cancel'); ?></button>
                                <button type="button" class="component-button primary" data-action="username-save-trigger-btn" data-i18n="global.save"><?php echo trans('global.save'); ?></button>
                            </div>
                        </div>
                        <p class="component-card__meta" data-i18n="settings.profile.username_meta"><?php echo trans('settings.profile.username_meta'); ?></p>
                    </div>
                </div>
            </div>
            <div class="component-card__actions">
                <div data-state="username-actions-view" class="active">
                    <button type="button" class="component-button" data-action="username-edit-trigger" data-i18n="global.edit"><?php echo trans('global.edit'); ?></button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode" data-component="email-section">
            <div class="component-card__content">
                <div class="component-card__text" style="width: 100%;">
                    <h2 class="component-card__title" data-i18n="settings.profile.email_title"><?php echo trans('settings.profile.email_title'); ?></h2>
                    <div data-state="email-view-state" class="active">
                        <p class="component-card__description" data-element="email-display-text">
                            <?php echo htmlspecialchars($currentEmail); ?>
                        </p>
                    </div>
                    <div data-state="email-edit-state" class="disabled">
                        <div class="input-with-actions">
                            <input type="email" class="component-text-input" data-element="email-input"
                                value="<?php echo htmlspecialchars($currentEmail); ?>"
                                required>
                            <div data-state="email-actions-edit" class="disabled">
                                <button type="button" class="component-button" data-action="email-cancel-trigger" data-i18n="global.cancel"><?php echo trans('global.cancel'); ?></button>
                                <button type="button" class="component-button primary" data-action="email-save-trigger-btn" data-i18n="global.save"><?php echo trans('global.save'); ?></button>
                            </div>
                        </div>
                        <p class="component-card__meta" data-i18n="settings.profile.email_meta"><?php echo trans('settings.profile.email_meta'); ?></p>
                    </div>
                </div>
            </div>
            <div class="component-card__actions">
                <div data-state="email-actions-view" class="active">
                    <button type="button" class="component-button" data-action="email-edit-trigger" data-i18n="global.edit"><?php echo trans('global.edit'); ?></button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--column">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.usage_title"><?php echo trans('settings.profile.usage_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.profile.usage_desc"><?php echo trans('settings.profile.usage_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" data-action="toggleModuleUsageSelect">
                        <div class="trigger-select-icon">
                            <span class="material-symbols-rounded"><?php echo htmlspecialchars($usageDisplayIcon); ?></span>
                        </div>
                        <div class="trigger-select-text">
                            <span data-i18n="settings.usage_options.<?php echo $currentUsage; ?>"><?php echo trans("settings.usage_options.{$currentUsage}"); ?></span>
                        </div>
                        <div class="trigger-select-arrow">
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                    </div>

                    <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleUsageSelect" data-preference-type="usage">
                        <div class="menu-content">
                            <div class="menu-list">
                                <?php
                                $usageOptions = [
                                    ['val' => 'personal', 'icon' => $usageIcons['personal'], 'i18n' => 'settings.usage_options.personal'],
                                    ['val' => 'student', 'icon' => $usageIcons['student'], 'i18n' => 'settings.usage_options.student'],
                                    ['val' => 'teacher', 'icon' => $usageIcons['teacher'], 'i18n' => 'settings.usage_options.teacher'],
                                    ['val' => 'small_business', 'icon' => $usageIcons['small_business'], 'i18n' => 'settings.usage_options.small_business'],
                                    ['val' => 'large_business', 'icon' => $usageIcons['large_business'], 'i18n' => 'settings.usage_options.large_business'],
                                ];
                                foreach ($usageOptions as $opt): 
                                    $isActive = ($currentUsage === $opt['val']) ? 'active' : '';
                                    $check = ($isActive) ? '<span class="material-symbols-rounded">check</span>' : '';
                                ?>
                                <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span></div>
                                    <div class="menu-link-text"><span data-i18n="<?php echo $opt['i18n']; ?>"><?php echo trans($opt['i18n']); ?></span></div>
                                    <div class="menu-link-icon"><?php echo $check; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--column">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.lang_title"><?php echo trans('settings.profile.lang_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.profile.lang_desc"><?php echo trans('settings.profile.lang_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" data-action="toggleModuleLanguageSelect">
                        <div class="trigger-select-icon"><span class="material-symbols-rounded">translate</span></div>
                        <div class="trigger-select-text"><span><?php echo htmlspecialchars($langDisplayText); ?></span></div>
                        <div class="trigger-select-arrow"><span class="material-symbols-rounded">arrow_drop_down</span></div>
                    </div>
                    <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleLanguageSelect" data-preference-type="language">
                        <div class="menu-content">
                            <div class="menu-list">
                                <?php 
                                $langOptions = [
                                    ['val' => 'es-latam', 'icon' => 'translate', 'label' => 'Español (Latinoamérica)'],
                                    ['val' => 'es-mx', 'icon' => 'translate', 'label' => 'Español (México)'],
                                    ['val' => 'en-us', 'icon' => 'translate', 'label' => 'English (United States)'],
                                    ['val' => 'en-gb', 'icon' => 'translate', 'label' => 'English (United Kingdom)'],
                                ];
                                foreach ($langOptions as $opt): 
                                    $isActive = ($currentLang === $opt['val']) ? 'active' : '';
                                    $check = ($isActive) ? '<span class="material-symbols-rounded">check</span>' : '';
                                ?>
                                <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span></div>
                                    <div class="menu-link-text"><span><?php echo $opt['label']; ?></span></div>
                                    <div class="menu-link-icon"><?php echo $check; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.new_tab_title"><?php echo trans('settings.profile.new_tab_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.profile.new_tab_desc"><?php echo trans('settings.profile.new_tab_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" 
                           data-element="toggle-new-tab" 
                           data-preference-type="boolean" 
                           data-field-name="open_links_in_new_tab" 
                           <?php echo ($openLinksInNewTab == 1) ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

    </div>
</div>