<?php
// includes/sections/admin/backups.php
?>
<div class="component-wrapper" data-section="admin-backups">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group" data-element="toolbar-group-default">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-action="change-view" data-tooltip="<?php echo $i18n->t('admin.backups.toolbar.view'); ?>">
                        <span class="material-symbols-rounded">grid_view</span>
                    </button>

                    <div class="component-toolbar-title d-none" data-element="toolbar-title">
                        <?php echo $i18n->t('menu.admin.backups'); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" data-nav="admin/backups/config" data-tooltip="<?php echo $i18n->t('admin.backups.toolbar.config'); ?>">
                        <span class="material-symbols-rounded">settings_motion_mode</span>
                    </button>
                    <button class="header-button" id="btn-create-backup" data-tooltip="<?php echo $i18n->t('admin.backups.toolbar.create'); ?>">
                        <span class="material-symbols-rounded">add</span>
                    </button>
                </div>
            </div>

            <div class="toolbar-group d-none" data-element="toolbar-group-actions">
                <div class="component-toolbar__side component-toolbar__side--left">
                    
                    <button class="header-button" data-action="view-selected" data-tooltip="<?php echo $i18n->t('admin.backups.actions.view'); ?>">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>

                    <button class="header-button" data-action="restore-selected" data-tooltip="<?php echo $i18n->t('admin.backups.actions.restore'); ?>">
                        <span class="material-symbols-rounded">history</span>
                    </button>
                    
                    <button class="header-button" data-action="delete-selected" data-tooltip="<?php echo $i18n->t('admin.backups.actions.delete'); ?>" style="color: var(--color-error);">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <span class="selection-info-text" data-element="selection-indicator"><?php echo sprintf($i18n->t('admin.backups.selected_count'), 0); ?></span>
                    <button class="header-button" data-action="close-selection" data-tooltip="<?php echo $i18n->t('global.cancel'); ?>">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="component-header-card" data-element="page-header">
        <h1 class="component-page-title"><?php echo $i18n->t('menu.admin.backups'); ?></h1>
        <p class="component-page-description"><?php echo $i18n->t('admin.backups.desc'); ?></p>
    </div>

    <div class="component-list" data-component="backup-list">
        <div class="state-loading">
            <div class="spinner-sm"></div>
            <p class="state-text"><?php echo $i18n->t('admin.backups.loading'); ?></p>
        </div>
    </div>

</div>