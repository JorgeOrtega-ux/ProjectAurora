<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = $basePath ?? '/ProjectAurora/';
$userId = $_SESSION['user_id'];

// Obtenemos datos del usuario
$stmt = $pdo->prepare("SELECT username, email, avatar, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$currentUsername = $currentUser['username'] ?? 'Usuario';
$currentEmail = $currentUser['email'] ?? 'correo@ejemplo.com';
$userAvatar = $currentUser['avatar'] ?? null;
$userRole = $currentUser['role'] ?? 'user';

// URL del avatar
$avatarUrl = null;
if ($userAvatar && !empty($userAvatar)) {
    $avatarUrl = $basePath . $userAvatar . '?t=' . time();
}

// Detectar si es avatar default
$isDefaultAvatar = false;
if (empty($userAvatar) || strpos($userAvatar, '/default/') !== false) {
    $isDefaultAvatar = true;
}
$hasCustomAvatar = !$isDefaultAvatar && ($avatarUrl !== null);
?>

<div class="section-content overflow-y active" data-section="settings/your-profile">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Tu Perfil</h1>
            <p class="component-page-description">Aquí podrás editar tu información de perfil y personalizar tu avatar.</p>
        </div>

        <div class="component-card component-card--edit-mode" data-component="avatar-section">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="file" class="visually-hidden" data-element="avatar-upload-input" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp">

            <div class="component-card__content">
                <div class="component-card__avatar" data-element="avatar-preview-container" data-role="<?php echo htmlspecialchars($userRole); ?>">
                    <?php if ($avatarUrl): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="component-card__avatar-image" data-element="avatar-preview-image">
                    <?php else: ?>
                        <img src="" alt="Sin avatar" class="component-card__avatar-image" data-element="avatar-preview-image" style="display: none;">
                        <span class="material-symbols-rounded default-avatar-icon" style="font-size: 32px; color: #999;">person</span>
                    <?php endif; ?>

                    <div class="component-card__avatar-overlay" data-action="trigger-avatar-upload">
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
                <div data-state="avatar-actions-default" class="<?php echo !$hasCustomAvatar ? 'active' : 'disabled'; ?>">
                    <button type="button" class="component-button" data-action="avatar-upload-trigger">
                        <span class="material-symbols-rounded" style="font-size: 18px;">upload</span> Subir foto
                    </button>
                </div>

                <div data-state="avatar-actions-custom" class="<?php echo $hasCustomAvatar ? 'active' : 'disabled'; ?>">
                    <button type="button" class="component-button" data-action="avatar-remove-trigger">Eliminar</button>
                    <button type="button" class="component-button" data-action="avatar-change-trigger">Cambiar foto</button>
                </div>

                <div data-state="avatar-actions-preview" class="disabled">
                    <button type="button" class="component-button" data-action="avatar-cancel-trigger">Cancelar</button>
                    <button type="button" class="component-button" data-action="avatar-save-trigger-btn">Guardar</button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode" data-component="username-section">

            <div class="component-card__content">
                <div class="component-card__text" style="width: 100%;">
                    <h2 class="component-card__title">Nombre de usuario</h2>

                    <div data-state="username-view-state" class="active">
                        <p class="component-card__description" data-element="username-display-text">
                            <?php echo htmlspecialchars($currentUsername); ?>
                        </p>
                    </div>

                    <div data-state="username-edit-state" class="disabled">
                        <div class="input-with-actions">
                            <input type="text" class="component-text-input" data-element="username-input"
                                value="<?php echo htmlspecialchars($currentUsername); ?>"
                                required minlength="8" maxlength="32">

                            <div data-state="username-actions-edit" class="disabled">
                                <button type="button" class="component-button" data-action="username-cancel-trigger">Cancelar</button>
                                <button type="button" class="component-button primary" data-action="username-save-trigger-btn">Guardar</button>
                            </div>
                        </div>
                        <p class="component-card__meta">8-32 caracteres. Letras, números y guión bajo.</p>
                    </div>
                </div>
            </div>

            <div class="component-card__actions">
                <div data-state="username-actions-view" class="active">
                    <button type="button" class="component-button" data-action="username-edit-trigger">Editar</button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode" data-component="email-section">

            <div class="component-card__content">
                <div class="component-card__text" style="width: 100%;">
                    <h2 class="component-card__title">Correo Electrónico</h2>

                    <div data-state="email-view-state" class="active">
                        <p class="component-card__description" data-element="email-display-text">
                            <?php echo htmlspecialchars($currentEmail); ?>
                        </p>
                    </div>

                    <div data-state="email-edit-state" class="disabled">
                        <div class="input-with-actions">
                            <input type="email" class="component-text-input" data-element="email-input"
                                value="<?php echo htmlspecialchars($currentEmail); ?>"
                                required>

                            <div data-state="email-actions-edit" class="disabled">
                                <button type="button" class="component-button" data-action="email-cancel-trigger">Cancelar</button>
                                <button type="button" class="component-button primary" data-action="email-save-trigger-btn">Guardar</button>
                            </div>
                        </div>
                        <p class="component-card__meta">Debe ser un dominio válido (Gmail, Outlook, iCloud, Yahoo).</p>
                    </div>
                </div>
            </div>

            <div class="component-card__actions">
                <div data-state="email-actions-view" class="active">
                    <button type="button" class="component-button" data-action="email-edit-trigger">Editar</button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--column">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.usageTitle">¿Para qué usarás ProjectAurora?</h2>
                    <p class="component-card__description" data-i18n="settings.profile.usageDesc">Esto nos ayudará a personalizar tu experiencia.</p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" data-action="toggleModuleUsageSelect">
                        <div class="trigger-select-icon">
                            <span class="material-symbols-rounded">person</span>
                        </div>
                        <div class="trigger-select-text">
                            <span data-i18n="settings.profile.usagePersonal">Uso personal</span>
                        </div>
                        <div class="trigger-select-arrow">
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                    </div>

                    <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleUsageSelect" data-preference-type="usage">
                        <div class="menu-content">
                            <div class="menu-list">
                                <div class="menu-link active" data-value="personal">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                                    <div class="menu-link-text"><span data-i18n="settings.profile.usagePersonal">Uso personal</span></div>
                                    <div class="menu-link-check-icon"><span class="material-symbols-rounded">check</span></div>
                                </div>
                                <div class="menu-link" data-value="student">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">school</span></div>
                                    <div class="menu-link-text"><span data-i18n="settings.profile.usageStudent">Estudiante</span></div>
                                    <div class="menu-link-check-icon"></div>
                                </div>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--column">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.langTitle">Idioma</h2>
                    <p class="component-card__description" data-i18n="settings.profile.langDesc">Selecciona tu idioma preferido.</p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" data-action="toggleModuleLanguageSelect">
                        <div class="trigger-select-icon"><span class="material-symbols-rounded">language</span></div>
                        <div class="trigger-select-text"><span data-i18n="settings.profile.langEsLatam">Español (Latinoamérica)</span></div>
                        <div class="trigger-select-arrow"><span class="material-symbols-rounded">arrow_drop_down</span></div>
                    </div>
                    <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleLanguageSelect" data-preference-type="language">
                        <div class="menu-content">
                            <div class="menu-list">
                                <div class="menu-link active" data-value="es-latam">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">language</span></div>
                                    <div class="menu-link-text"><span data-i18n="settings.profile.langEsLatam">Español (Latinoamérica)</span></div>
                                    <div class="menu-link-check-icon"><span class="material-symbols-rounded">check</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.profile.newTabTitle">Abrir los enlaces en una pestaña nueva</h2>
                    <p class="component-card__description" data-i18n="settings.profile.newTabDesc">En el navegador web, los enlaces siempre se abrirán en una pestaña nueva.</p>
                </div>
            </div>
            <div class="component-card__actions">
                <label class="component-toggle-switch">
                    <input type="checkbox" data-element="toggle-new-tab" data-preference-type="boolean" data-field-name="open_links_in_new_tab" checked="">
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

    </div>
</div>