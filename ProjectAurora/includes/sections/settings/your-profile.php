<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = $basePath ?? '/ProjectAurora/';
$userId = $_SESSION['user_id'];

// 1. Obtener datos del usuario
$stmt = $pdo->prepare("SELECT username, email, avatar, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$currentUsername = $currentUser['username'] ?? 'Usuario';
$currentEmail = $currentUser['email'] ?? 'correo@ejemplo.com';
$userAvatar = $currentUser['avatar'] ?? null;
$userRole = $currentUser['role'] ?? 'user';

// 2. Obtener preferencias
$stmtPrefs = $pdo->prepare("SELECT usage_intent, language, open_links_in_new_tab FROM user_preferences WHERE user_id = ?");
$stmtPrefs->execute([$userId]);
$prefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

$currentUsage = $prefs['usage_intent'] ?? 'personal';
$currentLang = $prefs['language'] ?? 'en-us';
$openLinksInNewTab = isset($prefs['open_links_in_new_tab']) ? (int)$prefs['open_links_in_new_tab'] : 1;

// --- [CORRECCIÓN 1] Mapas para mostrar el texto E ICONOS dinámicos ---
$usageTexts = [
    'personal' => 'Uso personal',
    'student' => 'Estudiante',
    'teacher' => 'Docente',
    'small_business' => 'Empresa pequeña',
    'large_business' => 'Empresa grande'
];

// Nuevo mapa para los iconos
$usageIcons = [
    'personal' => 'person',
    'student' => 'school',
    'teacher' => 'history_edu',
    'small_business' => 'storefront',
    'large_business' => 'domain'
];

$usageDisplayText = $usageTexts[$currentUsage] ?? 'Uso personal';
// Seleccionamos el icono guardado o 'person' por defecto
$usageDisplayIcon = $usageIcons[$currentUsage] ?? 'person'; 

$langTexts = [
    'es-latam' => 'Español (Latinoamérica)',
    'es-mx' => 'Español (México)',
    'en-us' => 'English (United States)',
    'en-gb' => 'English (United Kingdom)'
];

$langDisplayText = $langTexts[$currentLang] ?? 'English (United States)';

// URL del avatar
$avatarUrl = null;
if ($userAvatar && !empty($userAvatar)) {
    $avatarUrl = $basePath . $userAvatar . '?t=' . time();
}

$isDefaultAvatar = false;
if (empty($userAvatar) || strpos($userAvatar, '/default/') !== false) {
    $isDefaultAvatar = true;
}
$hasCustomAvatar = !$isDefaultAvatar && ($avatarUrl !== null);
?>

<div class="section-content active" data-section="settings/your-profile">
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
                        Subir foto
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
                            <span class="material-symbols-rounded"><?php echo htmlspecialchars($usageDisplayIcon); ?></span>
                        </div>
                        <div class="trigger-select-text">
                            <span><?php echo htmlspecialchars($usageDisplayText); ?></span>
                        </div>
                        <div class="trigger-select-arrow">
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                    </div>

                    <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleUsageSelect" data-preference-type="usage">
                        <div class="menu-content">
                            <div class="menu-list">
                                <?php
                                // Usamos el mismo array $usageIcons para mantener consistencia
                                $usageOptions = [
                                    ['val' => 'personal', 'icon' => $usageIcons['personal'], 'label' => 'Uso personal'],
                                    ['val' => 'student', 'icon' => $usageIcons['student'], 'label' => 'Estudiante'],
                                    ['val' => 'teacher', 'icon' => $usageIcons['teacher'], 'label' => 'Docente'],
                                    ['val' => 'small_business', 'icon' => $usageIcons['small_business'], 'label' => 'Empresa pequeña'],
                                    ['val' => 'large_business', 'icon' => $usageIcons['large_business'], 'label' => 'Empresa grande'],
                                ];
                                foreach ($usageOptions as $opt): 
                                    $isActive = ($currentUsage === $opt['val']) ? 'active' : '';
                                    $check = ($isActive) ? '<span class="material-symbols-rounded">check</span>' : '';
                                ?>
                                <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span></div>
                                    <div class="menu-link-text"><span><?php echo $opt['label']; ?></span></div>
                                    <div class="menu-link-icon"><?php echo $check; ?></div>
                                </div>
                                <?php endforeach; ?>
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
                        <div class="trigger-select-icon"><span class="material-symbols-rounded">translate</span></div>
                        <div class="trigger-select-text"><span><?php echo htmlspecialchars($langDisplayText); ?></span></div>
                        <div class="trigger-select-arrow"><span class="material-symbols-rounded">arrow_drop_down</span></div>
                    </div>
                    <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleLanguageSelect" data-preference-type="language">
                        <div class="menu-content">
                            <div class="menu-list">
                                <?php 
                                // [CORRECCIÓN 4] Todos los iconos unificados a 'translate'
                                $langOptions = [
                                    ['val' => 'es-latam', 'icon' => 'translate', 'label' => 'Español (Latinoamérica)'],
                                    ['val' => 'es-mx', 'icon' => 'translate', 'label' => 'Español (México)'],
                                    ['val' => 'en-us', 'icon' => 'translate', 'label' => 'English (United States)'],
                                    ['val' => 'en-gb', 'icon' => 'translate', 'label' => 'English (United Kingdom)'],
                                ];
                                foreach ($langOptions as $opt): 
                                    $isActive = ($currentLang === $opt['val']) ? 'active' : '';
                                    $check = ($isActive) ? '<span class="material-symbols-rounded">check</span>' : '';
                                ?>
                                <div class="menu-link <?php echo $isActive; ?>" data-value="<?php echo $opt['val']; ?>">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded"><?php echo $opt['icon']; ?></span></div>
                                    <div class="menu-link-text"><span><?php echo $opt['label']; ?></span></div>
                                    <div class="menu-link-icon"><?php echo $check; ?></div>
                                </div>
                                <?php endforeach; ?>
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
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" 
                           data-element="toggle-new-tab" 
                           data-preference-type="boolean" 
                           data-field-name="open_links_in_new_tab" 
                           <?php echo ($openLinksInNewTab == 1) ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

    </div>
</div>