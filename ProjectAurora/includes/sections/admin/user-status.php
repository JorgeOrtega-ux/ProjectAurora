<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-status">

    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/users" data-tooltip="Volver">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions">Acciones</span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-button d-none" id="btn-lift-ban" style="margin-right: 8px; color: #d32f2f; border-color: #ffcdd2;">
                    <span class="material-symbols-rounded">lock_open</span>
                    Levantar Sanción
                </button>
                
                <button class="component-button primary" id="btn-save-status">
                    <span class="material-symbols-rounded">save</span>
                    <span id="btn-save-text">Aplicar Sanción</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title">Gestionar Sanciones</h1>
            <p class="component-page-description">Aplica suspensiones temporales o permanentes.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__avatar" id="status-avatar-container">
                        <img src="" id="status-user-avatar" class="component-card__avatar-image hidden-avatar" style="display: none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="status-user-icon">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="status-username">Cargando...</h2>
                        <p class="component-card__description" id="status-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped mt-16">
            <input type="hidden" id="target-user-id" value="<?php echo htmlspecialchars($targetUid); ?>">

            <input type="hidden" id="input-status-value" value="suspended_temp">
            <input type="hidden" id="input-duration-value" value="2">
            <input type="hidden" id="input-reason-value" value="">

            <div id="active-sanction-alert" class="component-group-item d-none" style="background-color: #fff8e1; border-bottom: 1px solid #ffe0b2;">
                <div class="component-card__content">
                    <div class="component-icon-container" style="border-color: #ffb74d; background: #fff;">
                        <span class="material-symbols-rounded" style="color: #f57c00;">warning</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #ef6c00;">Este usuario ya está suspendido</h2>
                        <p class="component-card__description" id="active-sanction-desc" style="color: #e65100;">...</p>
                    </div>
                </div>
            </div>

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Tipo de Sanción</h2>
                        <p class="component-card__description">Selecciona el nivel de restricción.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-status-options">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="current-status-icon">timer</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="current-status-text">Suspensión Temporal</span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>

                        <div class="popover-module disabled" id="dropdown-status-options">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link"
                                        data-action="select-status-option"
                                        data-value="suspended_temp"
                                        data-label="Suspensión Temporal"
                                        data-icon="timer"
                                        data-color="#f57c00">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded status-temp">timer</span>
                                        </div>
                                        <div class="menu-link-text">Suspensión Temporal</div>
                                    </div>
                                    <div class="menu-link"
                                        data-action="select-status-option"
                                        data-value="suspended_perm"
                                        data-label="Suspensión Permanente"
                                        data-icon="block"
                                        data-color="#d32f2f">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded status-perm">block</span>
                                        </div>
                                        <div class="menu-link-text">Suspensión Permanente</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-duration" class="w-100">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Nueva Duración</h2>
                            <p class="component-card__description">Días a partir de hoy que el usuario estará bloqueado.</p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-duration">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded">calendar_today</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="current-duration-text">2 Días</span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>

                            <div class="popover-module disabled" id="dropdown-duration">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <?php
                                        $daysOptions = [2, 4, 6, 8, 12, 30];
                                        foreach ($daysOptions as $d) {
                                            echo "
                                            <div class='menu-link' data-action='select-duration-option' data-value='$d'>
                                                <div class='menu-link-icon'>
                                                    <span class='material-symbols-rounded'>schedule</span>
                                                </div>
                                                <div class='menu-link-text'>{$d} Días</div>
                                            </div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div id="wrapper-reason" class="w-100">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Motivo de la Sanción</h2>
                            <p class="component-card__description">Requerido para aplicar el castigo.</p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-reasons">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded">gavel</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="current-reason-text">Selecciona una razón...</span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>

                            <div class="popover-module disabled" id="dropdown-reasons">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <?php
                                        $reasons = [
                                            "Violación de términos de servicio",
                                            "Comportamiento inapropiado / Acoso",
                                            "Cuenta falsa o Spam",
                                            "Riesgo de seguridad",
                                            "Solicitud de verificación de identidad"
                                        ];
                                        foreach ($reasons as $r) {
                                            echo "<div class='menu-link' data-action=\"select-reason-option\" data-value=\"$r\">
                                                    <div class='menu-link-text'>$r</div>
                                                  </div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="component-card__error" id="status-error-msg" style="margin-top: 16px;"></div>

        <div class="component-card mt-16">
            <h3 class="history-title">
                <span class="material-symbols-rounded history-icon">history</span>
                Historial de Suspensiones
            </h3>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Fecha Inicio</th>
                            <th>Razón</th>
                            <th>Duración</th>
                            <th>Fin</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody id="suspension-history-body">
                        <tr>
                            <td colspan="5" class="history-loading">Cargando historial...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="<?php echo $basePath; ?>assets/js/modules/admin-status-manager.js"></script>