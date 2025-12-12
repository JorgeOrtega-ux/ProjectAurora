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
            border: 2px solid #000;
            border-radius: 16px;
            width: 100%;
        }

        /* Avatar Base (Contenedor) */
        .user-avatar-circle {
            position: relative;
            width: 40px;
            height: 40px;
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
            border: 1.5px solid #000;
            border-radius: 999px;
            background-color: #fff;
            font-size: 13px;
            font-weight: 600;
            color: #000;
            white-space: nowrap;
        }

        /* --- NUEVOS ESTILOS PARA TOOLBAR WRAPPER --- */
        .toolbar-wrapper {
            position: sticky;
            top: 16px;
            z-index: 500;
            width: 300px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.3s ease;
        }

        /* Ajuste para que la toolbar original se comporte bien dentro del wrapper */
        .toolbar-wrapper .toolbar {
            width: 100%;
            margin: 0;
            position: relative;
            top: 0;
        }

        .toolbar-secondary {
            width: 100%;
            height: 45px;
            background-color: #fff;
            border: 1px solid #00000020;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            padding: 0 12px;
            animation: slideDown 0.2s ease-out forwards;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .toolbar-btn-active {
            background-color: #f0f0f0 !important;
            border-color: #00000040 !important;
        }
    </style>

    <div class="component-wrapper">
        
        <div class="toolbar-wrapper">
            <div class="toolbar">
                <button class="component-button" id="btn-toggle-search" style="border:none;">
                    <span class="material-symbols-rounded">search</span>
                </button>
                <div style="width: 1px; height: 20px; background: #eee;"></div>
                <button class="component-button" style="border:none;">
                    <span class="material-symbols-rounded">filter_list</span>
                </button>
            </div>
            
            <div class="toolbar-secondary" id="toolbar-search-container" style="display: none;">
                <span class="material-symbols-rounded" style="color: #999; margin-right: 8px;">search</span>
                <input type="text" id="user-search-input" class="component-text-input" placeholder="Buscar usuario..." style="border: none; padding: 0; height: 100%;">
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
        /* --- LÓGICA DE TOOLBAR --- */
        const btnSearch = document.getElementById('btn-toggle-search');
        const searchContainer = document.getElementById('toolbar-search-container');
        const searchInput = document.getElementById('user-search-input');

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
                    if(searchInput) searchInput.value = ''; // Opcional: limpiar al cerrar
                }
            });
        }

        /* --- LÓGICA DE CARGA DE USUARIOS (Existente) --- */
        const container = document.getElementById('users-list-container');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const basePath = window.BASE_PATH || '/ProjectAurora/';

        function loadUsers() {
            const formData = new FormData();
            formData.append('action', 'get_users_list');
            formData.append('csrf_token', csrf);

            fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    renderUsers(res.data);
                } else {
                    container.innerHTML = `<div style="text-align:center; width:100%; padding:20px; color:red;">Error: ${res.message}</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = `<div style="text-align:center; width:100%; padding:20px; color:red;">Error de conexión</div>`;
            });
        }

        function renderUsers(users) {
            if(!users || users.length === 0) {
                container.innerHTML = `<p style="text-align:center; color:#666;">No hay usuarios registrados.</p>`;
                return;
            }

            let html = '';
            const timestamp = new Date().getTime(); 

            users.forEach(u => {
                const initial = u.username.charAt(0).toUpperCase();
                const dateObj = new Date(u.created_at);
                const dateStr = dateObj.toLocaleDateString();
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

        loadUsers();
    })();
    </script>
</div>