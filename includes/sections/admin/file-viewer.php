<?php
// includes/sections/admin/file-viewer.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-file-viewer">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        Visor de Archivos
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    
                    <div style="position: relative;">
                        <button class="header-button" data-action="toggle-options" data-tooltip="Opciones de visualización">
                            <span class="material-symbols-rounded">tune</span>
                        </button>

                        <div id="viewer-options-menu" class="popover-module" style="width: 260px; right: 0; left: auto; top: 100%; margin-top: 8px;">
                            <div class="menu-list">
                                <div class="menu-link" 
                                     data-action="toggle-highlight-mode" 
                                     data-label="Modo Profesional" 
                                     style="justify-content: space-between; cursor: pointer;">
                                    
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">code</span>
                                        </div>
                                        <div class="menu-link-text">Modo Profesional</div>
                                    </div>
                                    <label class="component-toggle-switch" style="pointer-events: none;">
                                        <input type="checkbox" id="check-highlight-mode">
                                        <span class="component-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="header-button" data-action="refresh-file" data-tooltip="Recargar archivo actual">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                    <button class="header-button" data-action="copy-content" data-tooltip="Copiar contenido">
                        <span class="material-symbols-rounded">content_copy</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="file-viewer-group mt-4">
        <div class="viewer-tabs-wrapper">
            <div class="viewer-tabs-scroll" id="file-viewer-tabs">
                </div>
        </div>

        <div class="component-card file-viewer-card">
            
            <div id="viewer-loading" class="state-loading d-none">
                <div class="spinner-sm"></div>
                <p class="state-text">Cargando contenido...</p>
            </div>

            <div id="viewer-error" class="state-error d-none" style="padding: 40px;"></div>

            <div id="file-content-container" class="file-content-area"></div>

        </div>
    </div>

</div>