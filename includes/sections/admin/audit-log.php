<?php
// includes/sections/admin/audit-log.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-audit-log">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" onclick="history.back()" data-tooltip="Volver">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </button>
                    
                    <div class="component-toolbar-title">
                        <?php echo $i18n->t('admin.audit.title'); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    
                    <div data-element="pagination-wrapper" style="display: flex; align-items: center; gap: 2px; border: 1px solid var(--border-light); border-radius: 10px; padding: 3px; margin-right: 8px;">
                        
                        <button class="header-button" data-action="prev-page" data-tooltip="Página anterior" disabled style="width: 32px; height: 32px; border: none;">
                            <span class="material-symbols-rounded" style="font-size: 1.2rem;">chevron_left</span>
                        </button>

                        <span data-element="pagination-info" style="font-size: 0.85rem; color: var(--text-primary); font-weight: 500; padding: 0 10px; min-width: 60px; text-align: center;">1/1</span>

                        <button class="header-button" data-action="next-page" data-tooltip="Página siguiente" disabled style="width: 32px; height: 32px; border: none;">
                            <span class="material-symbols-rounded" style="font-size: 1.2rem;">chevron_right</span>
                        </button>
                    </div>

                    <button class="header-button" data-action="refresh-log" data-tooltip="Actualizar">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="component-header-card d-none" data-element="page-header">
        <h1 class="component-page-title"><?php echo $i18n->t('admin.audit.title'); ?></h1>
        <p class="component-page-description"><?php echo $i18n->t('admin.audit.desc'); ?></p>
    </div>

    <div class="component-card mt-4" style="padding: 0; overflow: hidden; border: none; background: transparent;">
        <div class="component-table-wrapper">
            <table class="component-table" id="audit-log-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th> <th><?php echo $i18n->t('admin.audit.table.actor'); ?></th>
                        <th><?php echo $i18n->t('admin.audit.table.action'); ?></th>
                        <th><?php echo $i18n->t('admin.audit.table.target'); ?></th>
                        <th style="width: 40%;"><?php echo $i18n->t('admin.audit.table.details'); ?></th>
                        <th><?php echo $i18n->t('admin.audit.table.date'); ?></th>
                    </tr>
                </thead>
                <tbody id="audit-log-body">
                    </tbody>
            </table>
        </div>
        
        <div id="audit-loading" class="state-loading">
            <div class="spinner-sm"></div>
            <p class="state-text">Cargando actividad...</p>
        </div>
    </div>

</div>