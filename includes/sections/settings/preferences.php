<?php
// includes/sections/settings/preferences.php

// Recuperamos el idioma actual cargado por boot.php
global $currentLang, $currentTheme;

// Mapas de Etiquetas usando traducción para evitar hardcode
$langLabels = [
    'es-latam' => __('lang.es-latam'),
    'es-mx'    => __('lang.es-mx'),
    'en-us'    => __('lang.en-us'),
    'en-uk'    => __('lang.en-uk'),
    'fr-fr'    => __('lang.fr-fr')
];

// Obtener etiqueta actual
$currentLangLabel = $langLabels[$currentLang] ?? $langLabels['es-latam'];

// Etiquetas para temas usando traducción
$themeLabels = [
    'sync'  => __('theme.sync'),
    'light' => __('theme.light'),
    'dark'  => __('theme.dark')
];
$currentThemeLabel = $themeLabels[$currentTheme] ?? __('theme.sync');

$prefOpenLinks = json_decode($_COOKIE['project_aurora_prefs'] ?? '{}', true)['openLinksNewTab'] ?? true;
?>

<div class="section-content active" data-section="settings/preferences">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo __('settings.page_title'); ?></h1>
            <p class="component-page-description"><?php echo __('settings.page_desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo __('settings.lang_title'); ?></h2>
                        <p class="component-card__description"><?php echo __('settings.lang_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown" id="pref-trigger-language">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">language</span>
                            <span class="trigger-select-text"><?php echo htmlspecialchars($currentLangLabel); ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        
                        <div class="popover-module popover-module--searchable">
                            <div class="menu-content menu-content--flush">
                                <div class="menu-search-header">
                                    <div class="component-input-wrapper">
                                        <input type="text" class="component-text-input component-text-input--sm" placeholder="<?php echo __('settings.search_placeholder'); ?>" data-action="filter-list">
                                    </div>
                                </div>
                                <div class="menu-list menu-list--scrollable overflow-y">
                                    <?php foreach($langLabels as $code => $label): ?>
                                    <div class="menu-link <?php echo $currentLang === $code ? 'active' : ''; ?>" 
                                         data-action="select-option" 
                                         data-value="<?php echo $code; ?>" 
                                         data-label="<?php echo $label; ?>">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded trigger-select-icon">language</span></div>
                                        <div class="menu-link-text"><?php echo $label; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo __('settings.theme_title'); ?></h2>
                        <p class="component-card__description"><?php echo __('settings.theme_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown" id="pref-trigger-theme">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">settings_brightness</span>
                            <span class="trigger-select-text"><?php echo htmlspecialchars($currentThemeLabel); ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list overflow-y">
                                <div class="menu-link <?php echo $currentTheme === 'sync' ? 'active' : ''; ?>" data-action="select-option" data-value="sync" data-label="<?php echo __('theme.sync'); ?>">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">settings_brightness</span></div>
                                    <div class="menu-link-text"><?php echo __('theme.sync'); ?></div>
                                </div>
                                <div class="menu-link <?php echo $currentTheme === 'light' ? 'active' : ''; ?>" data-action="select-option" data-value="light" data-label="<?php echo __('theme.light'); ?>">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">light_mode</span></div>
                                    <div class="menu-link-text"><?php echo __('theme.light'); ?></div>
                                </div>
                                <div class="menu-link <?php echo $currentTheme === 'dark' ? 'active' : ''; ?>" data-action="select-option" data-value="dark" data-label="<?php echo __('theme.dark'); ?>">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">dark_mode</span></div>
                                    <div class="menu-link-text"><?php echo __('theme.dark'); ?></div>
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
                        <h2 class="component-card__title"><?php echo __('settings.nav_title'); ?></h2>
                        <p class="component-card__description"><?php echo __('settings.nav_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-open-links" <?php echo $prefOpenLinks ? 'checked' : ''; ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>