<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$userName = $_SESSION['user_name'] ?? 'Usuario';
$userAvatar = $_SESSION['user_avatar'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'user';
$userEmail = $_SESSION['user_email'] ?? ''; 
$formattedAvatar = '/ProjectAurora/' . ltrim($userAvatar, '/');
$isDefaultAvatar = (strpos($userAvatar, '/default/') !== false);
$stateDefaultClass = $isDefaultAvatar ? 'active' : 'disabled';
$stateCustomClass = $isDefaultAvatar ? 'disabled' : 'active';
?>
<div class="view-content">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.account.title') ?></h1>
            <p class="component-page-description"><?= t('settings.account.desc') ?></p>
        </div>
        <div class="component-card--grouped">
            <div class="component-group-item" data-component="profile-picture-section">
                 <div class="component-card__content">
                    <div class="component-card__profile-picture" data-role="<?= htmlspecialchars($userRole); ?>">
                        <img src="<?= htmlspecialchars($formattedAvatar); ?>" data-original-src="<?= htmlspecialchars($formattedAvatar); ?>" class="component-card__avatar-image" id="preview-avatar">
                        <div class="component-card__avatar-overlay" id="btn-trigger-upload"><span class="material-symbols-rounded">photo_camera</span></div>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.account.avatar') ?></h2>
                        <p class="component-card__description"><?= t('settings.account.avatar_desc') ?></p>
                    </div>
                </div>
                <input type="file" id="upload-avatar" accept="image/png, image/jpeg, image/webp, image/gif" hidden>
                <div class="component-card__actions actions-right">
                    <div class="component-action-group <?= $stateDefaultClass; ?>" data-state="profile-picture-actions-default">
                        <button type="button" class="component-button primary" id="btn-upload-init"><?= t('settings.account.upload') ?></button>
                    </div>
                    <div class="component-action-group disabled" data-state="profile-picture-actions-preview">
                        <button type="button" class="component-button" data-action="profile-picture-cancel"><?= t('settings.account.cancel') ?></button>
                        <button type="button" class="component-button primary" data-action="profile-picture-save"><?= t('settings.account.save') ?></button>
                    </div>
                    <div class="component-action-group <?= $stateCustomClass; ?>" data-state="profile-picture-actions-custom">
                        <button type="button" class="component-button" data-action="profile-picture-delete" style="color: #d32f2f; border-color: #d32f2f30;"><?= t('settings.account.delete') ?></button>
                        <button type="button" class="component-button primary" data-action="profile-picture-change"><?= t('settings.account.change') ?></button>
                    </div>
                </div>
            </div>
            <hr class="component-divider">
            <div class="component-group-item" data-component="username-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.account.username') ?></h2>
                        <div class="active" data-state="username-view-state"><span class="text-display-value" id="display-username"><?= htmlspecialchars($userName); ?></span></div>
                        <div class="disabled w-100 input-group-responsive" data-state="username-edit-state">
                            <div class="component-input-wrapper flex-1" style="height: 36px;">
                                <input type="text" class="component-text-input component-text-input--simple" id="input-username" value="<?= htmlspecialchars($userName); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="username-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="username"><?= t('settings.account.cancel') ?></button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="username"><?= t('settings.account.save') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="username-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="username"><?= t('settings.account.edit') ?></button>
                </div>
            </div>
            <hr class="component-divider">
            <div class="component-group-item" data-component="email-section">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.account.email') ?></h2>
                        <div class="active" data-state="email-view-state"><span class="text-display-value" id="display-email"><?= htmlspecialchars($userEmail); ?></span></div>
                        <div class="disabled w-100 input-group-responsive" data-state="email-edit-state">
                            <div class="component-input-wrapper flex-1" style="height: 36px;">
                                <input type="email" class="component-text-input component-text-input--simple" id="input-email" value="<?= htmlspecialchars($userEmail); ?>">
                            </div>
                            <div class="component-card__actions disabled m-0" data-state="email-actions-edit">
                                <button type="button" class="component-button" data-action="cancel-edit" data-target="email"><?= t('settings.account.cancel') ?></button>
                                <button type="button" class="component-button primary" data-action="save-field" data-target="email"><?= t('settings.account.save') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-card__actions actions-right active" data-state="email-actions-view">
                    <button type="button" class="component-button" data-action="start-edit" data-target="email"><?= t('settings.account.edit') ?></button>
                </div>
            </div>
        </div>
        <div class="component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= t('settings.account.lang') ?></h2>
                        <p class="component-card__description"><?= t('settings.account.lang_desc') ?></p>
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
                        <h2 class="component-card__title"><?= t('settings.account.links') ?></h2>
                        <p class="component-card__description"><?= t('settings.account.links_desc') ?></p>
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

    <div class="component-dialog-overlay" id="dialog-delete-avatar">
        <div class="component-dialog-box">
            <div class="component-dialog-header">
                <h3 class="component-dialog-title">Eliminar foto de perfil</h3>
                <button class="component-dialog-close" data-action="close-dialog" data-target="dialog-delete-avatar"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="component-dialog-body">
                ¿Estás seguro de que deseas eliminar tu foto de perfil? Se te asignará una nueva generada automáticamente en base a tu nombre.
            </div>
            <div class="component-dialog-footer">
                <button class="component-button" data-action="close-dialog" data-target="dialog-delete-avatar">Cancelar</button>
                <button class="component-button primary" id="btn-confirm-delete-avatar" style="background-color: #d32f2f; border-color: #d32f2f;">Eliminar</button>
            </div>
        </div>
    </div>

    <div class="component-dialog-overlay" id="dialog-verify-email">
        <div class="component-dialog-box">
            <div class="component-dialog-header">
                <h3 class="component-dialog-title">Verificar cambio de correo</h3>
                <button class="component-dialog-close" data-action="close-dialog" data-target="dialog-verify-email"><span class="material-symbols-rounded">close</span></button>
            </div>
            <div class="component-dialog-body">
                Hemos enviado un código de seguridad a tu <b>correo actual</b>. Ingrésalo para autorizar el cambio a tu nuevo correo.
                <div class="component-input-wrapper" style="margin-top: 16px;">
                    <input type="text" id="input-email-code" class="component-text-input" placeholder=" " maxlength="6" style="letter-spacing: 4px; text-align: center; font-weight: bold; font-size: 18px;">
                    <label for="input-email-code" class="component-label-floating" style="left: 12px; transform: translateY(-50%); width: 100%; text-align: center;">Código de 6 dígitos</label>
                </div>
            </div>
            <div class="component-dialog-footer">
                <button class="component-button" data-action="close-dialog" data-target="dialog-verify-email">Cancelar</button>
                <button class="component-button primary" id="btn-confirm-email-code">Verificar y Guardar</button>
            </div>
        </div>
    </div>
</div>