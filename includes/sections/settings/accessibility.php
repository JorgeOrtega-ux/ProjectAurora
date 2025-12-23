<div class="section-content active" data-section="settings/accessibility">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title">Accesibilidad</h1>
            <p class="component-page-description">Personaliza la apariencia y el comportamiento de la aplicación.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Tema</h2>
                        <p class="component-card__description">Elige el tema de la interfaz.</p>
                    </div>
                </div>
                
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" onclick="toggleDropdown(this)">
                        
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">contrast</span>
                            <span class="trigger-select-text">Sistema</span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                
                                <div class="menu-link active" onclick="selectOption(this, 'Sistema')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">settings_brightness</span>
                                    </div>
                                    <div class="menu-link-text">Sistema</div>
                                </div>

                                <div class="menu-link" onclick="selectOption(this, 'Claro')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">light_mode</span>
                                    </div>
                                    <div class="menu-link-text">Claro</div>
                                </div>

                                <div class="menu-link" onclick="selectOption(this, 'Oscuro')">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">dark_mode</span>
                                    </div>
                                    <div class="menu-link-text">Oscuro</div>
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
                        <h2 class="component-card__title">Alertas detalladas</h2>
                        <p class="component-card__description">Muestra descripciones más extensas en las notificaciones flotantes.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="pref-extended-alerts">
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>