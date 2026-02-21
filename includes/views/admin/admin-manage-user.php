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

if (!empty($targetUuid)) {
    global $dbConnection;
    if (isset($dbConnection)) {
        try {
            $stmt = $dbConnection->prepare("SELECT id, uuid, username, email, avatar_path, role, status FROM users WHERE uuid = :uuid LIMIT 1");
            $stmt->execute([':uuid' => $targetUuid]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
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
            // Preparar datos visuales
            $formattedAvatar = '/ProjectAurora/' . ltrim($targetUser['avatar_path'], '/');
            $isDefaultAvatar = (strpos($targetUser['avatar_path'], '/default/') !== false);
            $stateDefaultClass = $isDefaultAvatar ? 'active' : 'disabled';
            $stateCustomClass = $isDefaultAvatar ? 'disabled' : 'active';
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
        <?php endif; ?>
    </div>
</div>