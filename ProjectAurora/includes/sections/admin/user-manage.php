<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-manage">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-tooltip="Volver">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span style="font-size: 14px; font-weight: 600; color: #666;">Estado</span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-button primary" id="btn-save-manage">
                    <span class="material-symbols-rounded">save</span>
                    Guardar Estado
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title">Gestionar Usuario</h1>
            <p class="component-page-description">Ciclo de vida de la cuenta y notas administrativas.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__avatar" id="manage-avatar-container">
                        <img src="" id="manage-user-avatar" class="component-card__avatar-image" style="display:none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="manage-user-icon" style="font-size: 24px;">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="manage-username">Cargando...</h2>
                        <p class="component-card__description" id="manage-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped" style="margin-top: 16px;">
            <input type="hidden" id="manage-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">
            
            <input type="hidden" id="manage-status-value" value="active">
            <input type="hidden" id="manage-deletion-type" value="admin_decision">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Estado de la cuenta</h2>
                        <p class="component-card__description">Activar o Eliminar definitivamente.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-manage-status">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="manage-status-icon" style="color: #2e7d32;">check_circle</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="manage-status-text">Activo</span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>
                        
                        <div class="popover-module disabled" id="dropdown-manage-status" style="width: 100%; position: absolute; top: 100%; z-index: 10;">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link" 
                                         data-action="select-manage-status" 
                                         data-value="active" 
                                         data-label="Activo" 
                                         data-icon="check_circle" 
                                         data-color="#2e7d32">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#2e7d32">check_circle</span></div>
                                        <div class="menu-link-text">Activo</div>
                                    </div>
                                    <div class="menu-link" 
                                         data-action="select-manage-status" 
                                         data-value="deleted" 
                                         data-label="Cuenta Eliminada" 
                                         data-icon="delete_forever" 
                                         data-color="#616161">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#616161">delete_forever</span></div>
                                        <div class="menu-link-text">Cuenta Eliminada</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-deletion-details" class="w-100 d-none">
                <hr class="component-divider">
                
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Tipo de Decisión</h2>
                            <p class="component-card__description">¿Quién solicitó la eliminación?</p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-deletion-type">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded">gavel</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="text-deletion-type">Decisión Administrativa</span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>
                            
                            <div class="popover-module disabled" id="dropdown-deletion-type" style="width: 100%; position: absolute; top: 100%; z-index: 10;">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <div class="menu-link" 
                                             data-action="select-deletion-type" 
                                             data-value="admin_decision" 
                                             data-label="Decisión Administrativa">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">admin_panel_settings</span></div>
                                            <div class="menu-link-text">Decisión Administrativa</div>
                                        </div>
                                        <div class="menu-link" 
                                             data-action="select-deletion-type" 
                                             data-value="user_decision" 
                                             data-label="Decisión del Usuario">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">person</span></div>
                                            <div class="menu-link-text">Decisión del Usuario</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="wrapper-user-reason" class="d-none">
                    <hr class="component-divider">
                    <div class="component-group-item component-group-item--stacked">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Razón del Usuario</h2>
                            <p class="component-card__description">Motivo proporcionado por el usuario para salir.</p>
                        </div>
                        <div class="component-input-wrapper w-100" style="margin-top: 10px;">
                            <textarea id="input-user-reason" class="component-text-input full-width" style="height: 80px; padding: 10px;" placeholder="Ej: No utilizo la plataforma frecuentemente..."></textarea>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Comentarios Administrativos</h2>
                        <p class="component-card__description">Registro interno del motivo de la eliminación.</p>
                    </div>
                    <div class="component-input-wrapper w-100" style="margin-top: 10px;">
                        <textarea id="input-admin-comments" class="component-text-input full-width" style="height: 80px; padding: 10px;" placeholder="Ej: Solicitud vía ticket #1234 o Inactividad prolongada..."></textarea>
                    </div>
                </div>

            </div>
            
            <div class="component-card__error" id="manage-error-msg" style="margin: 20px 0 0 0; width: 100%;"></div>

        </div>
    </div>
</div>

<script src="<?php echo $basePath; ?>assets/js/modules/admin-manage-manager.js"></script>