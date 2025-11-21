<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Aseguramos tener el basePath disponible
$basePath = $basePath ?? '/ProjectAurora/';

// Obtener datos del usuario de la sesión
$userAvatar = $_SESSION['user_avatar'] ?? null;

// Construir URL del avatar
$avatarUrl = null;
if ($userAvatar && !empty($userAvatar)) {
    // Agregamos timestamp para evitar caché del navegador al actualizar
    $avatarUrl = $basePath . $userAvatar . '?t=' . time();
}

// Determinar estado inicial de los botones
$hasAvatar = ($avatarUrl !== null);
?>

<div class="section-content overflow-y active" data-section="settings/your-profile">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title">Tu Perfil</h1>
            <p class="component-page-description">Aquí podrás editar tu información de perfil y personalizar tu avatar.</p>
        </div>

        <div class="component-card component-card--edit-mode" id="avatar-section">

            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="file" class="visually-hidden" id="avatar-upload-input" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp">

            <div class="component-card__content">
                <div class="component-card__avatar" id="avatar-preview-container">
                    <?php if ($avatarUrl): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" 
                             alt="Tu avatar" 
                             class="component-card__avatar-image" 
                             id="avatar-preview-image">
                    <?php else: ?>
                        <img src="" 
                             alt="Sin avatar" 
                             class="component-card__avatar-image" 
                             id="avatar-preview-image" 
                             style="display: none;">
                        <span class="material-symbols-rounded default-avatar-icon" style="font-size: 32px; color: #999;">person</span>
                    <?php endif; ?>

                    <div class="component-card__avatar-overlay" onclick="document.getElementById('avatar-upload-input').click()">
                        <span class="material-symbols-rounded">photo_camera</span>
                    </div>
                </div>

                <div class="component-card__text">
                    <h2 class="component-card__title">Foto de perfil</h2>
                    <p class="component-card__description">Esto ayudará a tus compañeros a reconocerte.</p>
                    <p class="component-card__meta">Máximo 2MB. Formatos: PNG, JPG, WEBP.</p>
                </div>
            </div>

            <div class="component-card__actions">

                <div id="avatar-actions-default" class="<?php echo !$hasAvatar ? 'active' : 'disabled'; ?>" style="gap: 12px;">
                    <button type="button" class="component-button" id="avatar-upload-trigger">
                        <span class="material-symbols-rounded" style="font-size: 18px;">upload</span>
                        Subir foto
                    </button>
                </div>

                <div id="avatar-actions-custom" class="<?php echo $hasAvatar ? 'active' : 'disabled'; ?>" style="gap: 12px;">
                    <button type="button" class="component-button" id="avatar-remove-trigger">
                        Eliminar
                    </button>
                    <button type="button" class="component-button" id="avatar-change-trigger">
                        Cambiar foto
                    </button>
                </div>

                <div id="avatar-actions-preview" class="disabled" style="gap: 12px;">
                    <button type="button" class="component-button" id="avatar-cancel-trigger">Cancelar</button>
                    <button type="button" class="component-button" id="avatar-save-trigger-btn">Guardar</button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode" id="username-section">
            <input type="hidden" name="csrf_token" value="6d379709526bc94a1f1081247db712b90d2a67d65c1f143cb5fa17a98579c9c7"> <input type="hidden" name="action" value="update-username"> 
            
            <div class="component-card__content active" id="username-view-state">
            <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.username">Nombre de usuario</h2>
                    <p class="component-card__description" id="username-display-text" data-original-username="user20251120_1825356l">
                       user20251120_1825356l                    </p>
                </div>
            </div>
            <div class="component-card__actions active" id="username-actions-view">
            <button type="button" class="component-button" id="username-edit-trigger" data-i18n="settings.profile.edit">Editar</button>
            </div>

            <div class="component-card__content disabled" id="username-edit-state">
            <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.username">Nombre de usuario</h2>
                    <input type="text" class="component-text-input" id="username-input" name="username" value="user20251120_1825356l" required="" minlength="6" maxlength="32">
                </div>
            </div>
            <div class="component-card__actions disabled" id="username-actions-edit">
            <button type="button" class="component-button" id="username-cancel-trigger" data-i18n="settings.profile.cancel">Cancelar</button>
                <button type="button" class="component-button" id="username-save-trigger-btn" data-i18n="settings.profile.save">Guardar</button>
            </div>
        </div>
        
    </div>
</div>

<style>
    /* Layout Principal */
    .component-wrapper {
        width: 100%;
        max-width: 750px;
        margin: 0 auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .component-header-card, .component-card {
        border: 1px solid #00000020; /* Borde solicitado */
        border-radius: 12px;
        padding: 24px;
        background-color: #ffffff;
        box-shadow: none;
    }

    /* CENTRADO DE HEADER */
    .component-header-card {
        text-align: center; /* Centra el contenido */
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .component-page-title {
        font-size: 24px;
        font-weight: 700;
        color: #000;
        margin-bottom: 8px;
    }

    .component-page-description {
        font-size: 15px;
        color: #666;
        line-height: 1.5;
        margin: 0;
        max-width: 500px; /* Para que no se estire demasiado si hay mucho texto */
    }

    /* Tarjeta de Avatar */
    .component-card--edit-mode {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }

    .component-card__content {
        display: flex;
        align-items: center;
        gap: 20px;
        flex: 1;
        min-width: 200px;
    }

    .component-card__text {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .component-card__title {
        font-size: 16px;
        font-weight: 600;
        color: #000;
        margin: 0;
    }

    .component-card__description {
        font-size: 14px;
        color: #666;
        margin: 0;
    }
    
    .component-card__meta {
        font-size: 12px;
        color: #999;
        margin-top: 4px;
    }

    /* Avatar Circle */
    .component-card__avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background-color: #f5f5f5;
        position: relative;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid #00000020;
    }

    .component-card__avatar-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .component-card__avatar-overlay {
        position: absolute;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        opacity: 0;
        transition: opacity 0.2s ease;
        cursor: pointer;
    }
    
    .component-card__avatar:hover .component-card__avatar-overlay {
        opacity: 1;
    }

    .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    /* BOTONES UNIFICADOS (Sin primary/danger) */
    .component-card__actions {
        display: flex;
        align-items: center;
    }

    #avatar-actions-default, 
    #avatar-actions-custom, 
    #avatar-actions-preview {
        display: none;
        align-items: center;
    }

    #avatar-actions-default.active, 
    #avatar-actions-custom.active, 
    #avatar-actions-preview.active {
        display: flex;
    }

    .component-button {
        height: 38px;
        padding: 0 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        
        /* ESTILOS UNIFICADOS SOLICITADOS */
        background-color: transparent;
        border: 1px solid #00000020;
        color: #000;
    }

    .component-button:hover {
        background-color: #f5f5fa; /* Un gris muy sutil al hover */
    }
    
    .component-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Responsive */
    @media (max-width: 640px) {
        .component-card--edit-mode {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .component-card__actions {
            width: 100%;
            justify-content: flex-end;
            margin-top: 10px;
        }
    }

    .component-text-input {
    width: 100%;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #00000020;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    background-color: transparent;
    color: #000;
    margin-top: 4px;
}
</style>