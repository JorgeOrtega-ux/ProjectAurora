<?php
// includes/sections/settings/preferences.php

// ==========================================
// 1. LÓGICA DE SERVIDOR (PHP)
// ==========================================

// A. Obtener preferencias guardadas en Cookie (si existen)
$cookies = json_decode($_COOKIE['project_aurora_prefs'] ?? '{}', true);

// B. Definir valores por defecto o leídos de la cookie
$prefTheme = $cookies['theme'] ?? 'sync';
$prefOpenLinks = $cookies['openLinksNewTab'] ?? true;
$prefLang = $cookies['language'] ?? 'auto';

// C. Lógica Inteligente de Detección de Idioma (Si está en 'auto' o no hay cookie)
if ($prefLang === 'auto' || empty($prefLang)) {
    // 1. Obtener idioma del navegador (Header Accept-Language)
    // Ej: "es-MX,es;q=0.9,en;q=0.8"
    $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $primaryLang = strtolower(substr($browserLangs[0], 0, 5)); // "es-mx" o "en-us"
    
    // 2. Mapa de coincidencia exacta y cercanía
    $supportedLangs = [
        'es-mx'    => 'Español (México)',
        'es-latam' => 'Español (Latinoamérica)',
        'en-us'    => 'English (United States)',
        'en-uk'    => 'English (United Kingdom)',
        'fr-fr'    => 'Français (France)'
    ];

    // 3. Algoritmo de decisión
    if (array_key_exists($primaryLang, $supportedLangs)) {
        // Coincidencia exacta (ej: es-mx)
        $prefLang = $primaryLang;
    } elseif ($primaryLang === 'es-419') {
        // Caso especial Latam
        $prefLang = 'es-latam';
    } elseif (strpos($primaryLang, 'es') === 0) {
        // Cualquier otro español (es-ar, es-es, es-co) -> Español más cercano (Latam o Mx según prefieras)
        // Como pediste: si es algo como es-ar asignamos el más cercano. Usaremos Latam como genérico.
        $prefLang = 'es-latam';
    } elseif (strpos($primaryLang, 'en') === 0) {
        // Cualquier inglés -> US
        $prefLang = 'en-us';
    } else {
        // Fallback final
        $prefLang = 'en-us';
    }
}

// D. Pre-calcular etiquetas para la UI (Para que ya venga con texto correcto)
$langLabels = [
    'es-latam' => 'Español (Latinoamérica)',
    'es-mx'    => 'Español (México)',
    'en-us'    => 'English (United States)',
    'en-uk'    => 'English (United Kingdom)',
    'fr-fr'    => 'Français (France)'
];
$currentLangLabel = $langLabels[$prefLang] ?? 'English (United States)';

$themeLabels = [
    'sync'  => 'Sincronizar con el sistema',
    'light' => 'Tema claro',
    'dark'  => 'Tema oscuro'
];
$currentThemeLabel = $themeLabels[$prefTheme] ?? 'Sincronizar con el sistema';

?>

<div class="section-content active" data-section="settings/preferences">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Preferencias</h1>
            <p class="component-page-description">Personaliza tu experiencia visual y de navegación.</p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Idioma</h2>
                        <p class="component-card__description">Selecciona el idioma de la interfaz.</p>
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
                                        <input type="text" class="component-text-input component-text-input--sm" placeholder="Buscar idioma..." data-action="filter-list">
                                    </div>
                                </div>
                                <div class="menu-list menu-list--scrollable overflow-y">
                                    <?php foreach($langLabels as $code => $label): ?>
                                    <div class="menu-link <?php echo $prefLang === $code ? 'active' : ''; ?>" 
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
                        <h2 class="component-card__title">Tema</h2>
                        <p class="component-card__description">Define la apariencia de la aplicación.</p>
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
                                <div class="menu-link <?php echo $prefTheme === 'sync' ? 'active' : ''; ?>" data-action="select-option" data-value="sync" data-label="Sincronizar con el sistema">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">settings_brightness</span></div>
                                    <div class="menu-link-text">Sincronizar con el sistema</div>
                                </div>
                                <div class="menu-link <?php echo $prefTheme === 'light' ? 'active' : ''; ?>" data-action="select-option" data-value="light" data-label="Tema claro">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">light_mode</span></div>
                                    <div class="menu-link-text">Tema claro</div>
                                </div>
                                <div class="menu-link <?php echo $prefTheme === 'dark' ? 'active' : ''; ?>" data-action="select-option" data-value="dark" data-label="Tema oscuro">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">dark_mode</span></div>
                                    <div class="menu-link-text">Tema oscuro</div>
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
                        <h2 class="component-card__title">Navegación</h2>
                        <p class="component-card__description">Abrir enlaces externos en una nueva pestaña.</p>
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