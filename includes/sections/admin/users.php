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
            /* SIN BORDE DIRECTO AQUÍ, usaremos ::before */
        }

        /* Pseudo-elemento para el borde (IGUAL QUE EN HEADER) */
        .user-avatar-circle::before {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            border: 3px solid transparent; /* Grosor del borde */
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
            z-index: 1; /* Debajo del borde */
        }

        /* SISTEMA DE COLORES DE ROL (Aplicado al ::before) */
        
        .role-border-user::before {
            border-color: #cccccc;
        }

        .role-border-moderator::before {
            border-color: #0000FF;
        }

        .role-border-admin::before {
            border-color: #FF0000;
        }

        /* Fundador: Rainbow Gradient */
        .role-border-founder::before {
            border: none;
            background-image: conic-gradient(from 300deg, #D32029 0deg 90deg, #206BD3 90deg 210deg, #28A745 210deg 300deg, #FFC107 300deg 360deg);
            mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #fff 0);
            -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #fff 0);
        }

        /* Píldoras de datos */
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
    </style>

    <div class="toolbar">
        <button class="component-button" style="border:none;">
            <span class="material-symbols-rounded">filter_list</span>
        </button>
        <div style="width: 1px; height: 20px; background: #eee;"></div>
        <button class="component-button" style="border:none;">
            <span class="material-symbols-rounded">add</span>
            Crear
        </button>
    </div>

    <div class="component-wrapper">
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

                // Asignación de clases de rol
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