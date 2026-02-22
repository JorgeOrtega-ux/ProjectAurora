<?php
// includes/views/admin/admin-manage-status.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad de la vista: solo administradores
$userRoleSession = $_SESSION['user_role'] ?? 'user';
if (!in_array($userRoleSession, ['administrator', 'founder'])) {
    http_response_code(403);
    echo "<div class='view-content'><div class='component-wrapper'><div class='component-header-card'><h1 class='component-page-title u-text-error'>Acceso denegado</h1></div></div></div>";
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
                <h1 class="component-page-title u-text-error">Usuario no encontrado</h1>
                <p class="component-page-description">El identificador proporcionado no coincide con ningún usuario registrado.</p>
                <div class="component-actions u-flex-center u-mt-16">
                    <button class="component-button component-button--black" data-nav="/ProjectAurora/admin/users">Volver a la lista</button>
                </div>
            </div>
        <?php else: 
            $isDeleted = ($targetUser['status'] === 'deleted');
            $isSuspended = ($targetUser['is_suspended'] == 1);
            $suspensionType = $targetUser['suspension_type'] ?? 'temporal';
            $deletionType = $targetUser['deletion_type'] ?? 'admin_banned';
            
            $displaySuspensionDate = 'Seleccionar fecha y hora';
            $suspensionExpiresAt = '';
            
            if (!empty($targetUser['suspension_expires_at'])) {
                $d = new DateTime($targetUser['suspension_expires_at']);
                $suspensionExpiresAt = $d->format('Y-m-d H:i:s');
                $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                $mes = $meses[(int)$d->format('n') - 1];
                $displaySuspensionDate = $d->format('d') . ' ' . $mes . ' ' . $d->format('Y, H:i:s');
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
                            <h2 class="component-card__title">Estado de Existencia</h2>
                            <p class="component-card__description">Define si la cuenta existe de manera operativa en el sistema o si está marcada como eliminada.</p>
                        </div>
                    </div>
                    <div class="component-card__actions">
                        <div class="component-dropdown" id="dropdown-lifecycle-status" data-value="<?= $isDeleted ? 'deleted' : 'active' ?>">
                            <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                <span class="material-symbols-rounded trigger-select-icon"><?= $isDeleted ? 'delete_forever' : 'check_circle' ?></span>
                                <span class="component-dropdown-text"><?= $isDeleted ? 'Cuenta Eliminada' : 'Cuenta Activa' ?></span>
                                <span class="material-symbols-rounded">expand_more</span>
                            </div>
                            <div class="component-module component-module--display-overlay component-module--size-m component-module--dropdown-selector disabled">
                                <div class="component-module-panel">
                                    <div class="pill-container"><div class="drag-handle"></div></div>
                                    <div class="component-module-panel-body component-module-panel-body--padded">
                                        <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                            <div class="component-menu-link <?= !$isDeleted ? 'active' : '' ?>" data-action="status-select-option" data-target="lifecycle" data-value="active" data-label="Cuenta Activa">
                                                <div class="component-menu-link-icon"><span class="material-symbols-rounded">check_circle</span></div>
                                                <div class="component-menu-link-text"><span>Cuenta Activa</span></div>
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
                </div>

                <div id="cascade-deletion-data" class="component-stage-form <?= !$isDeleted ? 'disabled' : 'active' ?>" data-state="cascade-deletion">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content">
                            <div class="component-card__text">
                                <h2 class="component-card__title">Tipo de Eliminación</h2>
                                <p class="component-card__description">Clasificación formal de la eliminación de la cuenta en el sistema.</p>
                            </div>
                        </div>
                        <div class="component-card__actions">
                            <div class="component-dropdown" id="dropdown-deletion-type" data-value="<?= htmlspecialchars($deletionType) ?>">
                                <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                    <span class="material-symbols-rounded trigger-select-icon"><?= $deletionType === 'user_requested' ? 'person_remove' : 'gavel' ?></span>
                                    <span class="component-dropdown-text"><?= $deletionType === 'user_requested' ? 'Solicitado por el Usuario' : 'Decisión Administrativa' ?></span>
                                    <span class="material-symbols-rounded">expand_more</span>
                                </div>
                                <div class="component-module component-module--display-overlay component-module--size-m component-module--dropdown-selector disabled">
                                    <div class="component-module-panel">
                                        <div class="pill-container"><div class="drag-handle"></div></div>
                                        <div class="component-module-panel-body component-module-panel-body--padded">
                                            <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                                <div class="component-menu-link <?= $deletionType === 'admin_banned' ? 'active' : '' ?>" data-action="status-select-option" data-target="deletion-type" data-value="admin_banned" data-label="Decisión Administrativa">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">gavel</span></div>
                                                    <div class="component-menu-link-text"><span>Decisión Administrativa</span></div>
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
                    </div>
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content">
                            <div class="component-card__text">
                                <h2 class="component-card__title">Notas de Eliminación</h2>
                                <p class="component-card__description">Razón adicional, justificación o comentarios internos sobre la eliminación.</p>
                            </div>
                        </div>
                        <div class="component-card__actions u-w-100">
                            <div class="component-input-wrapper component-input-wrapper--textarea u-w-100">
                                <textarea id="input-deletion-reason" class="component-textarea" placeholder="Ej. Infracción grave de los términos de servicio..."><?= htmlspecialchars($targetUser['deletion_reason'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="component-card--grouped u-mt-16 <?= $isDeleted ? 'disabled' : 'active' ?>" id="card-suspension-control" data-state="suspension-card">
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">block</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Suspensión de Acceso</h2>
                            <p class="component-card__description">Impide temporal o permanentemente que el usuario inicie sesión en la plataforma.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="toggle-is-suspended" <?= $isSuspended ? 'checked' : '' ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div id="cascade-suspension-data" class="component-stage-form <?= !$isSuspended ? 'disabled' : 'active' ?>" data-state="cascade-suspension">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content">
                            <div class="component-card__text">
                                <h2 class="component-card__title">Tipo de Suspensión</h2>
                                <p class="component-card__description">Determina la duración y severidad del bloqueo de acceso.</p>
                            </div>
                        </div>
                        <div class="component-card__actions">
                            <div class="component-dropdown" id="dropdown-suspension-type" data-value="<?= htmlspecialchars($suspensionType) ?>">
                                <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                    <span class="material-symbols-rounded trigger-select-icon"><?= $suspensionType === 'permanent' ? 'lock' : 'schedule' ?></span>
                                    <span class="component-dropdown-text"><?= $suspensionType === 'permanent' ? 'Permanente' : 'Temporal' ?></span>
                                    <span class="material-symbols-rounded">expand_more</span>
                                </div>
                                <div class="component-module component-module--display-overlay component-module--size-m component-module--dropdown-selector disabled">
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
                    </div>

                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content">
                            <div class="component-card__text">
                                <h2 class="component-card__title"><?= t('admin.suspension.category_title') ?? 'Motivo de la Infracción' ?></h2>
                                <p class="component-card__description"><?= t('admin.suspension.category_desc') ?? 'Selecciona la falta cometida para aplicar una sanción automática.' ?></p>
                            </div>
                        </div>
                        <div class="component-card__actions">
                            <div class="component-dropdown" id="dropdown-suspension-category" data-value="other">
                                <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                    <span class="material-symbols-rounded trigger-select-icon">edit_calendar</span>
                                    <span class="component-dropdown-text"><?= t('admin.suspension.cat_other') ?? 'Otro (Especificar fecha manual)' ?></span>
                                    <span class="material-symbols-rounded">expand_more</span>
                                </div>
                                <div class="component-module component-module--display-overlay component-module--size-l component-module--dropdown-selector disabled">
                                    <div class="component-module-panel">
                                        <div class="pill-container"><div class="drag-handle"></div></div>
                                        <div class="component-module-panel-body component-module-panel-body--padded">
                                            <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                                <div class="component-menu-link" data-action="status-select-option" data-target="suspension-category" data-value="cat_1" data-label="<?= t('admin.suspension.cat_1') ?? 'Spam o Publicidad no deseada' ?>">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">campaign</span></div>
                                                    <div class="component-menu-link-text"><span><?= t('admin.suspension.cat_1') ?? 'Spam o Publicidad no deseada' ?></span></div>
                                                </div>
                                                <div class="component-menu-link" data-action="status-select-option" data-target="suspension-category" data-value="cat_2" data-label="<?= t('admin.suspension.cat_2') ?? 'Lenguaje inapropiado o Insultos' ?>">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">gavel</span></div>
                                                    <div class="component-menu-link-text"><span><?= t('admin.suspension.cat_2') ?? 'Lenguaje inapropiado o Insultos' ?></span></div>
                                                </div>
                                                <div class="component-menu-link" data-action="status-select-option" data-target="suspension-category" data-value="cat_3" data-label="<?= t('admin.suspension.cat_3') ?? 'Comportamiento Tóxico o Acoso' ?>">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">block</span></div>
                                                    <div class="component-menu-link-text"><span><?= t('admin.suspension.cat_3') ?? 'Comportamiento Tóxico o Acoso' ?></span></div>
                                                </div>
                                                <div class="component-menu-link" data-action="status-select-option" data-target="suspension-category" data-value="cat_4" data-label="<?= t('admin.suspension.cat_4') ?? 'Evasión de Ban / Multicuentas' ?>">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">group_remove</span></div>
                                                    <div class="component-menu-link-text"><span><?= t('admin.suspension.cat_4') ?? 'Evasión de Ban / Multicuentas' ?></span></div>
                                                </div>
                                                <div class="component-menu-link" data-action="status-select-option" data-target="suspension-category" data-value="cat_5" data-label="<?= t('admin.suspension.cat_5') ?? 'Fraude o Actividad Ilegal' ?>">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">policy</span></div>
                                                    <div class="component-menu-link-text"><span><?= t('admin.suspension.cat_5') ?? 'Fraude o Actividad Ilegal' ?></span></div>
                                                </div>
                                                <div class="component-menu-link active" data-action="status-select-option" data-target="suspension-category" data-value="other" data-label="<?= t('admin.suspension.cat_other') ?? 'Otro (Especificar fecha manual)' ?>">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">edit_calendar</span></div>
                                                    <div class="component-menu-link-text"><span><?= t('admin.suspension.cat_other') ?? 'Otro (Especificar fecha manual)' ?></span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="cascade-suspension-duration" class="component-stage-form disabled" data-state="suspension-duration">
                        <hr class="component-divider">
                        <div class="component-group-item component-group-item--wrap">
                            <div class="component-card__content">
                                <div class="component-card__icon-container component-card__icon-container--bordered">
                                    <span class="material-symbols-rounded">timer</span>
                                </div>
                                <div class="component-card__text">
                                    <h2 class="component-card__title">Duración de la Sanción</h2>
                                    <p class="component-card__description">Tiempo asignado automáticamente por el sistema.</p>
                                </div>
                            </div>
                            <div class="component-card__actions actions-right">
                                <span class="component-badge" style="font-size: 15px; font-weight: 600; color: var(--color-error); border-color: var(--color-error); padding: 8px 16px;">
                                    <span id="display-suspension-days" style="margin-right: 4px;">0</span> Días
                                </span>
                            </div>
                        </div>
                    </div>

                    <div id="cascade-suspension-date" class="component-stage-form <?= ($suspensionType === 'permanent') ? 'disabled' : 'active' ?>" data-state="suspension-date">
                        <hr class="component-divider">
                        <div class="component-group-item component-group-item--stacked">
                            <div class="component-card__content">
                                <div class="component-card__text">
                                    <h2 class="component-card__title">Fecha y hora de expiración</h2>
                                    <p class="component-card__description">El usuario recuperará el acceso automáticamente al llegar a este límite de tiempo.</p>
                                </div>
                            </div>
                            <div class="component-card__actions">
                                <div class="component-dropdown" id="date-picker-trigger-wrapper">
                                    <div class="component-input-wrapper--trigger" data-action="toggleModule" data-target="moduleCalendarSuspension" data-calendar-trigger="true" data-input-target="input-suspension-date" data-display-target="display-suspension-date">
                                        <span class="material-symbols-rounded trigger-select-icon">calendar_month</span>
                                        <span class="component-dropdown-text" id="display-suspension-date" style="flex: 1; text-align: left; padding-left: 8px;"><?= htmlspecialchars($displaySuspensionDate) ?></span>
                                        <span class="material-symbols-rounded">expand_more</span>
                                    </div>
                                    <input type="hidden" id="input-suspension-date" value="<?= htmlspecialchars($suspensionExpiresAt) ?>">

                                    <?php 
                                        // Definimos el ID del módulo y lo incluimos
                                        $calendarModuleId = 'moduleCalendarSuspension';
                                        include __DIR__ . '/../../modules/moduleCalendar.php'; 
                                    ?>

                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__content">
                            <div class="component-card__text">
                                <h2 class="component-card__title"><?= t('admin.suspension.note_title') ?? 'Nota adicional del administrador (Opcional)' ?></h2>
                                <p class="component-card__description"><?= t('admin.suspension.note_desc') ?? 'Proporciona contexto adicional sobre la sanción.' ?></p>
                            </div>
                        </div>
                        <div class="component-card__actions u-w-100">
                            <div class="component-input-wrapper component-input-wrapper--textarea u-w-100">
                                <textarea id="input-suspension-reason" class="component-textarea" placeholder="Ej. Spam repetitivo en los foros de la comunidad..."><?= htmlspecialchars($targetUser['suspension_reason'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="component-actions u-flex-end u-mt-24">
                <button type="button" class="component-button primary component-button--wide" id="btn-save-status-changes">
                    Guardar Cambios
                </button>
            </div>

        <?php endif; ?>
    </div>
</div>