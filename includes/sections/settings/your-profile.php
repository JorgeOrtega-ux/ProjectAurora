<div class="section-content active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Tu Perfil</h1>
            <p class="component-page-description">Gestiona tu identidad y preferencias personales.</p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="profile-picture-section">
                <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="administrator">
                        <img src="https://ui-avatars.com/api/?name=Jorge+Ortega&background=random" 
                             class="component-card__avatar-image" 
                             id="preview-avatar">
                        
                        <div class="component-card__avatar-overlay" onclick="document.getElementById('upload-avatar').click()">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Foto de Perfil</h2>
                        <p class="component-card__description">Visible para todos.</p>
                    </div>
                </div>

                <input type="file" id="upload-avatar" accept="image/*" hidden>

                <div class="component-card__actions actions-right">
                    <div class="active" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button primary" onclick="document.getElementById('upload-avatar').click()">
                            Subir foto
                        </button>
                    </div>
                    <div class="disabled" data-state="profile-picture-actions-preview">
                        <button type="button" class="component-button" data-action="profile-picture-cancel">Cancelar</button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save">Guardar</button>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Nombre de usuario</h2>

                        <div class="active" data-state="username-view-state">
                            <span class="text-display-value" id="display-username">Jorge Ortega</span>
                        </div>

                        <div class="disabled w-100 input-group-responsive" data-state="username-edit-state">
                            <div class="component-input-wrapper flex-1">
                                <input type="text" class="component-text-input" id="input-username" value="Jorge Ortega">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="username-actions-edit">
                                <button type="button" class="component-button" onclick="toggleEdit('username', false)">Cancelar</button>
                                <button type="button" class="component-button primary" onclick="saveData('username')">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="username-actions-view">
                    <button type="button" class="component-button" onclick="toggleEdit('username', true)">Editar</button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Correo electrónico</h2>

                        <div class="active" data-state="email-view-state">
                            <span class="text-display-value" id="display-email">jorge@ejemplo.com</span>
                        </div>

                        <div class="disabled w-100 input-group-responsive" data-state="email-edit-state">
                            <div class="component-input-wrapper flex-1">
                                <input type="email" class="component-text-input" id="input-email" value="jorge@ejemplo.com">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="email-actions-edit">
                                <button type="button" class="component-button" onclick="toggleEdit('email', false)">Cancelar</button>
                                <button type="button" class="component-button primary" onclick="saveData('email')">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="email-actions-view">
                    <button type="button" class="component-button" onclick="toggleEdit('email', true)">Editar</button>
                </div>
            </div>

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
                    <div class="trigger-select-wrapper" onclick="toggleDropdown(this)">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">language</span>
                            <span class="trigger-select-text">Español (Latinoamérica)</span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active" onclick="selectOption(this, 'Español (Latinoamérica)')">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">language</span></div>
                                    <div class="menu-link-text">Español (Latinoamérica)</div>
                                </div>
                                <div class="menu-link" onclick="selectOption(this, 'English (US)')">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">translate</span></div>
                                    <div class="menu-link-text">English (US)</div>
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
                        <h2 class="component-card__title">Abrir enlaces en pestaña nueva</h2>
                        <p class="component-card__description">Los enlaces externos se abrirán en otra ventana.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" checked>
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>