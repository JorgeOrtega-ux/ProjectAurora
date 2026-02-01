<?php
// includes/sections/admin/user-status.php

require_once __DIR__ . '/../../libs/Utils.php';
require_once __DIR__ . '/../../../api/services/AdminService.php';

$targetId = $_GET['id'] ?? null;
if (!$targetId) {
    echo "<script>window.location.href = '?page=admin/users';</script>";
    exit;
}

$currentAdminId = $_SESSION['user_id'] ?? 0; 
$adminService = new AdminService($pdo, $i18n, $currentAdminId);
$response = $adminService->getUserDetails($targetId);
$user = $response['success'] ? $response['user'] : null;

if (!$user) {
    echo "Usuario no encontrado";
    exit;
}

// === PREPARAR DATOS PARA JS ===
$suspensionData = [
    'status' => $user['account_status'],
    'reason' => $user['status_reason'] ?? '',
    'suspension_ends_at' => $user['suspension_ends_at']
];
?>

<div class="component-wrapper" data-section="admin-user-status" data-user-id="<?php echo htmlspecialchars($user['id']); ?>">

    <script type="application/json" id="server-status-data">
        <?php echo json_encode($suspensionData); ?>
    </script>

    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        Gestionar Estado de Cuenta
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" style="cursor: help; border-color: transparent;" data-tooltip="Auditoría Activa: Sus cambios quedarán registrados.">
                        <span class="material-symbols-rounded" style="color: var(--text-secondary);">history_edu</span>
                    </button>

                    <button class="component-button primary" id="btn-save-status" disabled>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-header-card">
        <h1 class="component-page-title">Estado: <?php echo htmlspecialchars($user['username']); ?></h1>
        <p class="component-page-description">Configura el acceso y suspensiones.</p>
    </div>

    <div class="component-card component-card--grouped">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title">Estado de cuenta</h2>
                    <p class="component-card__description">Define si el usuario está activo, suspendido o eliminado.</p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">gpp_maybe</span>
                        <span class="trigger-select-text" data-element="current-status-label">Seleccionar estado...</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    
                    <div class="popover-module">
                        <div class="menu-list">
                            <div class="menu-link" data-action="select-option" data-type="status" data-value="active" data-label="Activo">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">check_circle</span></div>
                                <div class="menu-link-text">Activo</div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="status" data-value="suspended" data-label="Suspendido">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">block</span></div>
                                <div class="menu-link-text">Suspendido</div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="status" data-value="deleted" data-label="Eliminado">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">delete</span></div>
                                <div class="menu-link-text">Eliminado</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-suspension-type">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title">Tipo de Suspensión</h2>
                    <p class="component-card__description">Elige entre temporal o permanente.</p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">timer</span>
                        <span class="trigger-select-text" data-element="suspension-type-label">Seleccionar tipo...</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="popover-module">
                        <div class="menu-list">
                            <div class="menu-link" data-action="select-option" data-type="suspension_type" data-value="temp" data-label="Temporal">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">timelapse</span></div>
                                <div class="menu-link-text">Temporal</div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="suspension_type" data-value="perm" data-label="Permanente">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">lock_forever</span></div>
                                <div class="menu-link-text">Permanente</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-suspension-days">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title">Fecha de finalización</h2>
                    <p class="component-card__description">Selecciona hasta cuándo estará suspendido el usuario.</p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="date-time-picker-wrapper" id="suspension-picker-wrapper" style="position: relative;">
                    <input type="hidden" id="suspension-date-input" name="suspension_ends_at">
                    
                    <div class="trigger-selector" style="cursor: pointer;">
                        <span class="material-symbols-rounded trigger-select-icon">calendar_month</span>
                        <span class="trigger-select-text" id="suspension-date-label">Seleccionar fecha...</span>
                        <span class="material-symbols-rounded" style="font-size: 18px;">edit_calendar</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-deletion-source">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title">Decisión</h2>
                    <p class="component-card__description">¿Quién solicitó la eliminación?</p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">person</span>
                        <span class="trigger-select-text" data-element="deletion-source-label">Seleccionar origen...</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="popover-module">
                        <div class="menu-list">
                            <div class="menu-link" data-action="select-option" data-type="deletion_source" data-value="user" data-label="Decisión del Usuario">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">person_remove</span></div>
                                <div class="menu-link-text">Decisión del Usuario</div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="deletion_source" data-value="admin" data-label="Decisión Administrativa">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">gavel</span></div>
                                <div class="menu-link-text">Decisión Administrativa</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-reason">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title">Razón</h2>
                    <p class="component-card__description">Motivo del cambio de estado.</p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">description</span>
                        <span class="trigger-select-text" data-element="reason-label">Seleccionar razón...</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="popover-module popover-module--searchable">
                         <div class="menu-content menu-content--flush">
                            <div class="menu-search-header">
                                <div class="component-input-wrapper">
                                    <input type="text" class="component-text-input component-text-input--sm" placeholder="Buscar..." data-action="filter-reason"> 
                                </div>
                            </div>
                            <div class="menu-list menu-list--scrollable overflow-y" id="reason-list-container">
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>