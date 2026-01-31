<?php
// includes/sections/admin/dashboard.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-dashboard">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <?php echo $i18n->t('menu.admin.dashboard'); ?>
                    </div>
                </div>

               <div class="component-toolbar__side component-toolbar__side--right">
                    
                    <button class="header-button" data-nav="admin/log-files" data-tooltip="Logs del Sistema">
                        <span class="material-symbols-rounded">description</span>
                    </button>

                    <button class="header-button" data-nav="admin/audit-log" data-tooltip="Registro de Actividad">
                        <span class="material-symbols-rounded">history_edu</span>
                    </button>
<button class="header-button" id="btn-open-alert-modal" data-tooltip="Emitir Alerta">
    <span class="material-symbols-rounded">campaign</span>
</button>
                    <div class="component-divider-vertical" style="height: 24px; border-left: 1px solid var(--border-light); margin: 0 4px;"></div>

                    <button class="header-button" data-action="refresh-dashboard" data-tooltip="Actualizar datos">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="component-dashboard-grid mt-4">
        
        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Usuarios Totales</span>
                <span class="material-symbols-rounded component-stat-icon">group</span>
            </div>
            <div class="component-stat-main-value" data-stat="total_users">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral" data-trend="total_users">
                    <span class="material-symbols-rounded" style="font-size:14px;">remove</span> 0%
                </span>
                <span class="component-trend-text">vs mes pasado</span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Registros Hoy</span>
                <span class="material-symbols-rounded component-stat-icon">person_add</span>
            </div>
            <div class="component-stat-main-value" data-stat="new_users_today">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral" data-trend="new_users_today">
                    <span class="material-symbols-rounded" style="font-size:14px;">remove</span> 0%
                </span>
                <span class="component-trend-text">vs ayer</span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">En Línea (Ahora)</span>
                <span class="material-symbols-rounded component-stat-icon" style="color:var(--color-success);">wifi</span>
            </div>
            <div class="component-stat-main-value" data-stat="online_total">...</div>
            <div class="component-stat-footer" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                <div style="font-size:11px; color:var(--text-secondary); display:flex; justify-content:space-between; width:100%;">
                    <span>Usuarios:</span> <strong style="color:var(--text-primary);" data-stat="online_users">0</strong>
                </div>
                <div style="font-size:11px; color:var(--text-secondary); display:flex; justify-content:space-between; width:100%;">
                    <span>Invitados:</span> <strong style="color:var(--text-primary);" data-stat="online_guests">0</strong>
                </div>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Alertas de Sistema</span>
                <span class="material-symbols-rounded component-stat-icon">notifications_active</span>
            </div>
            <div class="component-stat-main-value" data-stat="system_activity">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text">Eventos registrados hoy</span>
            </div>
        </div>

    </div>

</div>