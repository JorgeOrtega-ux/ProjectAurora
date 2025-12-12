<?php
// includes/sections/admin/users.php
?>
<div class="section-content active" data-section="admin/users">
    
    <div class="component-wrapper">
        
        <div class="component-toolbar-wrapper">
            
            <div class="component-toolbar" id="toolbar-normal">
                <button class="header-button" id="btn-toggle-search" title="<?= __('global.search_placeholder') ?>">
                    <span class="material-symbols-rounded">search</span>
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

        <div class="component-header-card">
            <h1 class="component-page-title" data-lang-key="admin.users.title"><?= __('admin.users.title') ?></h1>
            <p class="component-page-description" data-lang-key="admin.users.desc"><?= __('admin.users.desc') ?></p>
        </div>

        <div id="users-list-container" class="component-list-grid">
            <div class="loader-container">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        /* --- DOM REFS --- */
        const toolbarNormal = document.getElementById('toolbar-normal');
        const btnSearch = document.getElementById('btn-toggle-search');
        const searchContainer = document.getElementById('toolbar-search-container');
        const searchInput = document.getElementById('user-search-input');
        const btnFilter = document.getElementById('btn-toggle-filter');
        const filterPopover = document.getElementById('filter-popover-menu');
        const sortOptions = filterPopover ? filterPopover.querySelectorAll('.menu-link[data-sort]') : [];
        
        const toolbarActions = document.getElementById('toolbar-actions');
        const btnCancelSel = document.getElementById('btn-cancel-selection');
        const btnEdit = document.getElementById('btn-edit-user');
        const btnRole = document.getElementById('btn-manage-role');

        const container = document.getElementById('users-list-container');
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? csrfMeta.content : '';
        const basePath = window.BASE_PATH || '/ProjectAurora/';

        let currentSort = 'newest';
        let selectedUserId = null; 

        /* =========================================
           LÓGICA DE UI Y TOOLBARS
           ========================================= */

        function toggleActionToolbar(show) {
            if (show) {
                toolbarNormal.style.display = 'none';
                toolbarActions.style.display = 'flex';
                if(searchContainer) searchContainer.style.display = 'none';
                if(btnSearch) btnSearch.classList.remove('toolbar-btn-active');
            } else {
                toolbarNormal.style.display = 'flex';
                toolbarActions.style.display = 'none';
                selectedUserId = null;
                container.querySelectorAll('.component-entity-card.active').forEach(c => c.classList.remove('active'));
            }
        }

        if(btnCancelSel) {
            btnCancelSel.addEventListener('click', () => toggleActionToolbar(false));
        }

        if(btnSearch && searchContainer) {
            btnSearch.addEventListener('click', () => {
                const isHidden = (searchContainer.style.display === 'none');
                if(isHidden) {
                    searchContainer.style.display = 'flex';
                    btnSearch.classList.add('toolbar-btn-active');
                    if(searchInput) setTimeout(() => searchInput.focus(), 50);
                } else {
                    searchContainer.style.display = 'none';
                    btnSearch.classList.remove('toolbar-btn-active');
                    if(searchInput) searchInput.value = ''; 
                    loadUsers(); 
                }
            });
            let timeout = null;
            if(searchInput){
                searchInput.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => loadUsers(), 400);
                });
            }
        }

        if (btnFilter && filterPopover) {
            btnFilter.addEventListener('click', (e) => {
                e.stopPropagation();
                filterPopover.classList.toggle('active');
                btnFilter.classList.toggle('toolbar-btn-active');
            });
            document.addEventListener('click', (e) => {
                if (!filterPopover.contains(e.target) && !btnFilter.contains(e.target)) {
                    filterPopover.classList.remove('active');
                    btnFilter.classList.remove('toolbar-btn-active');
                }
            });
            sortOptions.forEach(option => {
                option.addEventListener('click', () => {
                    currentSort = option.dataset.sort;
                    sortOptions.forEach(opt => {
                        opt.classList.remove('active');
                        opt.querySelector('.check-icon').style.display = 'none';
                    });
                    option.classList.add('active');
                    option.querySelector('.check-icon').style.display = 'flex';
                    filterPopover.classList.remove('active');
                    btnFilter.classList.remove('toolbar-btn-active');
                    loadUsers();
                });
            });
        }

        /* =========================================
           LÓGICA DE SELECCIÓN Y ACCIONES
           ========================================= */

        container.addEventListener('click', (e) => {
            const card = e.target.closest('.component-entity-card');
            
            if (card) {
                const id = card.dataset.id;

                if (card.classList.contains('active')) {
                    toggleActionToolbar(false);
                } else {
                    container.querySelectorAll('.component-entity-card.active').forEach(c => c.classList.remove('active'));
                    card.classList.add('active');
                    selectedUserId = id;
                    toggleActionToolbar(true); 
                }
            }
        });

        if(btnEdit) btnEdit.addEventListener('click', () => {
            if(selectedUserId) alert('Acción: Editar usuario ID ' + selectedUserId);
        });
        if(btnRole) btnRole.addEventListener('click', () => {
            if(selectedUserId) alert('Acción: Gestionar Rol ID ' + selectedUserId);
        });

        /* =========================================
           LÓGICA DE CARGA DE DATOS
           ========================================= */
        function loadUsers() {
            if(container.innerHTML !== '') container.style.opacity = '0.5';
            toggleActionToolbar(false);

            const formData = new FormData();
            formData.append('action', 'get_users_list');
            formData.append('csrf_token', csrf);
            formData.append('sort', currentSort);
            
            if (searchInput && searchInput.value.trim() !== '') {
                formData.append('search', searchInput.value.trim());
            }

            fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                container.style.opacity = '1';
                if(res.status === 'success') {
                    renderUsers(res.data);
                } else {
                    container.innerHTML = `<div style="text-align:center; padding:20px; color:red;">${res.message}</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                container.style.opacity = '1';
                container.innerHTML = '<div style="text-align:center; padding:20px; color:red;">Error de conexión</div>';
            });
        }

        function renderUsers(users) {
            if(!users || users.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#666; padding:40px;">Sin resultados.</p>';
                return;
            }

            let html = '';
            const timestamp = new Date().getTime(); 

            users.forEach(u => {
                const initial = u.username.charAt(0).toUpperCase();
                const dateObj = new Date(u.created_at);
                const dateStr = !isNaN(dateObj) ? dateObj.toLocaleDateString() : '—';
                const avatarSrc = basePath + u.avatar_url + '?v=' + timestamp;

                let statusText = 'Activo';
                let statusClass = ''; 
                if(u.account_status === 'suspended') { statusText = 'Suspendido'; statusClass = 'pill-warning'; }
                if(u.account_status === 'deleted') { statusText = 'Eliminado'; statusClass = 'pill-danger'; }

                let borderClass = 'component-avatar-border-default';
                if(u.role === 'administrator') borderClass = 'component-avatar-border-red';
                else if (u.role === 'moderator') borderClass = 'component-avatar-border-blue';
                else if (u.role === 'founder') borderClass = 'component-avatar-border-rainbow';

                html += `
                <div class="component-entity-card" data-id="${u.id}">
                    <div class="component-entity-avatar ${borderClass}" title="Rol: ${u.role}">
                        <img src="${avatarSrc}" alt="${u.username}" onerror="this.style.display='none'; this.parentNode.innerText='${initial}';">
                    </div>
                    
                    <div class="component-pill" title="Email">${u.email}</div>
                    <div class="component-pill">${u.username}</div>
                    <div class="component-pill" style="text-transform:capitalize;">${u.role}</div>
                    <div class="component-pill ${statusClass}">${statusText}</div>
                    
                    <div class="component-pill">
                        <span class="material-symbols-rounded" style="font-size:16px; margin-right:4px;">calendar_today</span>
                        ${dateStr}
                    </div>
                </div>`;
            });
            container.innerHTML = html;
        }

        loadUsers();
    })();
    </script>
</div>