<?php
// includes/sections/settings/guest-preferences.php

// Etiquetas por defecto (PHP renderiza esto primero)
$currentThemeLabel = "Sincronizar con el sistema"; 
$currentLangLabel = "Español (Latinoamérica)";

// Lista completa de idiomas (Igual que en perfil de usuario)
$langLabels = [
    'es-latam' => 'Español (Latinoamérica)',
    'es-mx'    => 'Español (México)',
    'en-us'    => 'English (United States)',
    'en-gb'    => 'English (United Kingdom)',
    'fr-fr'    => 'Français (France)'
];
?>

<div class="section-content active" data-section="settings/preferences">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title">Preferencias</h1>
            <p class="component-page-description">Personaliza tu experiencia de navegación.</p>
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
                    <div class="trigger-select-wrapper" data-trigger="dropdown" id="guest-theme-selector">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">settings_brightness</span>
                            <span class="trigger-select-text"><?php echo $currentThemeLabel; ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-option" data-value="sync" data-label="Sincronizar con el sistema" data-type="theme">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">settings_brightness</span></div>
                                    <div class="menu-link-text">Sincronizar con el sistema</div>
                                </div>
                                <div class="menu-link" data-action="select-option" data-value="light" data-label="Tema claro" data-type="theme">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">light_mode</span></div>
                                    <div class="menu-link-text">Tema claro</div>
                                </div>
                                <div class="menu-link" data-action="select-option" data-value="dark" data-label="Tema oscuro" data-type="theme">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">dark_mode</span></div>
                                    <div class="menu-link-text">Tema oscuro</div>
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
                        <h2 class="component-card__title"><?php echo $i18n->t('settings.profile.lang_title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('settings.profile.lang_desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown" id="guest-lang-selector">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">translate</span>
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
                                               data-action="filter-options"> 
                                    </div>
                                </div>

                                <div class="menu-list menu-list--scrollable overflow-y">
                                    <?php foreach($langLabels as $code => $label): 
                                        // Por defecto ponemos activo es-latam, el script JS lo corregirá si es otro
                                        $isActive = ($code === 'es-latam') ? 'active' : '';
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
                        <input type="checkbox" id="pref-open-links" checked>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>

    <script nonce="<?php echo $cspNonce; ?>">
    (function() {
        try {
            // 1. Leer preferencias locales
            var prefs = JSON.parse(localStorage.getItem('guest_prefs') || '{}');
            
            // 2. Función helper para actualizar dropdowns visualmente
            function syncDropdown(wrapperId, storedValue) {
                if (!storedValue) return;
                var wrapper = document.getElementById(wrapperId);
                if (!wrapper) return;

                // Buscar la opción que coincide con lo guardado
                var targetOption = wrapper.querySelector('.menu-link[data-value="' + storedValue + '"]');
                
                if (targetOption) {
                    // Actualizar texto del trigger principal
                    var triggerText = wrapper.querySelector('.trigger-select-text');
                    var label = targetOption.getAttribute('data-label'); // Usar data-label para texto exacto
                    if (triggerText && label) triggerText.textContent = label;

                    // Actualizar icono del trigger (si aplica)
                    var triggerIcon = wrapper.querySelector('.trigger-select-icon');
                    var optionIcon = targetOption.querySelector('.material-symbols-rounded');
                    if (triggerIcon && optionIcon) triggerIcon.textContent = optionIcon.textContent;

                    // Actualizar clases: Quitar 'active' de todos y ponerlo al correcto
                    var allOptions = wrapper.querySelectorAll('.menu-link');
                    allOptions.forEach(function(opt) { opt.classList.remove('active'); });
                    targetOption.classList.add('active');
                }
            }

            // 3. Ejecutar sincronización visual inmediatamente
            syncDropdown('guest-theme-selector', prefs.theme);
            syncDropdown('guest-lang-selector', prefs.language);

            // 4. Sincronizar Toggle Links
            var toggleLinks = document.getElementById('pref-open-links');
            if (toggleLinks && prefs.hasOwnProperty('open_links_new_tab')) {
                toggleLinks.checked = prefs.open_links_new_tab;
            }

        } catch (e) {
            console.error("Error sincronizando UI de invitado", e);
        }
    })();
    </script>
</div>