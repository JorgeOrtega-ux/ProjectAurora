<?php
// includes/sections/admin/backups.php
?>
<div class="component-wrapper" data-section="admin-backups">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group" data-element="toolbar-group-default">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-action="back-to-dashboard" data-tooltip="Volver al Dashboard" onclick="history.back()">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </button>
                    
                    <button class="header-button" data-action="change-view" data-tooltip="Cambiar vista">
                        <span class="material-symbols-rounded">grid_view</span>
                    </button>

                    <div class="component-toolbar-title d-none" data-element="toolbar-title">
                        <?php echo $i18n->t('menu.admin.backups'); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" data-nav="admin/backups/config" data-tooltip="Configuración Automática">
                        <span class="material-symbols-rounded">settings_motion_mode</span>
                    </button>
                    <button class="component-button primary" id="btn-create-backup">
                        <span class="material-symbols-rounded">add</span>
                        Crear Copia
                    </button>
                </div>
            </div>

            <div class="toolbar-group d-none" data-element="toolbar-group-actions">
                <div class="component-toolbar__side component-toolbar__side--left">
                    
                    <button class="header-button" data-action="view-selected" data-tooltip="Ver contenido">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>

                    <button class="header-button" data-action="restore-selected" data-tooltip="Restaurar backup seleccionado">
                        <span class="material-symbols-rounded">history</span>
                    </button>
                    
                    <button class="header-button" data-action="delete-selected" data-tooltip="Eliminar seleccionados" style="color: var(--color-error);">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <span class="selection-info-text" data-element="selection-indicator">0 seleccionados</span>
                    <button class="header-button" data-action="close-selection" data-tooltip="Cancelar selección">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="component-header-card" data-element="page-header">
        <h1 class="component-page-title"><?php echo $i18n->t('menu.admin.backups'); ?></h1>
        <p class="component-page-description">Gestiona los puntos de restauración de la base de datos.</p>
    </div>

    <div class="component-list" data-component="backup-list">
        <div class="state-loading">
            <div class="spinner-sm"></div>
            <p class="state-text">Cargando copias de seguridad...</p>
        </div>
    </div>

</div>