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
            <h1 class="component-page-title">Gestionar Estado</h1>
            <p class="component-page-description">Modifica el acceso del usuario a la plataforma.</p>
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

        <div class="component-card" style="margin-top: 16px;">
            <input type="hidden" id="target-user-id" value="<?php echo htmlspecialchars($targetUid); ?>">
            
            <div class="component-card__content" style="flex-direction: column; align-items: flex-start; gap: 20px;">
                
                <div class="w-100">
                    <label style="font-size: 13px; font-weight: 600; color: #333; display: block; margin-bottom: 8px;">Estado de la cuenta</label>
                    <div class="trigger-select-wrapper" style="width: 100%;">
                        <div class="trigger-selector" data-action="toggleStatusDropdown">
                            <div class="trigger-select-icon"><span class="material-symbols-rounded" id="current-status-icon">check_circle</span></div>
                            <div class="trigger-select-text"><span id="current-status-text">Activo</span></div>
                            <div class="trigger-select-arrow"><span class="material-symbols-rounded">arrow_drop_down</span></div>
                        </div>
                        
                        <div class="popover-module disabled" id="dropdown-status-options" style="width: 100%; position: absolute; top: 100%; z-index: 10;">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link" onclick="selectStatus('active', 'Activo', 'check_circle', '#2e7d32')">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#2e7d32">check_circle</span></div>
                                        <div class="menu-link-text">Activo</div>
                                    </div>
                                    <div class="menu-link" onclick="selectStatus('suspended', 'Suspendido', 'block', '#d32f2f')">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#d32f2f">block</span></div>
                                        <div class="menu-link-text">Suspendido</div>
                                    </div>
                                    <div class="menu-link" onclick="selectStatus('deleted', 'Eliminado', 'delete', '#616161')">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded" style="color:#616161">delete</span></div>
                                        <div class="menu-link-text">Eliminado</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="input-status-value" value="active">
                    </div>
                </div>

                <div id="panel-suspension" class="w-100 d-none" style="background: #fff5f5; padding: 16px; border-radius: 8px; border: 1px solid #ffcdd2;">
                    <h3 style="font-size: 14px; color: #d32f2f; margin-bottom: 12px;">Detalles de la Suspensión</h3>
                    
                    <div class="w-100" style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 600; color: #d32f2f; display: block; margin-bottom: 6px;">Duración (Días)</label>
                        <input type="number" id="input-days" class="component-text-input w-100" min="1" value="7" placeholder="Ej: 7">
                    </div>

                    <div class="w-100">
                        <label style="font-size: 12px; font-weight: 600; color: #d32f2f; display: block; margin-bottom: 6px;">Razón</label>
                        <div class="trigger-select-wrapper" style="width: 100%;">
                            <div class="trigger-selector" onclick="document.getElementById('dropdown-reasons').classList.toggle('disabled')">
                                <div class="trigger-select-text"><span id="reason-text">Selecciona una razón...</span></div>
                                <div class="trigger-select-arrow"><span class="material-symbols-rounded">arrow_drop_down</span></div>
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
                            <input type="hidden" id="input-reason-value">
                        </div>
                    </div>
                </div>

                <div class="component-card__actions w-100 justify-end">
                    <button class="component-button primary" id="btn-save-status">Guardar Cambios</button>
                </div>
                <div class="component-card__error" id="status-error-msg"></div>

            </div>
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