<?php
// includes/sections/admin/redis-manager.php
?>
<div class="component-wrapper component-wrapper--full component-wrapper--flex" data-section="admin-redis-manager">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        Gestor de Redis
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" data-action="refresh-all" data-tooltip="Actualizar Todo">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                    <button class="header-button" data-action="flush-db" data-tooltip="VACIAR BASE DE DATOS" style="color: var(--color-error); border-color: rgba(211, 47, 47, 0.3);">
                        <span class="material-symbols-rounded">delete_forever</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="component-dashboard-grid mt-4" id="redis-stats-container">
        
        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Versión Redis</span>
                <span class="material-symbols-rounded component-stat-icon">info</span>
            </div>
            <div class="component-stat-main-value" data-stat="version">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral">Estable</span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Tiempo Activo</span>
                <span class="material-symbols-rounded component-stat-icon">timer</span>
            </div>
            <div class="component-stat-main-value" data-stat="uptime" style="font-size: 1.1rem; line-height: 1.4;">...</div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Memoria Usada</span>
                <span class="material-symbols-rounded component-stat-icon" style="color: #0288d1;">memory</span>
            </div>
            <div class="component-stat-main-value" data-stat="memory_used">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text">Pico máx: <strong data-stat="memory_peak">...</strong></span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Conexiones</span>
                <span class="material-symbols-rounded component-stat-icon">hub</span>
            </div>
            <div class="component-stat-main-value" data-stat="connected_clients">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text">Clientes activos</span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Total Claves</span>
                <span class="material-symbols-rounded component-stat-icon" style="color: var(--action-primary);">vpn_key</span>
            </div>
            <div class="component-stat-main-value" data-stat="total_keys">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text">En base de datos actual</span>
            </div>
        </div>

    </div>

    <div class="component-card component-card--fill mt-4">
        
        <div class="component-toolbar-internal">
            <div class="component-input-wrapper flex-1">
                <input type="text" class="component-text-input" id="redis-search-input" placeholder="Buscar clave (ej: user:*, *token*)..." value="*">
            </div>
            <button class="component-button primary" id="btn-redis-search">
                <span class="material-symbols-rounded">search</span>
            </button>
        </div>

        <div class="component-table-wrapper component-table-scrollable">
            <table class="component-table" id="redis-keys-table">
                <thead class="component-table-sticky-header">
                    <tr>
                        <th>Clave</th>
                        <th class="w-80">Tipo</th>
                        <th class="w-80">TTL</th>
                        <th class="w-60"></th>
                    </tr>
                </thead>
                <tbody id="redis-keys-body">
                    </tbody>
            </table>
        </div>
        
        <div id="redis-loading" class="state-loading d-none">
            <div class="spinner-sm"></div>
            <p class="state-text">Escaneando Redis...</p>
        </div>
    </div>

</div>