<?php
// includes/sections/admin/file-viewer.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-file-viewer">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-action="back-history" data-tooltip="Volver" onclick="history.back()">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </button>
                    
                    <div class="component-toolbar-title">
                        Visor de Archivos
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
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

    <div class="viewer-tabs-wrapper mt-4">
        <div class="viewer-tabs-scroll" id="file-viewer-tabs">
            </div>
    </div>

    <div class="component-card file-viewer-card" style="padding: 0; min-height: 400px; border-top-left-radius: 0;">
        
        <div id="viewer-loading" class="state-loading d-none">
            <div class="spinner-sm"></div>
            <p class="state-text">Cargando contenido...</p>
        </div>

        <div id="viewer-error" class="state-error d-none" style="padding: 40px;"></div>

        <div id="file-content-container" class="file-content-area">
            </div>

    </div>

</div>