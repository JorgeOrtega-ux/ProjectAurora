<?php
// includes/sections/admin/users.php
?>
<div class="component-wrapper" data-section="admin-users">
    
    <div class="component-toolbar-wrapper">
        
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group" data-element="toolbar-group-default">
                
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-action="toggle-search" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.search_tooltip'); ?>">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                    
                    <button class="header-button" data-action="change-view" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.change_view'); ?>">
                        <span class="material-symbols-rounded">grid_view</span>
                    </button>

                    <div data-element="toolbar-title" class="component-toolbar-title d-none">
                        <?php echo $i18n->t('menu.admin.users'); ?>
                    </div>
                </div>
                
                <div class="component-toolbar__side component-toolbar__side--right">
                    
                    <div data-element="pagination-wrapper" class="component-pagination">
                        
                        <button class="header-button component-pagination-btn" data-action="prev-page" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.prev_page'); ?>" disabled>
                            <span class="material-symbols-rounded">chevron_left</span>
                        </button>

                        <span data-element="pagination-info" class="component-pagination-info">
                            1/1
                        </span>

                        <button class="header-button component-pagination-btn" data-action="next-page" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.next_page'); ?>" disabled>
                            <span class="material-symbols-rounded">chevron_right</span>
                        </button>
                    </div>

                </div>
            </div>

            <div class="toolbar-group d-none" data-element="toolbar-group-actions">
                
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.manage_account'); ?>">
                        <span class="material-symbols-rounded">manage_accounts</span>
                    </button>
                    
                    <button class="header-button" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.manage_role'); ?>">
                        <span class="material-symbols-rounded">badge</span>
                    </button>

                    <button class="header-button" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.account_status'); ?>">
                        <span class="material-symbols-rounded">gpp_maybe</span>
                    </button>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <span class="selection-info-text" data-element="selection-indicator"><?php echo sprintf($i18n->t('admin.users_module.toolbar.selected_count'), 1); ?></span>
                    <button class="header-button" data-action="close-selection" data-tooltip="<?php echo $i18n->t('admin.users_module.toolbar.cancel_selection'); ?>">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>

            </div>

        </div>

        <div class="component-toolbar component-toolbar--secondary" data-element="search-panel">
            <div class="search-content w-100">
                <div class="search-icon">
                    <span class="material-symbols-rounded">search</span>
                </div>
                <div class="search-input">
                    <input type="text" 
                           placeholder="<?php echo $i18n->t('admin.users_module.search_placeholder'); ?>" 
                           data-element="search-input" 
                           autocomplete="off">
                </div>
            </div>
        </div>

    </div>

    <div class="component-header-card" data-element="page-header">
        <h1 class="component-page-title"><?php echo $i18n->t('menu.admin.users'); ?></h1>
        <p class="component-page-description"><?php echo $i18n->t('admin.users_module.subtitle'); ?></p>
    </div>
    
    <div class="component-list" data-component="user-list">
        <div class="state-loading">
            <div class="spinner-sm"></div>
            <p class="state-text"><?php echo $i18n->t('admin.users_module.list.loading'); ?></p>
        </div>
    </div>
</div>