<?php
// includes/sections/settings/accessibility.php

if (session_status() === PHP_SESSION_NONE) session_start();
// Aseguramos acceso a la BD
require_once __DIR__ . '/../../../config/database/db.php';

// Obtener preferencias de accesibilidad actuales del usuario
$theme = 'system';
$extendedAlerts = 0;

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT theme, extended_alerts FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $prefs = $stmt->fetch();
        if ($prefs) {
            $theme = $prefs['theme'] ?? 'system';
            $extendedAlerts = (int)($prefs['extended_alerts'] ?? 0);
        }
    } catch(Exception $e) {}
}

// Configuración visual para el Dropdown de Tema
$themesMap = [
    'system' => ['label' => __('settings.accessibility.theme_system'), 'icon' => 'settings_brightness'],
    'light'  => ['label' => __('settings.accessibility.theme_light'),  'icon' => 'light_mode'],
    'dark'   => ['label' => __('settings.accessibility.theme_dark'),   'icon' => 'dark_mode'],
];

$currentThemeData = $themesMap[$theme] ?? $themesMap['system'];
?>

<div class="section-content active" data-section="settings/accessibility">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?= __('settings.accessibility.title') ?></h1>
            <p class="component-page-description"><?= __('settings.accessibility.desc') ?></p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('settings.accessibility.theme_title') ?></h2>
                        <p class="component-card__description"><?= __('settings.accessibility.theme_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-ui-type="dropdown" data-align="left" data-pref="theme">
                        <div class="trigger-selector" data-action="toggle-dropdown">
                            <span class="material-symbols-rounded trigger-select-icon">contrast</span>
                            <span class="trigger-select-text"><?php echo $currentThemeData['label']; ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list">
                                <?php foreach ($themesMap as $key => $data): ?>
                                    <div class="menu-link body-text <?php echo ($key === $theme) ? 'active' : ''; ?>"
                                        data-value="<?php echo $key; ?>">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded"><?php echo $data['icon']; ?></span>
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
                        <h2 class="component-card__title"><?= __('settings.accessibility.alerts_title') ?></h2>
                        <p class="component-card__description"><?= __('settings.accessibility.alerts_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-extended-alerts"
                            <?php echo ($extendedAlerts == 1) ? 'checked' : ''; ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>