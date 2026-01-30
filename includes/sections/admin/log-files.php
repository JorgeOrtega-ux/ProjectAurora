<?php
// includes/sections/admin/log-files.php
?>
<div class="component-wrapper" data-section="admin-log-files">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group" data-element="toolbar-group-default">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-action="toggle-search" data-tooltip="Buscar archivos">
                        <span class="material-symbols-rounded">search</span>
                    </button>

                    <button class="header-button" data-action="change-view" data-tooltip="Cambiar vista">
                        <span class="material-symbols-rounded">grid_view</span>
                    </button>

                    <div class="component-toolbar-title d-none" data-element="toolbar-title">Explorador de Logs</div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <div data-element="count-wrapper" style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 500; padding: 0 10px;">
                        Cargando...
                    </div>
                </div>
            </div>

            <div class="toolbar-group d-none" data-element="toolbar-group-actions">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-action="delete-selected" data-tooltip="Eliminar archivos" style="color: var(--color-error);">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                    
                    <button class="header-button" data-action="download-selected" data-tooltip="Descargar (Zip)">
                        <span class="material-symbols-rounded">download</span>
                    </button>

                    <button class="header-button" data-action="view-log-content" data-tooltip="Ver contenido del log">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <span class="selection-info-text" data-element="selection-indicator">0 seleccionados</span>
                    <button class="header-button" data-action="close-selection" data-tooltip="Cancelar">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
            </div>

        </div>

        <div class="component-toolbar component-toolbar--secondary" data-element="search-panel">
            <div class="search-content w-100">
                <div class="search-icon"><span class="material-symbols-rounded">search</span></div>
                <div class="search-input">
                    <input type="text" placeholder="Buscar por nombre de archivo..." data-element="search-input" autocomplete="off">
                </div>
            </div>
        </div>
    </div>

    <div class="component-header-card" data-element="page-header">
        <h1 class="component-page-title">Archivos de Log</h1>
        <p class="component-page-description">Gestión física de los archivos de registro del servidor (logs/).</p>
    </div>

    <div class="component-list" data-component="file-list">
        <div class="state-loading">
            <div class="spinner-sm"></div>
            <p class="state-text">Escaneando directorio logs/...</p>
        </div>
    </div>

</div>