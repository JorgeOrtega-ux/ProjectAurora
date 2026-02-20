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
                                <input type="text" class="component-text-input" id="input-username" value="<?php echo htmlspecialchars($userName); ?>" style="padding-top: 0;">
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
                                <input type="email" class="component-text-input" id="input-email" value="<?php echo htmlspecialchars($userEmail); ?>" style="padding-top: 0;">
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
                <div class="component-card__actions" style="width: 100%;">
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">
                                language
                            </span>
                            <span class="trigger-select-text">Español (Latinoamérica)</span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        
                        <div class="popover-module popover-module--searchable">
                            <div class="menu-content menu-content--flush">
                                
                                <div class="menu-search-header">
                                    <div class="component-input-wrapper" style="height: 36px;">
                                        <input type="text" 
                                               class="component-text-input" 
                                               placeholder="Buscar idioma..." 
                                               data-action="filter-options" style="padding-top: 0;"> 
                                    </div>
                                </div>

                                <div class="menu-list menu-list--scrollable overflow-y">
                                    <div class="menu-link active" data-action="select-option" data-value="es-latam" data-label="Español (Latinoamérica)">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">language</span>
                                        </div>
                                        <div class="menu-link-text">Español (Latinoamérica)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="es-mx" data-label="Español (México)">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">language</span>
                                        </div>
                                        <div class="menu-link-text">Español (México)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="en-us" data-label="English (United States)">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">translate</span>
                                        </div>
                                        <div class="menu-link-text">English (United States)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="en-gb" data-label="English (United Kingdom)">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">translate</span>
                                        </div>
                                        <div class="menu-link-text">English (United Kingdom)</div>
                                    </div>
                                    <div class="menu-link" data-action="select-option" data-value="fr-fr" data-label="Français (France)">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">translate</span>
                                        </div>
                                        <div class="menu-link-text">Français (France)</div>
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