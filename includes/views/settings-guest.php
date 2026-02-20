<div class="view-content">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.guest.title') ?></h1>
            <p class="component-page-description"><?= t('settings.guest.desc') ?></p>
            <div class="component-actions" style="justify-content: center; margin-top: 16px;">
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
                <div class="component-card__actions w-100">
                    
                    <div class="component-dropdown">
                        <div class="component-dropdown-trigger" data-action="toggle-dropdown">
                            <span class="material-symbols-rounded trigger-select-icon">language</span>
                            <span class="component-dropdown-text">Español (Latinoamérica)</span>
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
                                        <div class="component-menu-link" data-action="select-option" data-value="en-us" data-label="English (United States)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>English (United States)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="en-gb" data-label="English (United Kingdom)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>English (United Kingdom)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="fr-fr" data-label="Français (France)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Français (France)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="de-de" data-label="Deutsch (Deutschland)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Deutsch (Deutschland)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="it-it" data-label="Italiano (Italia)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Italiano (Italia)</span></div></div>
                                        <div class="component-menu-link active" data-action="select-option" data-value="es-latam" data-label="Español (Latinoamérica)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (Latinoamérica)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="es-mx" data-label="Español (México)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (México)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="es-es" data-label="Español (España)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (España)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="pt-br" data-label="Português (Brasil)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Português (Brasil)</span></div></div>
                                        <div class="component-menu-link" data-action="select-option" data-value="pt-pt" data-label="Português (Portugal)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Português (Portugal)</span></div></div>
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
                        <h2 class="component-card__title"><?= t('settings.guest.links') ?></h2>
                        <p class="component-card__description"><?= t('settings.guest.links_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-open-links-guest" checked>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>