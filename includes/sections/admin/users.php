<?php
// includes/sections/admin/users.php
?>
<div class="section-content active" data-section="admin/users">
    
    <div class="component-wrapper" id="users-component-wrapper">
        
        <div class="component-toolbar-wrapper">
            
            <div class="component-toolbar" id="toolbar-normal">
                <button class="header-button" id="btn-toggle-search" title="<?= __('global.search_placeholder') ?>">
                    <span class="material-symbols-rounded">search</span>
                </button>

                <button class="header-button" id="btn-toggle-view" title="Cambiar Vista">
                    <span class="material-symbols-rounded">table_rows</span>
                </button>
                
                <div style="width: 1px; height: 20px; background: #eee;"></div>
                
                <div class="component-filter-wrapper">
                    <button class="header-button" id="btn-toggle-filter" title="Ordenar">
                        <span class="material-symbols-rounded">filter_list</span>
                    </button>
                    
                    <div class="popover-module component-filter-popover" id="filter-popover-menu">
                        <div class="menu-list">
                            <div style="padding: 8px 12px; font-size: 11px; color: #999; font-weight: 700; text-transform: uppercase;">
                                Ordenar por
                            </div>
                            <div class="menu-link active" data-sort="newest">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">schedule</span></div>
                                <div class="menu-link-text">Más recientes</div>
                                <div class="menu-link-icon check-icon"><span class="material-symbols-rounded">check</span></div>
                            </div>
                            <div class="menu-link" data-sort="oldest">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">history</span></div>
                                <div class="menu-link-text">Más antiguos</div>
                                <div class="menu-link-icon check-icon" style="display:none;"><span class="material-symbols-rounded">check</span></div>
                            </div>
                            <div class="menu-link" data-sort="az">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">sort_by_alpha</span></div>
                                <div class="menu-link-text">Nombre (A-Z)</div>
                                <div class="menu-link-icon check-icon" style="display:none;"><span class="material-symbols-rounded">check</span></div>
                            </div>
                            <div class="menu-link" data-sort="za">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">sort_by_alpha</span></div>
                                <div class="menu-link-text">Nombre (Z-A)</div>
                                <div class="menu-link-icon check-icon" style="display:none;"><span class="material-symbols-rounded">check</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="width: 1px; height: 20px; background: #eee;"></div>

                <div class="component-input-wrapper">
                    <div class="component-counter-control">
                        <div class="counter-group left">
                            <button type="button" class="counter-btn" id="btn-page-prev">
                                <span class="material-symbols-rounded">keyboard_arrow_left</span>
                            </button>
                        </div>
                        <input type="number" id="users-page-counter" class="counter-input" min="1" value="1">
                        <div class="counter-group right">
                            <button type="button" class="counter-btn" id="btn-page-next">
                                <span class="material-symbols-rounded">keyboard_arrow_right</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="component-toolbar" id="toolbar-actions" style="display: none;">
                
                <button class="header-button" id="btn-cancel-selection" title="Cancelar selección">
                    <span class="material-symbols-rounded">close</span>
                </button>

                <div style="width: 1px; height: 20px; background: #eee;"></div>

                <button class="header-button" id="btn-edit-user" title="Editar Cuenta">
                    <span class="material-symbols-rounded">edit</span>
                </button>
                
                <button class="header-button" id="btn-manage-role" title="Gestionar Rol">
                    <span class="material-symbols-rounded">shield_person</span>
                </button>

            </div>
            
            <div class="component-toolbar-secondary" id="toolbar-search-container" style="display: none;">
                <div class="search-container" style="width: 100%;">
                    <div class="search-icon">
                        <span class="material-symbols-rounded">search</span>
                    </div>
                    <div class="search-input">
                        <input type="text" id="user-search-input" placeholder="<?= __('global.search_placeholder') ?>" data-lang-placeholder="global.search_placeholder">
                    </div>
                </div>
            </div>
        </div>

        <div class="component-header-card" id="users-header-card">
            <h1 class="component-page-title" data-lang-key="admin.users.title"><?= __('admin.users.title') ?></h1>
            <p class="component-page-description" data-lang-key="admin.users.desc"><?= __('admin.users.desc') ?></p>
        </div>

        <div id="users-list-container">
            <div class="loader-container">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>