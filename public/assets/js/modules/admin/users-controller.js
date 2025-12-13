/**
 * UsersController.js
 * Lógica para la gestión de usuarios (Tabla, Búsqueda, Filtros, Paginación).
 */

// Estado del módulo
let state = {
    currentSort: 'newest',
    selectedUserId: null,
    viewMode: 'grid',
    usersCache: [],
    currentPage: 1,
    totalPages: 1,
    abortController: null // Para cancelar peticiones fetch pendientes
};

// Referencias DOM (se llenan en init)
let dom = {};

const init = () => {
    // 1. Reiniciar estado
    state = {
        currentSort: 'newest',
        selectedUserId: null,
        viewMode: 'grid',
        usersCache: [],
        currentPage: 1,
        totalPages: 1,
        abortController: null
    };

    // 2. Capturar Referencias DOM
    dom = {
        wrapper: document.getElementById('users-component-wrapper'),
        headerCard: document.getElementById('users-header-card'),
        container: document.getElementById('users-list-container'),
        
        // Toolbar Normal
        toolbarNormal: document.getElementById('toolbar-normal'),
        btnSearch: document.getElementById('btn-toggle-search'),
        btnView: document.getElementById('btn-toggle-view'),
        searchContainer: document.getElementById('toolbar-search-container'),
        searchInput: document.getElementById('user-search-input'),
        
        // Toolbar Acciones
        toolbarActions: document.getElementById('toolbar-actions'),
        btnCancelSel: document.getElementById('btn-cancel-selection'),
        btnEdit: document.getElementById('btn-edit-user'),
        btnRole: document.getElementById('btn-manage-role'),
        
        // Filtros
        btnFilter: document.getElementById('btn-toggle-filter'),
        filterPopover: document.getElementById('filter-popover-menu'),
        sortOptions: document.querySelectorAll('.menu-link[data-sort]'),
        
        // Paginación
        btnPagePrev: document.getElementById('btn-page-prev'),
        btnPageNext: document.getElementById('btn-page-next'),
        inputPage: document.getElementById('users-page-counter'),
    };

    if (!dom.container) return; // Seguridad si el HTML falló al cargar

    // 3. Configurar Event Listeners
    setupEventListeners();

    // 4. Carga inicial
    loadUsers();
};

const destroy = () => {
    // Cancelar peticiones en vuelo
    if (state.abortController) {
        state.abortController.abort();
    }
    
    // Limpiar referencias para evitar memory leaks
    dom = {};
    state.usersCache = [];
    
    // Nota: Los EventListeners agregados a elementos DOM que son eliminados 
    // por el cambio de página (innerHTML) se limpian automáticamente por el navegador.
    // Solo necesitaríamos remover listeners de 'window' o 'document' si los hubiera.
    document.removeEventListener('click', handleOutsideClick);
};

// --- LÓGICA INTERNA ---

const setupEventListeners = () => {
    // Toggle Búsqueda
    if (dom.btnSearch) {
        dom.btnSearch.addEventListener('click', () => {
            const isHidden = (dom.searchContainer.style.display === 'none');
            if (isHidden) {
                dom.searchContainer.style.display = 'flex';
                dom.btnSearch.classList.add('toolbar-btn-active');
                setTimeout(() => dom.searchInput?.focus(), 50);
            } else {
                dom.searchContainer.style.display = 'none';
                dom.btnSearch.classList.remove('toolbar-btn-active');
                if (dom.searchInput) {
                    dom.searchInput.value = '';
                    state.currentPage = 1;
                    loadUsers();
                }
            }
        });
    }

    // Input Búsqueda (Debounce)
    let timeout = null;
    if (dom.searchInput) {
        dom.searchInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                state.currentPage = 1;
                loadUsers();
            }, 400);
        });
    }

    // Cambio de Vista (Grid/Table)
    if (dom.btnView) {
        dom.btnView.addEventListener('click', () => {
            state.viewMode = (state.viewMode === 'grid') ? 'table' : 'grid';
            
            // Actualizar icono
            const iconSpan = dom.btnView.querySelector('span');
            if (iconSpan) iconSpan.textContent = (state.viewMode === 'grid') ? 'table_rows' : 'grid_view';

            // Ajustes CSS
            if (state.viewMode === 'table') {
                dom.wrapper.classList.add('wrapper-full-width');
                if (dom.headerCard) dom.headerCard.style.display = 'none';
            } else {
                dom.wrapper.classList.remove('wrapper-full-width');
                if (dom.headerCard) dom.headerCard.style.display = 'block';
            }
            
            renderUsers(state.usersCache);
        });
    }

    // Filtros
    if (dom.btnFilter && dom.filterPopover) {
        dom.btnFilter.addEventListener('click', (e) => {
            e.stopPropagation();
            dom.filterPopover.classList.toggle('active');
            dom.btnFilter.classList.toggle('toolbar-btn-active');
        });
        
        // Listener global para cerrar al hacer click fuera (se remueve en destroy)
        document.addEventListener('click', handleOutsideClick);

        dom.sortOptions.forEach(option => {
            option.addEventListener('click', () => {
                state.currentSort = option.dataset.sort;
                
                // Actualizar UI del popover
                dom.sortOptions.forEach(opt => {
                    opt.classList.remove('active');
                    opt.querySelector('.check-icon').style.display = 'none';
                });
                option.classList.add('active');
                option.querySelector('.check-icon').style.display = 'flex';
                
                dom.filterPopover.classList.remove('active');
                dom.btnFilter.classList.remove('toolbar-btn-active');
                
                state.currentPage = 1;
                loadUsers();
            });
        });
    }

    // Paginación
    if (dom.btnPagePrev) {
        dom.btnPagePrev.addEventListener('click', () => {
            if (state.currentPage > 1) {
                state.currentPage--;
                loadUsers();
            }
        });
    }
    if (dom.btnPageNext) {
        dom.btnPageNext.addEventListener('click', () => {
            if (state.currentPage < state.totalPages) {
                state.currentPage++;
                loadUsers();
            }
        });
    }
    if (dom.inputPage) {
        dom.inputPage.addEventListener('change', () => {
            let val = parseInt(dom.inputPage.value);
            if (isNaN(val) || val < 1) val = 1;
            if (val > state.totalPages) val = state.totalPages;
            state.currentPage = val;
            loadUsers();
        });
    }

    // Selección de usuarios (Event Delegation)
    dom.container.addEventListener('click', (e) => {
        const card = e.target.closest('.component-entity-card');
        const row = e.target.closest('.component-table-row');
        const target = card || row;
        
        if (target) {
            const id = target.dataset.id;
            const isActive = target.classList.contains('active');

            // Limpiar selección visual previa
            const selector = state.viewMode === 'grid' ? '.component-entity-card.active' : '.component-table-row.active';
            dom.container.querySelectorAll(selector).forEach(el => el.classList.remove('active'));

            if (isActive) {
                toggleActionToolbar(false);
            } else {
                target.classList.add('active');
                state.selectedUserId = id;
                toggleActionToolbar(true);
            }
        }
    });

    // Toolbar de Acciones
    if (dom.btnCancelSel) {
        dom.btnCancelSel.addEventListener('click', () => toggleActionToolbar(false));
    }
    if (dom.btnEdit) {
        dom.btnEdit.addEventListener('click', () => {
            if (state.selectedUserId) alert('Acción: Editar usuario ID ' + state.selectedUserId);
        });
    }
    if (dom.btnRole) {
        dom.btnRole.addEventListener('click', () => {
            if (state.selectedUserId) alert('Acción: Gestionar Rol ID ' + state.selectedUserId);
        });
    }
};

const handleOutsideClick = (e) => {
    if (dom.filterPopover && !dom.filterPopover.contains(e.target) && !dom.btnFilter.contains(e.target)) {
        dom.filterPopover.classList.remove('active');
        dom.btnFilter.classList.remove('toolbar-btn-active');
    }
};

const toggleActionToolbar = (show) => {
    if (show) {
        dom.toolbarNormal.style.display = 'none';
        dom.toolbarActions.style.display = 'flex';
        if (dom.searchContainer) dom.searchContainer.style.display = 'none';
        if (dom.btnSearch) dom.btnSearch.classList.remove('toolbar-btn-active');
    } else {
        dom.toolbarNormal.style.display = 'flex';
        dom.toolbarActions.style.display = 'none';
        state.selectedUserId = null;
        
        // Limpiar visual
        const selector = state.viewMode === 'grid' ? '.component-entity-card.active' : '.component-table-row.active';
        dom.container.querySelectorAll(selector).forEach(el => el.classList.remove('active'));
    }
};

const updatePaginationUI = () => {
    if (dom.inputPage) dom.inputPage.value = state.currentPage;
    if (dom.btnPagePrev) dom.btnPagePrev.disabled = (state.currentPage <= 1);
    if (dom.btnPageNext) dom.btnPageNext.disabled = (state.currentPage >= state.totalPages);
};

// API Calls
const loadUsers = async () => {
    if (state.abortController) state.abortController.abort();
    state.abortController = new AbortController();

    if (dom.container.innerHTML !== '') dom.container.style.opacity = '0.5';
    toggleActionToolbar(false);

    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const formData = new FormData();
    formData.append('action', 'get_users_list');
    formData.append('csrf_token', csrf);
    formData.append('sort', state.currentSort);
    formData.append('page', state.currentPage);
    
    if (dom.searchInput && dom.searchInput.value.trim() !== '') {
        formData.append('search', dom.searchInput.value.trim());
    }

    try {
        const response = await fetch(basePath + 'api/admin_handler.php', {
            method: 'POST',
            body: formData,
            signal: state.abortController.signal
        });
        
        const res = await response.json();
        
        dom.container.style.opacity = '1';

        if (res.status === 'success') {
            state.usersCache = res.data;
            if (res.pagination) {
                state.totalPages = res.pagination.total_pages;
                state.currentPage = res.pagination.current_page;
                updatePaginationUI();
            }
            renderUsers(res.data);
        } else {
            dom.container.innerHTML = `<div style="text-align:center; padding:20px; color:red;">${res.message}</div>`;
        }

    } catch (err) {
        if (err.name === 'AbortError') return;
        console.error(err);
        dom.container.style.opacity = '1';
        dom.container.innerHTML = '<div style="text-align:center; padding:20px; color:red;">Error de conexión</div>';
    }
};

// Render Functions
const renderUsers = (users) => {
    if (!users || users.length === 0) {
        dom.container.className = '';
        dom.container.innerHTML = '<p style="text-align:center; color:#666; padding:40px;">Sin resultados.</p>';
        return;
    }

    const timestamp = new Date().getTime();
    const basePath = window.BASE_PATH || '/ProjectAurora/';

    if (state.viewMode === 'grid') {
        dom.container.className = 'component-list-grid';
        
        let html = '';
        users.forEach(u => {
            const { statusText, statusClass } = getStatusInfo(u.account_status);
            const borderClass = getBorderClass(u.role);
            const avatarSrc = `${basePath}${u.avatar_url}?v=${timestamp}`;
            const fallbackUrl = `${basePath}assets/uploads/avatars/fallback/${(u.id % 5) + 1}.png`;

            html += `
            <div class="component-entity-card" data-id="${u.id}">
                <div class="component-entity-avatar ${borderClass}" title="Rol: ${escapeHtml(u.role)}">
                    <img src="${avatarSrc}" 
                         alt="${escapeHtml(u.username)}"
                         onerror="this.src='${fallbackUrl}'; this.onerror=null;">
                </div>
                
                <div class="component-pill" title="Email">${escapeHtml(u.email)}</div>
                <div class="component-pill">${escapeHtml(u.username)}</div>
                <div class="component-pill" style="text-transform:capitalize;">${escapeHtml(u.role)}</div>
                <div class="component-pill ${statusClass}">${statusText}</div>
                
                <div class="component-pill">
                    <span class="material-symbols-rounded" style="font-size:16px; margin-right:4px;">calendar_today</span>
                    ${formatDate(u.created_at)}
                </div>
            </div>`;
        });
        dom.container.innerHTML = html;

    } else {
        dom.container.className = '';
        
        let html = `
        <div class="component-table-wrapper">
            <table class="component-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Registro</th>
                    </tr>
                </thead>
                <tbody>`;

        users.forEach(u => {
            const { statusText, statusClass } = getStatusInfo(u.account_status); // Reusamos lógica para texto
            
            // Mapeo especial para badges de tabla
            let badgeClass = 'component-badge-default';
            if (u.account_status === 'suspended') badgeClass = 'component-badge-warning';
            if (u.account_status === 'deleted') badgeClass = 'component-badge-danger';
            if (u.account_status === 'active') badgeClass = 'component-badge-success';

            const avatarSrc = `${basePath}${u.avatar_url}?v=${timestamp}`;
            const fallbackUrl = `${basePath}assets/uploads/avatars/fallback/${(u.id % 5) + 1}.png`;

            html += `
            <tr class="component-table-row" data-id="${u.id}">
                <td>
                    <img src="${avatarSrc}" 
                         class="component-table-avatar"
                         alt="${escapeHtml(u.username)}"
                         onerror="this.src='${fallbackUrl}'; this.onerror=null;">
                </td>
                <td><span class="table-text-primary">${escapeHtml(u.username)}</span></td>
                <td><span class="table-text-secondary">${escapeHtml(u.email)}</span></td>
                <td style="text-transform:capitalize;">${escapeHtml(u.role)}</td>
                <td><span class="component-badge ${badgeClass}">${statusText}</span></td>
                <td><span class="table-text-secondary">${formatDate(u.created_at)}</span></td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
        dom.container.innerHTML = html;
    }
};

// Utilities
const escapeHtml = (unsafe) => {
    if (typeof unsafe !== 'string') return unsafe;
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};

const formatDate = (dateString) => {
    const dateObj = new Date(dateString);
    return !isNaN(dateObj) ? dateObj.toLocaleDateString() : '—';
};

const getStatusInfo = (status) => {
    let statusText = 'Activo';
    let statusClass = ''; 
    if (status === 'suspended') { statusText = 'Suspendido'; statusClass = 'pill-warning'; }
    if (status === 'deleted') { statusText = 'Eliminado'; statusClass = 'pill-danger'; }
    return { statusText, statusClass };
};

const getBorderClass = (role) => {
    if (role === 'administrator') return 'component-avatar-border-red';
    if (role === 'moderator') return 'component-avatar-border-blue';
    if (role === 'founder') return 'component-avatar-border-rainbow';
    return 'component-avatar-border-default';
};

export default { init, destroy };