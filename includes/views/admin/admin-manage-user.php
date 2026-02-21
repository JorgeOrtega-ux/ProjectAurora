<?php
// includes/views/admin/admin-manage-user.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad de la vista: solo administradores
$userRole = $_SESSION['user_role'] ?? 'user';
if (!in_array($userRole, ['administrator', 'founder'])) {
    http_response_code(403);
    echo "<div class='view-content'><div class='component-wrapper'><h1 style='color: var(--color-error); text-align: center;'>Acceso denegado</h1></div></div>";
    exit;
}

$targetUuid = $_GET['uuid'] ?? '';
$targetUser = null;
$targetPrefs = ['language' => 'en-us', 'open_links_new_tab' => 1, 'theme' => 'system', 'extended_alerts' => 0];

if (!empty($targetUuid)) {
    global $dbConnection;
    if (isset($dbConnection)) {
        try {
            $stmt = $dbConnection->prepare("SELECT id, uuid, username, email, avatar_path, role, status FROM users WHERE uuid = :uuid LIMIT 1");
            $stmt->execute([':uuid' => $targetUuid]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($targetUser) {
                $stmtPrefs = $dbConnection->prepare("SELECT language, open_links_new_tab, theme, extended_alerts FROM user_preferences WHERE user_id = :id LIMIT 1");
                $stmtPrefs->execute([':id' => $targetUser['id']]);
                $fetchedPrefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);
                if ($fetchedPrefs) {
                    $targetPrefs = $fetchedPrefs;
                }
            }
        } catch (\Throwable $e) {
            // Error silencioso
        }
    }
}
?>

<div class="view-content" id="admin-manage-user-view">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_admin" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <?php if (!$targetUser): ?>
            <div class="component-header-card">
                <h1 class="component-page-title" style="color: var(--color-error);">Usuario no encontrado</h1>
                <p class="component-page-description">El identificador proporcionado no coincide con ningún usuario registrado.</p>
                <div class="component-actions" style="margin-top: 16px; justify-content: center;">
                    <button class="component-button component-button--black" data-nav="/ProjectAurora/admin/users">Volver a la lista</button>
                </div>
            </div>
        <?php else: 
            // Preparar datos visuales Perfil
            $formattedAvatar = '/ProjectAurora/' . ltrim($targetUser['avatar_path'], '/');
            $isDefaultAvatar = (strpos($targetUser['avatar_path'], '/default/') !== false);
            $stateDefaultClass = $isDefaultAvatar ? 'active' : 'disabled';
            $stateCustomClass = $isDefaultAvatar ? 'disabled' : 'active';

            // Preparar datos visuales Preferencias
            $langLabels = [
                'en-us' => 'English (United States)', 'en-gb' => 'English (United Kingdom)',
                'fr-fr' => 'Français (France)', 'de-de' => 'Deutsch (Deutschland)',
                'it-it' => 'Italiano (Italia)', 'es-latam' => 'Español (Latinoamérica)',
                'es-mx' => 'Español (México)', 'es-es' => 'Español (España)',
                'pt-br' => 'Português (Brasil)', 'pt-pt' => 'Português (Portugal)'
            ];
            $currentLangLabel = $langLabels[$targetPrefs['language']] ?? 'English (United States)';

            $themeLabels = [
                'system' => t('settings.access.theme_system'),
                'light' => t('settings.access.theme_light'),
                'dark' => t('settings.access.theme_dark')
            ];
            $currentThemeLabel = $themeLabels[$targetPrefs['theme']] ?? t('settings.access.theme_system');
            
            $themeIcons = [
                'system' => 'settings_brightness',
                'light' => 'light_mode',
                'dark' => 'dark_mode'
            ];
            $currentThemeIcon = $themeIcons[$targetPrefs['theme']] ?? 'settings_brightness';

            $checkedLinksAttr = $targetPrefs['open_links_new_tab'] ? 'checked' : '';
            $checkedAlertsAttr = $targetPrefs['extended_alerts'] ? 'checked' : '';
        ?>
            <input type="hidden" id="admin-target-uuid" value="<?= htmlspecialchars($targetUser['uuid']) ?>">

            <div class="component-header-card" style="display: flex; align-items: center; justify-content: space-between; text-align: left;">
                <div>
                    <h1 class="component-page-title">Editando: <?= htmlspecialchars($targetUser['username']) ?></h1>
                    <p class="component-page-description">Administración directa de cuenta. Los cambios se aplican de inmediato.</p>
                </div>
                <button type="button" class="component-button component-button--square-40" data-tooltip="Volver a usuarios" data-nav="/ProjectAurora/admin/users">
                    <span class="material-symbols-rounded">arrow_back</span>
                </button>
            </div>

            <div class="component-card--grouped">
                <div class="component-group-item" data-component="admin-profile-picture-section">
                     <div class="component-card__content">
                        <div class="component-card__profile-picture" data-role="<?= htmlspecialchars($targetUser['role']); ?>">
                            <img src="<?= htmlspecialchars($formattedAvatar); ?>" data-original-src="<?= htmlspecialchars($formattedAvatar); ?>" class="component-card__avatar-image" id="admin-preview-avatar">
                            <div class="component-card__avatar-overlay" id="admin-btn-trigger-upload"><span class="material-symbols-rounded">photo_camera</span></div>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Foto de perfil</h2>
                            <p class="component-card__description">Se recomienda una imagen cuadrada de máximo 2MB (PNG o JPG).</p>
                        </div>
                    </div>
                    <input type="file" id="admin-upload-avatar" accept="image/png, image/jpeg, image/webp, image/gif" hidden>
                    <div class="component-card__actions actions-right">
                        <div class="component-action-group <?= $stateDefaultClass; ?>" data-state="admin-avatar-actions-default">
                            <button type="button" class="component-button primary" id="admin-btn-upload-init">Subir foto</button>
                        </div>
                        <div class="component-action-group disabled" data-state="admin-avatar-actions-preview">
                            <button type="button" class="component-button" data-action="admin-avatar-cancel">Cancelar</button>
                            <button type="button" class="component-button primary" data-action="admin-avatar-save">Guardar</button>
                        </div>
                        <div class="component-action-group <?= $stateCustomClass; ?>" data-state="admin-avatar-actions-custom">
                            <button type="button" class="component-button" data-action="admin-avatar-delete">Eliminar</button>
                            <button type="button" class="component-button primary" data-action="admin-avatar-change">Cambiar foto</button>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--wrap" data-component="admin-username-section">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Nombre de usuario</h2>
                            <div class="active" data-state="admin-username-view-state">
                                <span class="text-display-value" id="admin-display-username"><?= htmlspecialchars($targetUser['username']); ?></span>
                            </div>
                            <div class="disabled input-group-responsive" data-state="admin-username-edit-state">
                                <div class="component-input-wrapper">
                                    <input type="text" class="component-text-input component-text-input--simple" id="admin-input-username" value="<?= htmlspecialchars($targetUser['username']); ?>">
                                </div>
                                <div class="component-card__actions disabled" data-state="admin-username-actions-edit">
                                    <button type="button" class="component-button" data-action="admin-cancel-edit" data-target="username">Cancelar</button>
                                    <button type="button" class="component-button primary" data-action="admin-save-field" data-target="username">Guardar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right active" data-state="admin-username-actions-view">
                        <button type="button" class="component-button" data-action="admin-start-edit" data-target="username">Editar (Forzar)</button>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--wrap" data-component="admin-email-section">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Correo electrónico</h2>
                            <div class="active" data-state="admin-email-view-state">
                                <span class="text-display-value" id="admin-display-email"><?= htmlspecialchars($targetUser['email']); ?></span>
                            </div>
                            <div class="disabled input-group-responsive" data-state="admin-email-edit-state">
                                <div class="component-input-wrapper">
                                    <input type="email" class="component-text-input component-text-input--simple" id="admin-input-email" value="<?= htmlspecialchars($targetUser['email']); ?>">
                                </div>
                                <div class="component-card__actions disabled" data-state="admin-email-actions-edit">
                                    <button type="button" class="component-button" data-action="admin-cancel-edit" data-target="email">Cancelar</button>
                                    <button type="button" class="component-button primary" data-action="admin-save-field" data-target="email">Guardar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right active" data-state="admin-email-actions-view">
                        <button type="button" class="component-button" data-action="admin-start-edit" data-target="email">Editar (Forzar)</button>
                    </div>
                </div>
            </div>

            <div style="margin-top: 8px;">
                <h3 style="font-size: 16px; color: var(--text-primary); margin-left: 8px;">Preferencias del Usuario</h3>
            </div>

            <div class="component-card--grouped">
                
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= t('settings.account.lang') ?></h2>
                            <p class="component-card__description">Forzar el idioma de la interfaz de este usuario.</p>
                        </div>
                    </div>
                    <div class="component-card__actions">
                        <div class="component-dropdown" data-pref-key="language">
                            <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                <span class="material-symbols-rounded trigger-select-icon">language</span>
                                <span class="component-dropdown-text"><?= htmlspecialchars($currentLangLabel) ?></span>
                                <span class="material-symbols-rounded">expand_more</span>
                            </div>
                            
                            <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                                <div class="component-module-panel">
                                    <div class="pill-container"><div class="drag-handle"></div></div>
                                    <div class="component-module-panel-header--search">
                                        <div class="component-input-wrapper component-input-wrapper--search">
                                            <input type="text" class="component-text-input component-text-input--simple" placeholder="<?= t('settings.account.search') ?>" data-action="admin-filter-options"> 
                                        </div>
                                    </div>
                                    <div class="component-module-panel-body component-module-panel-body--padded">
                                        <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'en-us' ? 'active' : '' ?>" data-action="admin-select-option" data-value="en-us" data-label="English (United States)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>English (United States)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'en-gb' ? 'active' : '' ?>" data-action="admin-select-option" data-value="en-gb" data-label="English (United Kingdom)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>English (United Kingdom)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'fr-fr' ? 'active' : '' ?>" data-action="admin-select-option" data-value="fr-fr" data-label="Français (France)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Français (France)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'de-de' ? 'active' : '' ?>" data-action="admin-select-option" data-value="de-de" data-label="Deutsch (Deutschland)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Deutsch (Deutschland)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'it-it' ? 'active' : '' ?>" data-action="admin-select-option" data-value="it-it" data-label="Italiano (Italia)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Italiano (Italia)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'es-latam' ? 'active' : '' ?>" data-action="admin-select-option" data-value="es-latam" data-label="Español (Latinoamérica)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (Latinoamérica)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'es-mx' ? 'active' : '' ?>" data-action="admin-select-option" data-value="es-mx" data-label="Español (México)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (México)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'es-es' ? 'active' : '' ?>" data-action="admin-select-option" data-value="es-es" data-label="Español (España)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Español (España)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'pt-br' ? 'active' : '' ?>" data-action="admin-select-option" data-value="pt-br" data-label="Português (Brasil)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Português (Brasil)</span></div></div>
                                            <div class="component-menu-link <?= $targetPrefs['language'] === 'pt-pt' ? 'active' : '' ?>" data-action="admin-select-option" data-value="pt-pt" data-label="Português (Portugal)"><div class="component-menu-link-icon"><span class="material-symbols-rounded">language</span></div><div class="component-menu-link-text"><span>Português (Portugal)</span></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--wrap">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= t('settings.account.links') ?></h2>
                            <p class="component-card__description">Los enlaces externos se abrirán en una nueva pestaña del navegador.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="admin-pref-open-links" <?= $checkedLinksAttr ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= t('settings.access.theme') ?></h2>
                            <p class="component-card__description">Fuerza el aspecto de la plataforma para este usuario.</p>
                        </div>
                    </div>
                    <div class="component-card__actions">
                        <div class="component-dropdown" data-pref-key="theme">
                            <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                <span class="material-symbols-rounded trigger-select-icon"><?= $currentThemeIcon ?></span>
                                <span class="component-dropdown-text"><?= htmlspecialchars($currentThemeLabel) ?></span>
                                <span class="material-symbols-rounded">expand_more</span>
                            </div>
                            
                            <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                                <div class="component-module-panel">
                                    <div class="pill-container"><div class="drag-handle"></div></div>
                                    <div class="component-module-panel-body component-module-panel-body--padded">
                                        <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                            <div class="component-menu-link <?= $targetPrefs['theme'] === 'system' ? 'active' : '' ?>" data-action="admin-select-option" data-value="system" data-label="<?= t('settings.access.theme_system') ?>">
                                                <div class="component-menu-link-icon"><span class="material-symbols-rounded">settings_brightness</span></div>
                                                <div class="component-menu-link-text"><span><?= t('settings.access.theme_system') ?></span></div>
                                            </div>
                                            <div class="component-menu-link <?= $targetPrefs['theme'] === 'light' ? 'active' : '' ?>" data-action="admin-select-option" data-value="light" data-label="<?= t('settings.access.theme_light') ?>">
                                                <div class="component-menu-link-icon"><span class="material-symbols-rounded">light_mode</span></div>
                                                <div class="component-menu-link-text"><span><?= t('settings.access.theme_light') ?></span></div>
                                            </div>
                                            <div class="component-menu-link <?= $targetPrefs['theme'] === 'dark' ? 'active' : '' ?>" data-action="admin-select-option" data-value="dark" data-label="<?= t('settings.access.theme_dark') ?>">
                                                <div class="component-menu-link-icon"><span class="material-symbols-rounded">dark_mode</span></div>
                                                <div class="component-menu-link-text"><span><?= t('settings.access.theme_dark') ?></span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item component-group-item--wrap">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= t('settings.access.alerts') ?></h2>
                            <p class="component-card__description">Las notificaciones Toast se mostrarán por 5 segundos antes de ocultarse.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="admin-pref-extended-alerts" <?= $checkedAlertsAttr ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

            </div>

        <?php endif; ?>
    </div>
</div>