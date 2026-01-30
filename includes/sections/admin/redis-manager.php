<?php
// includes/sections/admin/redis-manager.php
?>
<div class="component-wrapper component-wrapper--full component-wrapper--flex" data-section="admin-redis-manager">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-nav="admin/server" data-tooltip="Volver a Configuración">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </button>
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

    <div class="component-card component-card--grouped mt-4" id="redis-stats-container">
        <div class="component-group-item">
            <div class="component-stats-grid">
                <div class="component-stat-box">
                    <span class="component-stat-label">Versión</span>
                    <strong class="component-stat-value" data-stat="version">...</strong>
                </div>
                <div class="component-stat-box">
                    <span class="component-stat-label">Uptime</span>
                    <strong class="component-stat-value" data-stat="uptime">...</strong>
                </div>
                <div class="component-stat-box">
                    <span class="component-stat-label">Memoria</span>
                    <strong class="component-stat-value" data-stat="memory_used">...</strong>
                </div>
                <div class="component-stat-box">
                    <span class="component-stat-label">Clientes</span>
                    <strong class="component-stat-value" data-stat="connected_clients">...</strong>
                </div>
                <div class="component-stat-box">
                    <span class="component-stat-label">Claves Totales</span>
                    <strong class="component-stat-value" data-stat="total_keys">...</strong>
                </div>
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