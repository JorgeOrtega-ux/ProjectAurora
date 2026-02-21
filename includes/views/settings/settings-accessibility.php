<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- PREVENIR PARPADEO (SSR PREFS) ---
$prefs = ['theme' => 'system', 'extended_alerts' => false];
if (isset($_COOKIE['aurora_prefs'])) {
    $cookiePrefs = json_decode(urldecode($_COOKIE['aurora_prefs']), true);
    if (is_array($cookiePrefs)) {
        $prefs['theme'] = $cookiePrefs['theme'] ?? $prefs['theme'];
        $prefs['extended_alerts'] = isset($cookiePrefs['extendedAlerts']) ? (bool)$cookiePrefs['extendedAlerts'] : $prefs['extended_alerts'];
    }
}
if (isset($_SESSION['user_id'])) {
    global $dbConnection;
    if (isset($dbConnection)) {
        $stmt = $dbConnection->prepare("SELECT theme, extended_alerts FROM user_preferences WHERE user_id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $dbPrefs = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbPrefs) {
            $prefs['theme'] = $dbPrefs['theme'];
            $prefs['extended_alerts'] = (bool)$dbPrefs['extended_alerts'];
        }
    }
}

$themeLabels = [
    'system' => t('settings.access.theme_system'),
    'light' => t('settings.access.theme_light'),
    'dark' => t('settings.access.theme_dark')
];
$currentThemeLabel = $themeLabels[$prefs['theme']] ?? t('settings.access.theme_system');

$themeIcons = [
    'system' => 'settings_brightness',
    'light' => 'light_mode',
    'dark' => 'dark_mode'
];
$currentThemeIcon = $themeIcons[$prefs['theme']] ?? 'settings_brightness';
$checkedAlertsAttr = $prefs['extended_alerts'] ? 'checked' : '';
?>
<div class="view-content">
    <div class="component-wrapper">
        
        <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">

        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.access.title') ?></h1>
            <p class="component-page-description"><?= t('settings.access.desc') ?></p>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.access.theme') ?></h2>
                        <p class="component-card__description"><?= t('settings.access.theme_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="component-dropdown" data-pref-key="theme">
                        <div class="component-dropdown-trigger" data-action="toggle-dropdown">
                            <span class="material-symbols-rounded trigger-select-icon"><?= $currentThemeIcon ?></span>
                            <span class="component-dropdown-text"><?= htmlspecialchars($currentThemeLabel) ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        
                        <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                            <div class="component-module-panel">
                                <div class="pill-container"><div class="drag-handle"></div></div>
                                <div class="component-module-panel-body component-module-panel-body--padded">
                                    <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                        <div class="component-menu-link <?= $prefs['theme'] === 'system' ? 'active' : '' ?>" data-action="select-option" data-value="system" data-label="<?= t('settings.access.theme_system') ?>">
                                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">settings_brightness</span></div>
                                            <div class="component-menu-link-text"><span><?= t('settings.access.theme_system') ?></span></div>
                                        </div>
                                        <div class="component-menu-link <?= $prefs['theme'] === 'light' ? 'active' : '' ?>" data-action="select-option" data-value="light" data-label="<?= t('settings.access.theme_light') ?>">
                                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">light_mode</span></div>
                                            <div class="component-menu-link-text"><span><?= t('settings.access.theme_light') ?></span></div>
                                        </div>
                                        <div class="component-menu-link <?= $prefs['theme'] === 'dark' ? 'active' : '' ?>" data-action="select-option" data-value="dark" data-label="<?= t('settings.access.theme_dark') ?>">
                                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">dark_mode</span></div>
                                            <div class="component-menu-link-text"><span><?= t('settings.access.theme_dark') ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.access.alerts') ?></h2>
                        <p class="component-card__description"><?= t('settings.access.alerts_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-extended-alerts" <?= $checkedAlertsAttr ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>