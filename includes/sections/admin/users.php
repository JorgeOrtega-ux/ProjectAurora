<?php
// includes/sections/admin/users.php
?>
<div class="section-content active" data-section="admin/users">
    
    <style>
        .users-list-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .user-card-mockup {
            display: flex;
            flex-wrap: wrap;       /* Elementos bajan si no caben */
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #fff;
            border: 1px solid #00000020;
            border-radius: 12px;
            width: 100%;
        }

        /* Avatar Base (Contenedor) */
        .user-avatar-circle {
            position: relative;
            width: 35px;
            height: 35px;
            background-color: #f5f5fa; /* Fondo por si falla imagen */
            color: #555;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        /* Pseudo-elemento para el borde */
        .user-avatar-circle::before {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            border: 2px solid transparent; /* Grosor del borde */
            pointer-events: none;
            z-index: 2;
        }

        /* Imagen dentro del círculo */
        .user-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
            position: relative;
            z-index: 1; 
        }

        /* SISTEMA DE COLORES DE ROL */
        .role-border-user::before { border-color: #cccccc; }
        .role-border-moderator::before { border-color: #0000FF; }
        .role-border-admin::before { border-color: #FF0000; }
        .role-border-founder::before {
            border: none;
            background-image: conic-gradient(from 300deg, #D32029 0deg 90deg, #206BD3 90deg 210deg, #28A745 210deg 300deg, #FFC107 300deg 360deg);
            mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #fff 0);
            -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #fff 0);
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            height: 36px;
            padding: 0 16px;
            border: 1.5px solid #00000020;
            border-radius: 999px;
            background-color: #fff;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            white-space: nowrap;
        }

        /* Estilos específicos para el dropdown de filtro en el toolbar */
        .filter-wrapper {
            position: relative;
        }
        
        .filter-popover {
            top: calc(100% + 8px); 
            right: 0;
            left: auto;
            width: 240px;
        }

    </style>

    <div class="component-wrapper">
        
        <div class="toolbar-wrapper">
            <div class="toolbar">
                <button class="header-button" id="btn-toggle-search">
                    <span class="material-symbols-rounded">search</span>
                </button>
                
                <div style="width: 1px; height: 20px; background: #eee;"></div>
                
                <div class="filter-wrapper">
                    <button class="header-button" id="btn-toggle-filter">
                        <span class="material-symbols-rounded">filter_list</span>
                    </button>
                    
                    <div class="popover-module filter-popover" id="filter-popover-menu">
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
            
            <div class="toolbar-secondary" id="toolbar-search-container" style="display: none;">
                <div class="search-container" style="width: 100%;">
                    <div class="search-icon">
                        <span class="material-symbols-rounded">search</span>
                    </div>
                    <div class="search-input">
                        <input type="text" id="user-search-input" placeholder="Buscar..." data-lang-placeholder="global.search_placeholder">
                    </div>
                </div>
            </div>
        </div>

        <div class="component-header-card">
            <h1 class="component-page-title" data-lang-key="admin.users.title"><?= __('admin.users.title') ?></h1>
            <p class="component-page-description" data-lang-key="admin.users.desc"><?= __('admin.users.desc') ?></p>
        </div>

        <div id="users-list-container" class="users-list-grid">
            <div class="loader-container">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        /* --- REFERENCIAS DOM --- */
        const btnSearch = document.getElementById('btn-toggle-search');
        const searchContainer = document.getElementById('toolbar-search-container');
        const searchInput = document.getElementById('user-search-input');
        
        const btnFilter = document.getElementById('btn-toggle-filter');
        const filterPopover = document.getElementById('filter-popover-menu');
        const sortOptions = filterPopover.querySelectorAll('.menu-link[data-sort]');
        
        const container = document.getElementById('users-list-container');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const basePath = window.BASE_PATH || '/ProjectAurora/';

        let currentSort = 'newest';

        /* --- LÓGICA DE TOOLBAR: BÚSQUEDA --- */
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
                    loadUsers(); // Recargar al limpiar búsqueda
                }
            });

            // Búsqueda en tiempo real (debounce simple)
            let timeout = null;
            if(searchInput){
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        loadUsers(); 
                    }, 400);
                });
            }
        }

        /* --- LÓGICA DE TOOLBAR: FILTRO --- */
        if (btnFilter && filterPopover) {
            // Toggle Popover
            btnFilter.addEventListener('click', (e) => {
                e.stopPropagation();
                const isActive = filterPopover.classList.contains('active');
                
                // Cerrar otros dropdowns si los hubiera (opcional)
                
                if (isActive) {
                    filterPopover.classList.remove('active');
                    btnFilter.classList.remove('toolbar-btn-active');
                } else {
                    filterPopover.classList.add('active');
                    btnFilter.classList.add('toolbar-btn-active');
                }
            });

            // Cerrar al hacer click fuera
            document.addEventListener('click', (e) => {
                if (!filterPopover.contains(e.target) && !btnFilter.contains(e.target)) {
                    filterPopover.classList.remove('active');
                    btnFilter.classList.remove('toolbar-btn-active');
                }
            });

            // Manejo de clicks en opciones
            sortOptions.forEach(option => {
                option.addEventListener('click', () => {
                    // Actualizar variable
                    currentSort = option.dataset.sort;

                    // Actualizar UI del dropdown (clase active y check icon)
                    sortOptions.forEach(opt => {
                        opt.classList.remove('active');
                        const check = opt.querySelector('.check-icon');
                        if(check) check.style.display = 'none';
                    });
                    
                    option.classList.add('active');
                    const check = option.querySelector('.check-icon');
                    if(check) check.style.display = 'flex';

                    // Cerrar popover
                    filterPopover.classList.remove('active');
                    btnFilter.classList.remove('toolbar-btn-active');

                    // Recargar lista
                    loadUsers();
                });
            });
        }

        /* --- LÓGICA DE CARGA DE USUARIOS --- */
        function loadUsers() {
            // Mostrar loader si ya había contenido
            if(container.innerHTML !== '') {
                container.style.opacity = '0.5';
            }

            const formData = new FormData();
            formData.append('action', 'get_users_list');
            formData.append('csrf_token', csrf);
            formData.append('sort', currentSort); // Enviar ordenamiento
            
            if (searchInput && searchInput.value.trim() !== '') {
                formData.append('search', searchInput.value.trim()); // Enviar búsqueda
            }

            fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                container.style.opacity = '1';
                if(res.status === 'success') {
                    renderUsers(res.data);
                } else {
                    container.innerHTML = `<div style="text-align:center; width:100%; padding:20px; color:red;">Error: ${res.message}</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                container.style.opacity = '1';
                container.innerHTML = `<div style="text-align:center; width:100%; padding:20px; color:red;">Error de conexión</div>`;
            });
        }

        function renderUsers(users) {
            if(!users || users.length === 0) {
                container.innerHTML = `<p style="text-align:center; color:#666; padding: 40px;">No se encontraron usuarios.</p>`;
                return;
            }

            let html = '';
            const timestamp = new Date().getTime(); 

            users.forEach(u => {
                const initial = u.username.charAt(0).toUpperCase();
                // Manejo seguro de fecha
                const dateObj = new Date(u.created_at);
                const dateStr = !isNaN(dateObj) ? dateObj.toLocaleDateString() : 'Fecha desconocida';
                
                const avatarSrc = basePath + u.avatar_url + '?v=' + timestamp;

                let statusText = 'Activo';
                if(u.account_status === 'suspended') statusText = 'Suspendido';
                if(u.account_status === 'deleted') statusText = 'Eliminado';

                let roleBorderClass = 'role-border-user';
                if(u.role === 'administrator') roleBorderClass = 'role-border-admin';
                else if (u.role === 'moderator') roleBorderClass = 'role-border-moderator';
                else if (u.role === 'founder') roleBorderClass = 'role-border-founder';

                html += `
                <div class="user-card-mockup">
                    <div class="user-avatar-circle ${roleBorderClass}" title="Rol: ${u.role}">
                        <img src="${avatarSrc}" 
                             alt="${u.username}" 
                             class="user-avatar-img"
                             onerror="this.style.display='none'; this.parentNode.innerText='${initial}';">
                    </div>
                    
                    <div class="user-pill">
                        ${u.email}
                    </div>
                    <div class="user-pill">
                        ${u.username}
                    </div>
                    <div class="user-pill" style="text-transform:capitalize;">
                        Rol: ${u.role}
                    </div>
                    <div class="user-pill">
                        ${u.uuid}
                    </div>
                    <div class="user-pill">
                        Estado: ${statusText}
                    </div>
                    <div class="user-pill">
                        ${dateStr}
                    </div>
                </div>
                `;
            });

            container.innerHTML = html;
        }

        // Carga inicial
        loadUsers();
    })();
    </script>
</div>