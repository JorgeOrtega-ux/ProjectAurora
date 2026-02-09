<?php
// includes/sections/admin/audit-log.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-audit-log">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <?php echo $i18n->t('admin.audit.title'); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    
                    <div data-element="pagination-wrapper" class="component-pagination component-pagination--spaced">
                        
                        <button class="header-button component-pagination-btn" data-action="prev-page" data-tooltip="<?php echo $i18n->t('global.pagination_prev'); ?>" disabled>
                            <span class="material-symbols-rounded">chevron_left</span>
                        </button>

                        <span data-element="pagination-info" class="component-pagination-info">1/1</span>

                        <button class="header-button component-pagination-btn" data-action="next-page" data-tooltip="<?php echo $i18n->t('global.pagination_next'); ?>" disabled>
                            <span class="material-symbols-rounded">chevron_right</span>
                        </button>
                    </div>

                    <button class="header-button" data-action="refresh-log" data-tooltip="<?php echo $i18n->t('global.refresh'); ?>">
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
                        <th style="width: 50px;"><?php echo $i18n->t('admin.users_module.list.headers.avatar'); ?></th> 
                        <th><?php echo $i18n->t('admin.users_module.list.headers.user'); ?></th>
                        
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
            <p class="state-text"><?php echo $i18n->t('admin.audit.loading'); ?></p>
        </div>
    </div>

</div>