<?php
// includes/views/admin/admin-manage-status.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad de la vista: solo administradores
$userRoleSession = $_SESSION['user_role'] ?? 'user';
if (!in_array($userRoleSession, ['administrator', 'founder'])) {
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
            $stmt = $dbConnection->prepare("SELECT id, uuid, username, email, role, status, is_suspended, suspension_type, suspension_expires_at, suspension_reason, deletion_type, deletion_reason FROM users WHERE uuid = :uuid LIMIT 1");
            $stmt->execute([':uuid' => $targetUuid]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Error silencioso
        }
    }
}
?>

<style>
    .cascade-section {
        display: flex;
        flex-direction: column;
        gap: 16px;
        width: 100%;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px dashed var(--divider-color);
    }
    .cascade-section.disabled {
        display: none;
    }
    .status-textarea {
        width: 100%;
        min-height: 80px;
        resize: vertical;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--bg-input);
        color: var(--text-primary);
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s ease;
    }
    .status-textarea:focus {
        border-color: var(--border-color-hover);
    }
    .status-select {
        width: 100%;
        height: 44px;
        padding: 0 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--bg-input);
        color: var(--text-primary);
        font-size: 14px;
        outline: none;
        cursor: pointer;
    }
</style>

<div class="view-content" id="admin-manage-status-view">
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
            // Preparación de variables actuales
            $isDeleted = ($targetUser['status'] === 'deleted');
            $isSuspended = ($targetUser['is_suspended'] == 1);
            $suspensionType = $targetUser['suspension_type'] ?? 'temporal';
            
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
                    
                    <div style="width: 100%; display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
                        <label style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Estado de Existencia</label>
                        <select id="select-lifecycle-status" class="status-select">
                            <option value="active" <?= !$isDeleted ? 'selected' : '' ?>>Cuenta Activa (Existente)</option>
                            <option value="deleted" <?= $isDeleted ? 'selected' : '' ?>>Cuenta Eliminada</option>
                        </select>
                    </div>

                    <div id="cascade-deletion-data" class="cascade-section <?= !$isDeleted ? 'disabled' : '' ?>">
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Tipo de Eliminación</label>
                            <select id="select-deletion-type" class="status-select">
                                <option value="admin_banned" <?= ($targetUser['deletion_type'] === 'admin_banned' || empty($targetUser['deletion_type'])) ? 'selected' : '' ?>>Decisión Administrativa (Baneo Definitivo)</option>
                                <option value="user_requested" <?= ($targetUser['deletion_type'] === 'user_requested') ? 'selected' : '' ?>>Solicitado por el Usuario</option>
                            </select>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Razón / Notas de Eliminación (Opcional)</label>
                            <textarea id="input-deletion-reason" class="status-textarea" placeholder="Ej. El usuario infringió los términos de servicio gravemente."><?= htmlspecialchars($targetUser['deletion_reason'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="component-card--grouped <?= $isDeleted ? 'disabled' : '' ?>" id="card-suspension-control" style="margin-top: 16px;">
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

                    <div id="cascade-suspension-data" class="cascade-section <?= !$isSuspended ? 'disabled' : '' ?>">
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Tipo de Suspensión</label>
                            <select id="select-suspension-type" class="status-select">
                                <option value="temporal" <?= ($suspensionType === 'temporal') ? 'selected' : '' ?>>Temporal</option>
                                <option value="permanent" <?= ($suspensionType === 'permanent') ? 'selected' : '' ?>>Permanente</option>
                            </select>
                        </div>

                        <div id="cascade-suspension-date" style="display: flex; flex-direction: column; gap: 8px; <?= ($suspensionType === 'permanent') ? 'display: none;' : '' ?>">
                            <label style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Finaliza el (Fecha y Hora)</label>
                            <div class="component-input-wrapper">
                                <input type="datetime-local" id="input-suspension-date" class="component-text-input component-text-input--simple" value="<?= $suspensionExpiresAt ?>">
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Motivo de la suspensión (Visible para el usuario / Opcional)</label>
                            <textarea id="input-suspension-reason" class="status-textarea" placeholder="Ej. Spam repetitivo en el foro."><?= htmlspecialchars($targetUser['suspension_reason'] ?? '') ?></textarea>
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