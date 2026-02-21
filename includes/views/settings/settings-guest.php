<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- PREVENIR PARPADEO (SSR PREFS) ---
$prefs = ['language' => 'en-us', 'open_links_new_tab' => true];
if (isset($_COOKIE['aurora_prefs'])) {
    $cookiePrefs = json_decode(urldecode($_COOKIE['aurora_prefs']), true);
    if (is_array($cookiePrefs)) {
        $prefs['language'] = $cookiePrefs['language'] ?? $prefs['language'];
        $prefs['open_links_new_tab'] = isset($cookiePrefs['openLinksNewTab']) ? (bool)$cookiePrefs['openLinksNewTab'] : $prefs['open_links_new_tab'];
    }
}

$langLabels = [
    'en-us' => 'English (United States)', 'en-gb' => 'English (United Kingdom)',
    'fr-fr' => 'Français (France)', 'de-de' => 'Deutsch (Deutschland)',
    'it-it' => 'Italiano (Italia)', 'es-latam' => 'Español (Latinoamérica)',
    'es-mx' => 'Español (México)', 'es-es' => 'Español (España)',
    'pt-br' => 'Português (Brasil)', 'pt-pt' => 'Português (Portugal)'
];
$currentLangLabel = $langLabels[$prefs['language']] ?? 'English (United States)';
$checkedAttr = $prefs['open_links_new_tab'] ? 'checked' : '';
?>
<div class="view-content">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.guest.title') ?></h1>
            <p class="component-page-description"><?= t('settings.guest.desc') ?></p>
            <div class="component-actions">
                <button class="component-button component-button--black component-button--rect-40" data-nav="/ProjectAurora/login">
                    <?= t('settings.guest.login') ?>
                </button>
            </div>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.guest.lang') ?></h2>
                        <p class="component-card__description"><?= t('settings.guest.lang_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions">
                    
                    <div class="component-dropdown" data-pref-key="language">
                        <div class="component-dropdown-trigger" data-action="toggle-dropdown">
                            <span class="material-symbols-rounded trigger-select-icon">language</span>
                            <span class="component-dropdown-text"><?= htmlspecialchars($currentLangLabel) ?></span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        
                        <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                            <div class="component-module-panel">
                                <div class="pill-container"><div class="drag-handle"></div></div>
                                <div class="component-module-panel-header--search">
                                    <div class="component-input-wrapper component-input-wrapper--search">
                                        <input type="text" class="component-text-input component-text-input--simple" placeholder="<?= t('settings.account.search') ?>" data-action="filter-options"> 
                                    </div>
                                </div>
                                <div class="component-module-panel-body component-module-panel-body--padded">
                                    <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                        <div class="component-menu-link <?= $prefs['language'] === 'en-us' ? 'active' : '' ?>" data-action="select-option" data-value="en-us" data-label="English (United States)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>English (United States)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'en-gb' ? 'active' : '' ?>" data-action="select-option" data-value="en-gb" data-label="English (United Kingdom)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>English (United Kingdom)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'fr-fr' ? 'active' : '' ?>" data-action="select-option" data-value="fr-fr" data-label="Français (France)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Français (France)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'de-de' ? 'active' : '' ?>" data-action="select-option" data-value="de-de" data-label="Deutsch (Deutschland)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Deutsch (Deutschland)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'it-it' ? 'active' : '' ?>" data-action="select-option" data-value="it-it" data-label="Italiano (Italia)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Italiano (Italia)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'es-latam' ? 'active' : '' ?>" data-action="select-option" data-value="es-latam" data-label="Español (Latinoamérica)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (Latinoamérica)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'es-mx' ? 'active' : '' ?>" data-action="select-option" data-value="es-mx" data-label="Español (México)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (México)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'es-es' ? 'active' : '' ?>" data-action="select-option" data-value="es-es" data-label="Español (España)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (España)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'pt-br' ? 'active' : '' ?>" data-action="select-option" data-value="pt-br" data-label="Português (Brasil)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Português (Brasil)</span></div></div>
                                        <div class="component-menu-link <?= $prefs['language'] === 'pt-pt' ? 'active' : '' ?>" data-action="select-option" data-value="pt-pt" data-label="Português (Portugal)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Português (Portugal)</span></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item component-group-item--wrap">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.guest.links') ?></h2>
                        <p class="component-card__description"><?= t('settings.guest.links_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-open-links-guest" <?= $checkedAttr ?>>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>