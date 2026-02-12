<?php
// includes/sections/app/my-content.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

$requestedUuid = $routeParams['uuid'] ?? '';

if ($requestedUuid !== $_SESSION['uuid']) {
    ?>
    <div class="component-studio-state-screen">
        <div class="component-studio-state-content">
            <span class="material-symbols-rounded component-studio-state-icon danger">lock</span>
            <h2 class="component-studio-state-title">Acceso Denegado</h2>
            <p class="component-studio-state-text">No tienes permiso para ver este contenido.</p>
        </div>
    </div>
    <?php
    return;
}
?>

<div class="component-studio-layout" data-section="channel-content">
    
    <div class="component-studio-toolbar">
        
        <div class="component-studio-toolbar-group">
            <span class="component-toolbar-title">Contenido</span>
            
            <div class="component-divider-vertical"></div>

            <div class="trigger-select-wrapper" data-trigger="dropdown" id="content-filter-dropdown">
                <div class="trigger-selector">
                    <span class="material-symbols-rounded trigger-select-icon">filter_list</span>
                    <span class="trigger-select-text">Todos</span>
                    <span class="material-symbols-rounded">expand_more</span>
                </div>

                <div class="popover-module">
                    <div class="menu-list">
                        <div class="menu-link active" data-action="select-option" data-type="filter_status" data-value="all" data-label="Todos">
                            <div class="menu-link-icon"><span class="material-symbols-rounded">apps</span></div>
                            <div class="menu-link-text">Todos</div>
                        </div>
                        <div class="menu-link" data-action="select-option" data-type="filter_status" data-value="published" data-label="Publicados">
                            <div class="menu-link-icon"><span class="material-symbols-rounded">public</span></div>
                            <div class="menu-link-text">Publicados</div>
                        </div>
                        <div class="menu-link" data-action="select-option" data-type="filter_status" data-value="queued" data-label="Borradores">
                            <div class="menu-link-icon"><span class="material-symbols-rounded">edit_document</span></div>
                            <div class="menu-link-text">Borradores</div>
                        </div>
                        <div class="menu-link" data-action="select-option" data-type="filter_status" data-value="processing" data-label="Procesando">
                            <div class="menu-link-icon"><span class="material-symbols-rounded">hourglass_empty</span></div>
                            <div class="menu-link-text">Procesando</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-studio-toolbar-group">
            
            <div data-element="pagination-wrapper" class="component-pagination">
                <button class="header-button component-pagination-btn" data-action="prev-page" data-tooltip="Página anterior" disabled="">
                    <span class="material-symbols-rounded">chevron_left</span>
                </button>
                <span data-element="pagination-info" class="component-pagination-info">1/1</span>
                <button class="header-button component-pagination-btn" data-action="next-page" data-tooltip="Página siguiente" disabled="">
                    <span class="material-symbols-rounded">chevron_right</span>
                </button>
            </div>

            <div class="component-divider-vertical"></div>

            <button class="header-button" data-action="toggle-content-search" data-tooltip="Buscar video">
                <span class="material-symbols-rounded">search</span>
            </button>

          <button class="header-button" data-nav="s/channel/upload" data-tooltip="Crear contenido">
    <span class="material-symbols-rounded">add</span>
</button>
        </div>

        <div id="content-search-dropdown">
            <div class="component-input-wrapper w-100">
                <input type="text" class="component-text-input" id="content-search-input" placeholder="Buscar por título..." autocomplete="off">
            </div>
        </div>

    </div>

    <div class="component-studio-content-area">
        
        <div class="component-card component-card--fill">
            <div class="component-table-wrapper component-table-scrollable">
                <table class="component-table">
                    <thead class="component-table-sticky-header">
                        <tr>
                            <th style="min-width: 300px;">Video</th>
                            <th style="width: 120px;">Estado</th>
                            <th style="width: 140px;">Fecha</th>
                            <th style="width: 100px;" class="text-right">Duración</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody id="content-table-body">
                        </tbody>
                </table>
            </div>
            
            <div id="content-loading" class="state-loading d-none">
                <div class="spinner-sm"></div>
                <p class="state-text">Cargando...</p>
            </div>

            <div id="content-empty" class="state-empty d-none">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--text-tertiary); margin-bottom: 16px;">video_library</span>
                <p style="color: var(--text-secondary);">No se encontró contenido.</p>
            </div>
        </div>

    </div>
</div>