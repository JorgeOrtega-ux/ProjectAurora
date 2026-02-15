<?php
// includes/sections/admin/metadata-manager.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-metadata">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        Gestor de Contenido
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-system-alert-wrapper mt-4">
        <div class="component-segmented-control" style="width: 100%; max-width: 400px; margin-bottom: 20px;">
            <button class="component-segmented-btn active" data-tab="categories">Categorías</button>
            <button class="component-segmented-btn" data-tab="actors">Actores / Actrices</button>
        </div>

        <div class="admin-grid-layout" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            
            <div class="component-card p-4">
                <h3 class="text-lg font-bold mb-4" id="form-title">Nueva Categoría</h3>
                
                <form id="metadata-form">
                    <input type="hidden" id="meta-type" value="category">
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" id="meta-name" class="form-input" placeholder="Ej. Acción, Comedia..." required>
                    </div>

                    <div class="form-group mb-3" id="actor-type-group" style="display: none;">
                        <label class="form-label">Tipo</label>
                        <select id="meta-extra" class="form-select">
                            <option value="actress">Actriz</option>
                            <option value="actor">Actor</option>
                        </select>
                    </div>

                    <div class="form-actions mt-4">
                        <button type="submit" class="button button--primary w-full">
                            <span class="material-symbols-rounded">add</span> Agregar
                        </button>
                    </div>
                </form>
            </div>

            <div class="component-card p-0">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Slug</th>
                                <th id="col-extra">Uso</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="metadata-list">
                            </tbody>
                    </table>
                </div>
                <div id="loading-state" class="p-4 text-center text-secondary" style="display: none;">
                    Cargando datos...
                </div>
                <div id="empty-state" class="p-4 text-center text-secondary" style="display: none;">
                    No hay registros encontrados.
                </div>
            </div>

        </div>
    </div>
</div>

<script type="module" src="<?php echo $basePath; ?>public/assets/js/modules/admin/metadata-controller.js"></script>