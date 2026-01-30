<?php
// includes/sections/admin/redis-manager.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-redis-manager">
    
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
            <div style="display:flex; justify-content:space-between; width:100%; flex-wrap:wrap; gap:16px;">
                <div class="stat-item" style="flex:1; min-width:120px; text-align:center;">
                    <span style="font-size:12px; color:var(--text-secondary); display:block;">Versión</span>
                    <strong style="font-size:16px; color:var(--text-primary);" data-stat="version">...</strong>
                </div>
                <div class="stat-item" style="flex:1; min-width:120px; text-align:center;">
                    <span style="font-size:12px; color:var(--text-secondary); display:block;">Uptime</span>
                    <strong style="font-size:16px; color:var(--text-primary);" data-stat="uptime">...</strong>
                </div>
                <div class="stat-item" style="flex:1; min-width:120px; text-align:center;">
                    <span style="font-size:12px; color:var(--text-secondary); display:block;">Memoria</span>
                    <strong style="font-size:16px; color:var(--text-primary);" data-stat="memory_used">...</strong>
                </div>
                <div class="stat-item" style="flex:1; min-width:120px; text-align:center;">
                    <span style="font-size:12px; color:var(--text-secondary); display:block;">Clientes</span>
                    <strong style="font-size:16px; color:var(--text-primary);" data-stat="connected_clients">...</strong>
                </div>
                <div class="stat-item" style="flex:1; min-width:120px; text-align:center;">
                    <span style="font-size:12px; color:var(--text-secondary); display:block;">Claves Totales</span>
                    <strong style="font-size:16px; color:var(--text-primary);" data-stat="total_keys">...</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card mt-4" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 600px;">
        
        <div style="padding: 12px; border-bottom: 1px solid var(--border-light); background: var(--bg-surface); display: flex; gap: 8px;">
            <div class="component-input-wrapper flex-1">
                <input type="text" class="component-text-input" id="redis-search-input" placeholder="Buscar clave (ej: user:*, *token*)..." value="*">
            </div>
            <button class="component-button primary" id="btn-redis-search">
                <span class="material-symbols-rounded">search</span>
            </button>
        </div>

        <div class="component-table-wrapper" style="flex: 1; overflow-y: auto; border:none; box-shadow:none; border-radius:0;">
            <table class="component-table" id="redis-keys-table">
                <thead style="position: sticky; top: 0; background: var(--bg-surface); z-index: 10;">
                    <tr>
                        <th>Clave</th>
                        <th style="width: 80px;">Tipo</th>
                        <th style="width: 80px;">TTL</th>
                        <th style="width: 60px;"></th>
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