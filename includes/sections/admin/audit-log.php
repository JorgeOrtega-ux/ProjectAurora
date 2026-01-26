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
                        <th style="width: 160px;"><?php echo $i18n->t('admin.audit.table.date'); ?></th>
                        <th style="width: 120px;"><?php echo $i18n->t('admin.audit.table.actor'); ?></th>
                        <th style="width: 140px;"><?php echo $i18n->t('admin.audit.table.action'); ?></th>
                        <th style="width: 100px;"><?php echo $i18n->t('admin.audit.table.target'); ?></th>
                        <th><?php echo $i18n->t('admin.audit.table.details'); ?></th>
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

        <div class="component-toolbar" style="margin-top: 16px; justify-content: center; background: transparent; border: none; box-shadow: none;">
            <button class="header-button" data-action="prev-page" disabled>
                <span class="material-symbols-rounded">chevron_left</span>
            </button>
            <span id="audit-page-info" style="font-size: 13px; font-weight: 600; padding: 0 12px; color: var(--text-secondary);">1 / 1</span>
            <button class="header-button" data-action="next-page" disabled>
                <span class="material-symbols-rounded">chevron_right</span>
            </button>
        </div>
    </div>

</div>