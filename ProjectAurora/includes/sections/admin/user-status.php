<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; exit;
}

$targetUid = $_GET['uid'] ?? 0;
?>

<div class="section-content active" data-section="admin/user-status">
    <div class="component-wrapper">

        <div class="component-header-card">
            <div class="auth-back-link" style="margin-bottom: 15px; text-align: left;">
                <a href="#" onclick="event.preventDefault(); navigateTo('admin/users')" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                    <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> 
                    <span>Volver a usuarios</span>
                </a>
            </div>
            <h1 class="component-page-title" style="color: #f57c00;">Gestionar Sanciones</h1>
            <p class="component-page-description">Aplica suspensiones temporales o permanentes.</p>
        </div>

        <div class="content-toolbar" style="width: 100%; max-width: 100%; justify-content: flex-end; margin: 16px 0;">
            <button class="component-button primary" id="btn-save-status">
                <span class="material-symbols-rounded">save</span>
                Aplicar Sanción
            </button>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__avatar">
                        <img src="" id="status-user-avatar" class="component-card__avatar-image" style="display:none;">
                        <span class="material-symbols-rounded default-avatar-icon" id="status-user-icon" style="font-size: 24px;">person</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" id="status-username">Cargando...</h2>
                        <p class="component-card__description" id="status-email">...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped" style="margin-top: 16px;">
            <input type="hidden" id="target-user-id" value="<?php echo htmlspecialchars($targetUid); ?>">
            
            <input type="hidden" id="input-status-value" value="suspended_temp">
            <input type="hidden" id="input-duration-value" value="2">
            <input type="hidden" id="input-reason-value" value="">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Tipo de Sanción</h2>
                        <p class="component-card__description">Selecciona el nivel de restricción.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="trigger-select-wrapper w-100">
                        <div class="trigger-selector" data-action="toggleStatusDropdown">
                            <div class="trigger-select-icon">
                                <span class="material-symbols-rounded" id="current-status-icon" style="color: #f57c00;">timer</span>
                            </div>
                            <div class="trigger-select-text">
                                <span id="current-status-text">Suspensión Temporal</span>
                            </div>
                            <div class="trigger-select-arrow">
                                <span class="material-symbols-rounded">arrow_drop_down</span>
                            </div>
                        </div>
                        
                        <div class="popover-module disabled" id="dropdown-status-options" style="width: 100%; position: absolute; top: 100%; z-index: 10;">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link" onclick="selectStatus('suspended_temp', 'Suspensión Temporal', 'timer', '#f57c00')">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#f57c00">timer</span></div>
                                        <div class="menu-link-text">Suspensión Temporal</div>
                                    </div>
                                    <div class="menu-link" onclick="selectStatus('suspended_perm', 'Suspensión Permanente', 'block', '#d32f2f')">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#d32f2f">block</span></div>
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
                            <h2 class="component-card__title" style="color: #f57c00;">Duración</h2>
                            <p class="component-card__description">Días que el usuario estará bloqueado.</p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" onclick="document.getElementById('dropdown-duration').classList.toggle('disabled')">
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
                            
                            <div class="popover-module disabled" id="dropdown-duration" style="width: 100%; position: absolute; top: 100%; z-index: 10;">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <?php 
                                        $daysOptions = [2, 4, 6, 8, 12, 30];
                                        foreach($daysOptions as $d) {
                                            echo "
                                            <div class='menu-link' onclick=\"selectDuration($d)\">
                                                <div class='menu-link-icon'><span class='material-symbols-rounded'>schedule</span></div>
                                                <div class='menu-link-text'>$d Días</div>
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
                            <h2 class="component-card__title" style="color: #d32f2f;">Motivo de la Sanción</h2>
                            <p class="component-card__description">Requerido para aplicar el castigo.</p>
                        </div>
                    </div>
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" onclick="document.getElementById('dropdown-reasons').classList.toggle('disabled')">
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
                            
                            <div class="popover-module disabled" id="dropdown-reasons" style="width: 100%; position: absolute; top: 100%; z-index: 10;">
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
                                        foreach($reasons as $r) {
                                            echo "<div class='menu-link' onclick=\"selectReason('$r')\"><div class='menu-link-text'>$r</div></div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="component-card__error" id="status-error-msg" style="margin: 20px 0 0 0; width: 100%;"></div>

        </div>

        <div class="component-card" style="margin-top: 16px;">
            <h3 style="font-size: 16px; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-rounded" style="color: #666;">history</span>
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
                            <td colspan="5" style="text-align: center; padding: 20px; color: #999;">Cargando historial...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="<?php echo isset($basePath) ? $basePath : '/ProjectAurora/'; ?>assets/js/modules/admin-status-manager.js"></script>