<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Recuperamos datos de sesión disponibles para una integración más fluida
$userName = $_SESSION['user_name'] ?? 'Usuario';
$userAvatar = $_SESSION['user_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=random';
$userRole = $_SESSION['user_role'] ?? 'user';
// Puedes reemplazar esto con el correo real cuando lo agregues a la sesión
$userEmail = 'jorge@uat.edu.mx'; 
?>

<div class="view-content">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Tu Perfil</h1>
            <p class="component-page-description">Administra tu foto de perfil, información personal y preferencias de cuenta.</p>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item" data-component="profile-picture-section">
                 <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="<?php echo htmlspecialchars($userRole); ?>">
                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" class="component-card__avatar-image" id="preview-avatar">
                        <div class="component-card__avatar-overlay" id="btn-trigger-upload">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Foto de perfil</h2>
                        <p class="component-card__description">Se recomienda una imagen cuadrada en formato PNG o JPG.</p>
                    </div>
                </div>

                <input type="file" id="upload-avatar" accept="image/png, image/jpeg, image/webp, image/gif" hidden>

                <div class="component-card__actions actions-right">
                    <div class="component-action-group active" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button primary" id="btn-upload-init">
                            Subir foto
                        </button>
                    </div>

                    <div class="component-action-group disabled" data-state="profile-picture-actions-preview">
                        <button type="button" class="component-button" data-action="profile-picture-cancel">Cancelar</button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save">Guardar</button>
                    </div>

                    <div class="component-action-group disabled" data-state="profile-picture-actions-custom">
                        <button type="button" class="component-button" data-action="profile-picture-delete" style="color: #d32f2f; border-color: #d32f2f30;">
                            Eliminar foto
                        </button>
                        <button type="button" class="component-button primary" data-action="profile-picture-change">
                            Cambiar foto
                        </button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Nombre de usuario</h2>
                        <div class="active" data-state="username-view-state">
                            <span class="text-display-value" id="display-username"><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                        <div class="disabled w-100 input-group-responsive" data-state="username-edit-state">
                            <div class="component-input-wrapper flex-1" style="height: 36px;">
                                <input type="text" class="component-text-input component-text-input--simple" id="input-username" value="<?php echo htmlspecialchars($userName); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="username-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="username">Cancelar</button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="username">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="username-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="username">Editar</button>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Correo electrónico</h2>
                        <div class="active" data-state="email-view-state">
                            <span class="text-display-value" id="display-email"><?php echo htmlspecialchars($userEmail); ?></span>
                        </div>
                        <div class="disabled w-100 input-group-responsive" data-state="email-edit-state">
                            <div class="component-input-wrapper flex-1" style="height: 36px;">
                                <input type="email" class="component-text-input component-text-input--simple" id="input-email" value="<?php echo htmlspecialchars($userEmail); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="email-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="email">Cancelar</button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="email">Guardar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="email-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="email">Editar</button>
                </div>
            </div>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Idioma de la interfaz</h2>
                        <p class="component-card__description">Selecciona tu idioma preferido para la plataforma.</p>
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
                                
                                <div class="pill-container">
                                    <div class="drag-handle"></div>
                                </div>

                                <div class="component-module-panel-header--search">
                                    <div class="component-input-wrapper component-input-wrapper--search">
                                        <input type="text" 
                                               class="component-text-input component-text-input--simple" 
                                               placeholder="Buscar idioma..." 
                                               data-action="filter-options"> 
                                    </div>
                                </div>

                                <div class="component-module-panel-body component-module-panel-body--padded">
                                    <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                        <div class="component-menu-link active" data-action="select-option" data-value="es-latam" data-label="Español (Latinoamérica)">
                                            <div class="component-menu-link-icon">
                                                <span class="material-symbols-rounded">language</span>
                                            </div>
                                            <div class="component-menu-link-text"><span>Español (Latinoamérica)</span></div>
                                        </div>
                                        <div class="component-menu-link" data-action="select-option" data-value="es-mx" data-label="Español (México)">
                                            <div class="component-menu-link-icon">
                                                <span class="material-symbols-rounded">language</span>
                                            </div>
                                            <div class="component-menu-link-text"><span>Español (México)</span></div>
                                        </div>
                                        <div class="component-menu-link" data-action="select-option" data-value="en-us" data-label="English (United States)">
                                            <div class="component-menu-link-icon">
                                                <span class="material-symbols-rounded">translate</span>
                                            </div>
                                            <div class="component-menu-link-text"><span>English (United States)</span></div>
                                        </div>
                                        <div class="component-menu-link" data-action="select-option" data-value="en-gb" data-label="English (United Kingdom)">
                                            <div class="component-menu-link-icon">
                                                <span class="material-symbols-rounded">translate</span>
                                            </div>
                                            <div class="component-menu-link-text"><span>English (United Kingdom)</span></div>
                                        </div>
                                        <div class="component-menu-link" data-action="select-option" data-value="fr-fr" data-label="Français (France)">
                                            <div class="component-menu-link-icon">
                                                <span class="material-symbols-rounded">translate</span>
                                            </div>
                                            <div class="component-menu-link-text"><span>Français (France)</span></div>
                                        </div>
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
                        <h2 class="component-card__title">Abrir enlaces en una pestaña nueva</h2>
                        <p class="component-card__description">Los enlaces externos se abrirán en una nueva pestaña del navegador.</p>
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