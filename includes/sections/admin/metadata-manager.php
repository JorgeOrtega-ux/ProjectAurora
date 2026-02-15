<?php
// includes/sections/admin/metadata-manager.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-metadata">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <span class="material-symbols-rounded mr-2">category</span>
                        Gestor de Metadatos
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="w-100 mt-4" style="max-width: 1200px; margin: 0 auto;">
        
        <div class="d-flex gap-2 mb-4">
            <button class="component-button primary" data-tab="categories" id="tab-categories">
                <span class="material-symbols-rounded">label</span> Categorías
            </button>
            <button class="component-button" data-tab="actors" id="tab-actors">
                <span class="material-symbols-rounded">face</span> Actores / Actrices
            </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; align-items: start;">
            
            <div class="component-card p-4" style="position: sticky; top: 80px;">
                <div class="component-header-centered mb-4" style="align-items: flex-start; text-align: left;">
                    <h3 style="font-size: 18px; font-weight: 700; margin: 0;" id="form-title">Nueva Categoría</h3>
                    <p style="font-size: 13px; color: var(--text-secondary);">Agrega nuevos registros al sistema.</p>
                </div>
                
                <form id="metadata-form" class="component-stage-form">
                    <input type="hidden" id="meta-type" value="category">
                    
                    <div class="component-form-group">
                        <label class="text-sm font-medium mb-1" style="font-size: 13px; font-weight: 600;">Nombre</label>
                        <div class="component-input-wrapper">
                            <input type="text" id="meta-name" class="component-text-input" placeholder="Ej. Acción, Comedia..." required>
                        </div>
                    </div>

                    <div class="component-form-group" id="actor-type-group" style="display: none;">
                        <label class="text-sm font-medium mb-1" style="font-size: 13px; font-weight: 600;">Tipo de Intérprete</label>
                        <div class="component-input-wrapper">
                            <select id="meta-extra" class="component-text-input" style="cursor: pointer;">
                                <option value="actress">Actriz</option>
                                <option value="actor">Actor</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="component-button primary w-100">
                            <span class="material-symbols-rounded">add_circle</span> Agregar Registro
                        </button>
                    </div>
                </form>
            </div>

            <div class="component-card p-0 overflow-hidden" style="min-height: 400px; display: flex; flex-direction: column;">
                
                <div class="component-table-wrapper border-0 shadow-none flex-1">
                    <table class="component-table">
                        <thead>
                            <tr>
                                <th style="padding-left: 24px;">Nombre</th>
                                <th>Slug / ID</th>
                                <th id="col-extra">Detalles</th>
                                <th class="text-right" style="padding-right: 24px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="metadata-list">
                            </tbody>
                    </table>
                </div>

                <div id="loading-state" class="state-loading" style="display: none; padding: 40px;">
                    <div class="spinner-sm mb-2"></div>
                    <div class="state-text">Cargando datos...</div>
                </div>
                
                <div id="empty-state" class="state-empty" style="display: none; padding: 40px;">
                    <span class="material-symbols-rounded text-secondary mb-2" style="font-size: 32px; opacity: 0.5;">folder_off</span>
                    <div class="state-text">No se encontraron registros.</div>
                </div>

            </div>

        </div>
    </div>
</div>