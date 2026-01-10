<?php
// includes/sections/settings/preferences.php
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
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">language</span>
                            <span class="trigger-select-text">Español (Latinoamérica)</span>
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
                                    <div class="menu-link active" data-action="select-option" data-value="es-latam" data-label="Español (Latinoamérica)">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded trigger-select-icon">language</span></div>
                                        <div class="menu-link-text">Español (Latinoamérica)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="es-mx" data-label="Español (México)">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded trigger-select-icon">language</span></div>
                                        <div class="menu-link-text">Español (México)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="en-us" data-label="English (United States)">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded trigger-select-icon">language</span></div>
                                        <div class="menu-link-text">English (United States)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="en-uk" data-label="English (United Kingdom)">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded trigger-select-icon">language</span></div>
                                        <div class="menu-link-text">English (United Kingdom)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="fr-fr" data-label="Français (France)">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded trigger-select-icon">language</span></div>
                                        <div class="menu-link-text">Français (France)</div>
                                    </div>
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
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">settings_brightness</span>
                            <span class="trigger-select-text">Sincronizar con el sistema</span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list overflow-y">
                                <div class="menu-link active" data-action="select-option" data-value="sync" data-label="Sincronizar con el sistema">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">settings_brightness</span></div>
                                    <div class="menu-link-text">Sincronizar con el sistema</div>
                                </div>
                                <div class="menu-link" data-action="select-option" data-value="light" data-label="Tema claro">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">light_mode</span></div>
                                    <div class="menu-link-text">Tema claro</div>
                                </div>
                                <div class="menu-link" data-action="select-option" data-value="dark" data-label="Tema oscuro">
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
                        <input type="checkbox" id="pref-open-links" checked>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>