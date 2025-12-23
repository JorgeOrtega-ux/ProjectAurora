<?php
// includes/sections/settings/accessibility.php

$currentTheme = $_SESSION['preferences']['theme'] ?? 'sync';
$isExtended = isset($_SESSION['preferences']['extended_toast']) && $_SESSION['preferences']['extended_toast'] == 1;

// Obtenemos las etiquetas traducidas
$labelSystem = $i18n->t('settings.accessibility.theme_system');
$labelLight = $i18n->t('settings.accessibility.theme_light');
$labelDark = $i18n->t('settings.accessibility.theme_dark');

$themeLabels = [
    'sync' => $labelSystem,
    'light' => $labelLight,
    'dark' => $labelDark
];
$themeIcons = [
    'sync' => 'settings_brightness',
    'light' => 'light_mode',
    'dark' => 'dark_mode'
];

$currentThemeLabel = $themeLabels[$currentTheme] ?? $labelSystem;
$currentThemeIcon = $themeIcons[$currentTheme] ?? 'settings_brightness';
?>

<div class="section-content active" data-section="settings/accessibility">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->t('settings.accessibility.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->t('settings.accessibility.desc'); ?></p>
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
                    <div class="trigger-select-wrapper" onclick="toggleDropdown(this)">
                        
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon"><?php echo $currentThemeIcon; ?></span>
                            <span class="trigger-select-text"><?php echo $currentThemeLabel; ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link <?php echo ($currentTheme === 'sync') ? 'active' : ''; ?>" 
                                     onclick="selectOption(this, '<?php echo $labelSystem; ?>', 'sync')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">settings_brightness</span>
                                    </div>
                                    <div class="menu-link-text"><?php echo $labelSystem; ?></div>
                                </div>
                                
                                <div class="menu-link <?php echo ($currentTheme === 'light') ? 'active' : ''; ?>" 
                                     onclick="selectOption(this, '<?php echo $labelLight; ?>', 'light')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">light_mode</span>
                                    </div>
                                    <div class="menu-link-text"><?php echo $labelLight; ?></div>
                                </div>
                                
                                <div class="menu-link <?php echo ($currentTheme === 'dark') ? 'active' : ''; ?>" 
                                     onclick="selectOption(this, '<?php echo $labelDark; ?>', 'dark')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">dark_mode</span>
                                    </div>
                                    <div class="menu-link-text"><?php echo $labelDark; ?></div>
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
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.accessibility.toast_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.accessibility.toast_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-extended-toast" <?php echo $isExtended ? 'checked' : ''; ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>