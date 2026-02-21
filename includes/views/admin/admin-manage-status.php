<?php
// includes/views/admin/admin-manage-status.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad de la vista: solo administradores
$userRoleSession = $_SESSION['user_role'] ?? 'user';
if (!in_array($userRoleSession, ['administrator', 'founder'])) {
    http_response_code(403);
    echo "<div class='view-content'><div class='component-wrapper'><h1 style='color: #d32f2f; text-align: center;'>Acceso denegado</h1></div></div>";
    exit;
}

$targetUuid = $_GET['uuid'] ?? '';
$targetUser = null;

if (!empty($targetUuid)) {
    global $dbConnection;
    if (isset($dbConnection)) {
        try {
            $stmt = $dbConnection->prepare("SELECT id, uuid, username, email, role, status, is_suspended, suspension_type, suspension_expires_at, suspension_reason, deletion_type, deletion_reason FROM users WHERE uuid = :uuid LIMIT 1");
            $stmt->execute([':uuid' => $targetUuid]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Error silencioso
        }
    }
}
?>

<div class="view-content" id="admin-manage-status-view">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_admin" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <?php if (!$targetUser): ?>
            <div class="component-header-card">
                <h1 class="component-page-title" style="color: #d32f2f;">Usuario no encontrado</h1>
                <p class="component-page-description">El identificador proporcionado no coincide con ningún usuario registrado.</p>
                <div class="component-actions" style="margin-top: 16px; justify-content: center;">
                    <button class="component-button component-button--black" data-nav="/ProjectAurora/admin/users">Volver a la lista</button>
                </div>
            </div>
        <?php else: 
            // Preparación de variables actuales
            $isDeleted = ($targetUser['status'] === 'deleted');
            $isSuspended = ($targetUser['is_suspended'] == 1);
            $suspensionType = $targetUser['suspension_type'] ?? 'temporal';
            $deletionType = $targetUser['deletion_type'] ?? 'admin_banned';
            
            // Formatear fecha para el input datetime-local (YYYY-MM-DDTHH:MM)
            $suspensionExpiresAt = '';
            if (!empty($targetUser['suspension_expires_at'])) {
                $d = new DateTime($targetUser['suspension_expires_at']);
                $suspensionExpiresAt = $d->format('Y-m-d\TH:i');
            }
        ?>
            <input type="hidden" id="admin-target-uuid" value="<?= htmlspecialchars($targetUser['uuid']) ?>">

            <div class="component-header-card">
                <h1 class="component-page-title">Estado y Acceso: <?= htmlspecialchars($targetUser['username']) ?></h1>
                <p class="component-page-description">Administra el ciclo de vida de la cuenta y los bloqueos de acceso.</p>
            </div>

            <div class="component-card--grouped">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">power_settings_new</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Ciclo de vida de la cuenta</h2>
                            <p class="component-card__description">Define si la cuenta existe operativamente en el sistema.</p>
                        </div>
                    </div>
                    
                    <div style="width: 100%; display: flex; flex-direction: column; gap: 12px; margin-top: 8px;">
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: #666666; font-weight: 500;">Estado de Existencia</label>
                            
                            <div class="component-dropdown" id="dropdown-lifecycle-status" data-value="<?= $isDeleted ? 'deleted' : 'active' ?>" style="max-width: 100%;">
                                <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                    <span class="material-symbols-rounded trigger-select-icon"><?= $isDeleted ? 'delete_forever' : 'check_circle' ?></span>
                                    <span class="component-dropdown-text"><?= $isDeleted ? 'Cuenta Eliminada' : 'Cuenta Activa (Existente)' ?></span>
                                    <span class="material-symbols-rounded">expand_more</span>
                                </div>
                                <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                                    <div class="component-module-panel">
                                        <div class="pill-container"><div class="drag-handle"></div></div>
                                        <div class="component-module-panel-body component-module-panel-body--padded">
                                            <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                                <div class="component-menu-link <?= !$isDeleted ? 'active' : '' ?>" data-action="status-select-option" data-target="lifecycle" data-value="active" data-label="Cuenta Activa (Existente)">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">check_circle</span></div>
                                                    <div class="component-menu-link-text"><span>Cuenta Activa (Existente)</span></div>
                                                </div>
                                                <div class="component-menu-link <?= $isDeleted ? 'active' : '' ?>" data-action="status-select-option" data-target="lifecycle" data-value="deleted" data-label="Cuenta Eliminada">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">delete_forever</span></div>
                                                    <div class="component-menu-link-text"><span>Cuenta Eliminada</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div id="cascade-deletion-data" class="<?= !$isDeleted ? 'disabled' : 'active' ?>" data-state="cascade-deletion" style="flex-direction: column; width: 100%; gap: 16px; margin-top: 8px;">
                            <hr class="component-divider" style="margin: 0;">
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <label style="font-size: 13px; color: #666666; font-weight: 500;">Tipo de Eliminación</label>
                                
                                <div class="component-dropdown" id="dropdown-deletion-type" data-value="<?= htmlspecialchars($deletionType) ?>" style="max-width: 100%;">
                                    <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                        <span class="material-symbols-rounded trigger-select-icon"><?= $deletionType === 'user_requested' ? 'person_remove' : 'gavel' ?></span>
                                        <span class="component-dropdown-text"><?= $deletionType === 'user_requested' ? 'Solicitado por el Usuario' : 'Decisión Administrativa (Baneo Definitivo)' ?></span>
                                        <span class="material-symbols-rounded">expand_more</span>
                                    </div>
                                    <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                                        <div class="component-module-panel">
                                            <div class="pill-container"><div class="drag-handle"></div></div>
                                            <div class="component-module-panel-body component-module-panel-body--padded">
                                                <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                                    <div class="component-menu-link <?= $deletionType === 'admin_banned' ? 'active' : '' ?>" data-action="status-select-option" data-target="deletion-type" data-value="admin_banned" data-label="Decisión Administrativa (Baneo Definitivo)">
                                                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">gavel</span></div>
                                                        <div class="component-menu-link-text"><span>Decisión Administrativa (Baneo Definitivo)</span></div>
                                                    </div>
                                                    <div class="component-menu-link <?= $deletionType === 'user_requested' ? 'active' : '' ?>" data-action="status-select-option" data-target="deletion-type" data-value="user_requested" data-label="Solicitado por el Usuario">
                                                        <div class="component-menu-link-icon"><span class="material-symbols-rounded">person_remove</span></div>
                                                        <div class="component-menu-link-text"><span>Solicitado por el Usuario</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <label style="font-size: 13px; color: #666666; font-weight: 500;">Razón / Notas de Eliminación (Opcional)</label>
                                <div class="component-input-wrapper" style="height: auto; min-height: 80px;">
                                    <textarea id="input-deletion-reason" class="component-text-input component-text-input--simple" placeholder="Ej. El usuario infringió los términos de servicio gravemente." style="resize: vertical; padding: 12px; height: 100%; min-height: 80px; font-size: 14px; outline: none; border: none; background: transparent;"><?= htmlspecialchars($targetUser['deletion_reason'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="component-card--grouped <?= $isDeleted ? 'disabled' : 'active' ?>" id="card-suspension-control" data-state="suspension-card" style="margin-top: 16px;">
                <div class="component-group-item component-group-item--stacked">
                    
                    <div class="component-card__content" style="width: 100%; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded">block</span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title">Suspensión de Acceso</h2>
                                <p class="component-card__description">Impide que el usuario inicie sesión en la plataforma.</p>
                            </div>
                        </div>
                        <div class="component-card__actions actions-right">
                            <label class="component-toggle-switch">
                                <input type="checkbox" id="toggle-is-suspended" <?= $isSuspended ? 'checked' : '' ?>>
                                <span class="component-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div id="cascade-suspension-data" class="<?= !$isSuspended ? 'disabled' : 'active' ?>" data-state="cascade-suspension" style="flex-direction: column; width: 100%; gap: 16px; margin-top: 8px;">
                        <hr class="component-divider" style="margin: 0;">
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: #666666; font-weight: 500;">Tipo de Suspensión</label>
                            
                            <div class="component-dropdown" id="dropdown-suspension-type" data-value="<?= htmlspecialchars($suspensionType) ?>" style="max-width: 100%;">
                                <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                    <span class="material-symbols-rounded trigger-select-icon"><?= $suspensionType === 'permanent' ? 'lock' : 'schedule' ?></span>
                                    <span class="component-dropdown-text"><?= $suspensionType === 'permanent' ? 'Permanente' : 'Temporal' ?></span>
                                    <span class="material-symbols-rounded">expand_more</span>
                                </div>
                                <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                                    <div class="component-module-panel">
                                        <div class="pill-container"><div class="drag-handle"></div></div>
                                        <div class="component-module-panel-body component-module-panel-body--padded">
                                            <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                                <div class="component-menu-link <?= $suspensionType === 'temporal' ? 'active' : '' ?>" data-action="status-select-option" data-target="suspension-type" data-value="temporal" data-label="Temporal">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">schedule</span></div>
                                                    <div class="component-menu-link-text"><span>Temporal</span></div>
                                                </div>
                                                <div class="component-menu-link <?= $suspensionType === 'permanent' ? 'active' : '' ?>" data-action="status-select-option" data-target="suspension-type" data-value="permanent" data-label="Permanente">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">lock</span></div>
                                                    <div class="component-menu-link-text"><span>Permanente</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="cascade-suspension-date" style="display: <?= ($suspensionType === 'permanent') ? 'none' : 'flex' ?>; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: #666666; font-weight: 500;">Finaliza el (Fecha y Hora)</label>
                            <div class="component-input-wrapper">
                                <input type="datetime-local" id="input-suspension-date" class="component-text-input component-text-input--simple" value="<?= $suspensionExpiresAt ?>" style="padding: 0 12px; font-family: inherit;">
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: #666666; font-weight: 500;">Motivo de la suspensión (Visible para el usuario / Opcional)</label>
                            <div class="component-input-wrapper" style="height: auto; min-height: 80px;">
                                <textarea id="input-suspension-reason" class="component-text-input component-text-input--simple" placeholder="Ej. Spam repetitivo en el foro." style="resize: vertical; padding: 12px; height: 100%; min-height: 80px; font-size: 14px; outline: none; border: none; background: transparent;"><?= htmlspecialchars($targetUser['suspension_reason'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="component-actions" style="margin-top: 24px; justify-content: flex-end;">
                <button type="button" class="component-button primary component-button--large" style="max-width: 250px;" id="btn-save-status-changes">
                    Guardar Cambios
                </button>
            </div>

        <?php endif; ?>
    </div>
</div>