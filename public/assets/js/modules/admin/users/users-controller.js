/**
 * public/assets/js/modules/admin/users/users-controller.js
 * Versión Refactorizada: Arquitectura Signal & Interceptors
 */

import { ApiService } from '../../../core/services/api-service.js';
import { I18nManager } from '../../../core/utils/i18n-manager.js';
import { UserDetailsController } from './user-details-controller.js'; 
import { UserRoleController } from './user-role-controller.js'; 
import { UserStatusController } from './user-status-controller.js';
import { navigateTo } from '../../../core/utils/url-manager.js';

const AdminAPI = ApiService.Routes.Admin;

let _container = null;    
let _usersData = [];      
let _currentPage = 1;     
let _totalPages = 1;
const _itemsPerPage = 20; 

let _viewMode = 'grid'; 
let _selectedUserId = null; 
let _searchQuery = '';
let _searchTimeout = null;

export const UsersController = {
    init: () => {
        console.log("UsersController: Inicializado (Safe Mode)");
        
        // Sub-controladores si estamos en vistas de detalle
        if (document.querySelector('[data-section="admin-user-details"]')) {
            UserDetailsController.init();
            return;
        }
        if (document.querySelector('[data-section="admin-user-role"]')) {
            UserRoleController.init();
            return;
        }
        if (document.querySelector('[data-section="admin-user-status"]')) {
            UserStatusController.init();
            return;
        }

        _container = document.querySelector('[data-section="admin-users"]');
        if (!_container) return;

        _usersData = [];
        _currentPage = 1;
        _totalPages = 1;
        _viewMode = 'grid'; 
        _selectedUserId = null;
        _searchQuery = '';

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('q')) {
            _searchQuery = urlParams.get('q');
            const searchInput = _container.querySelector('[data-element="search-input"]');
            const toolbarSearch = _container.querySelector('[data-element="search-panel"]');
            const btnToggleSearch = _container.querySelector('[data-action="toggle-search"]');
            
            if(searchInput) searchInput.value = _searchQuery;
            if(toolbarSearch) toolbarSearch.classList.add('active');
            if(btnToggleSearch) btnToggleSearch.classList.add('active');
        }

        initToolbarEvents();
        initPaginationEvents();
        loadUsers();
    }
};

function initToolbarEvents() {
    if (!_container) return;

    const btnToggleSearch = _container.querySelector('[data-action="toggle-search"]');
    const toolbarSearch = _container.querySelector('[data-element="search-panel"]');
    const searchInput = _container.querySelector('[data-element="search-input"]');
    const btnChangeView = _container.querySelector('[data-action="change-view"]');
    const btnCloseSelection = _container.querySelector('[data-action="close-selection"]');

    if (btnChangeView) {
        btnChangeView.addEventListener('click', () => {
            _viewMode = (_viewMode === 'grid') ? 'table' : 'grid';
            updateViewUI(btnChangeView);
            renderList();
        });
    }

    if (btnCloseSelection) {
        btnCloseSelection.addEventListener('click', deselectUser);
    }

    const groupActions = _container.querySelector('[data-element="toolbar-group-actions"]');
    if (groupActions) {
        const buttons = groupActions.querySelectorAll('.header-button');
        buttons.forEach(btn => {
            const icon = btn.querySelector('.material-symbols-rounded');
            
            if (icon && icon.textContent === 'manage_accounts') {
                btn.addEventListener('click', () => { if (_selectedUserId) navigateTo('admin/user-details', { id: _selectedUserId }); });
            }
            if (icon && icon.textContent === 'badge') {
                btn.addEventListener('click', () => { if (_selectedUserId) navigateTo('admin/user-role', { id: _selectedUserId }); });
            }
            if (icon && icon.textContent === 'gpp_maybe') {
                btn.addEventListener('click', () => { if (_selectedUserId) navigateTo('admin/user-status', { id: _selectedUserId }); });
            }
        });
    }

    if (!btnToggleSearch || !toolbarSearch) return;

    const openSearch = () => {
        if (_selectedUserId) deselectUser();
        toolbarSearch.classList.add('active');
        btnToggleSearch.classList.add('active');
        if (searchInput) setTimeout(() => searchInput.focus(), 50);
    };

    const closeSearch = () => {
        toolbarSearch.classList.remove('active');
        btnToggleSearch.classList.remove('active');
    };

    btnToggleSearch.addEventListener('click', (e) => {
        e.stopPropagation();
        toolbarSearch.classList.contains('active') ? closeSearch() : openSearch();
    });

    toolbarSearch.addEventListener('click', (e) => e.stopPropagation());

    document.addEventListener('click', (e) => {
        if (document.body.contains(toolbarSearch) && 
            toolbarSearch.classList.contains('active') && 
            !toolbarSearch.contains(e.target) && 
            !btnToggleSearch.contains(e.target)) {
            closeSearch();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSearch();
        });

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            if (_searchTimeout) clearTimeout(_searchTimeout);
            
            _searchTimeout = setTimeout(() => {
                if (query !== _searchQuery) {
                    _searchQuery = query;
                    _currentPage = 1; 
                    updateURL(query);
                    loadUsers();
                }
            }, 400); 
        });
    }
}

function initPaginationEvents() {
    if (!_container) return;

    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (_currentPage > 1) {
                _currentPage--;
                loadUsers();
            }
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            if (_currentPage < _totalPages) {
                _currentPage++;
                loadUsers();
            }
        });
    }
}

function updateURL(query) {
    const url = new URL(window.location);
    if (query && query.length > 0) {
        url.searchParams.set('q', query);
    } else {
        url.searchParams.delete('q');
    }
    window.history.replaceState(history.state, '', url);
}

async function loadUsers() {
    if (!_container) return;
    
    const listContainer = _container.querySelector('[data-component="user-list"]');
    if (!listContainer) return;

    listContainer.innerHTML = `
        <div class="state-loading">
            <div class="spinner-sm"></div>
            <p class="state-text">${I18nManager.t('admin.users_module.list.loading') || 'Cargando usuarios...'}</p>
        </div>`;

    const formData = new FormData();
    formData.append('page', _currentPage);
    formData.append('limit', _itemsPerPage);
    if (_searchQuery) formData.append('search', _searchQuery);

    try {
        // Signal added: Cancela la búsqueda anterior si el usuario escribe rápido
        const res = await ApiService.post(AdminAPI.GetUsers, formData, { signal: window.PAGE_SIGNAL });

        if (res.success) {
            _usersData = res.users;
            
            if (res.pagination) {
                _currentPage = res.pagination.current;
                _totalPages = res.pagination.total_pages;
                updatePaginationUI(res.pagination.total_items);
            } else {
                 updatePaginationUI(0);
            }

            renderList();

        } else {
            listContainer.innerHTML = `<div class="state-error">${res.message}</div>`;
        }
    } catch (error) {
        // Ignorar aborts
        if (error.isAborted) return;
        
        console.error(error);
        listContainer.innerHTML = `<div class="state-error">${I18nManager.t('js.core.connection_error') || 'Error de conexión'}</div>`;
    }
}

function updatePaginationUI(totalItems) {
    const paginationPanel = _container.querySelector('[data-element="pagination-wrapper"]');
    const infoText = _container.querySelector('[data-element="pagination-info"]');
    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');

    if (paginationPanel) {
        paginationPanel.classList.remove('d-none');
        paginationPanel.style.display = 'flex';

        if (infoText) {
            const displayTotal = _totalPages > 0 ? _totalPages : 1;
            infoText.textContent = `${_currentPage}/${displayTotal}`;
        }

        if (btnPrev) btnPrev.disabled = (_currentPage <= 1);
        if (btnNext) btnNext.disabled = (_currentPage >= _totalPages);
    }
}

function renderList() {
    if (!_container) return;
    const listContainer = _container.querySelector('[data-component="user-list"]');
    if (!listContainer) return;

    if (_usersData.length === 0) {
        listContainer.innerHTML = `<div class="state-empty">${I18nManager.t('admin.users_module.list.empty') || 'No se encontraron usuarios.'}</div>`;
        return;
    }

    if (_viewMode === 'table') {
        renderListAsTable(_usersData, listContainer);
    } else {
        renderListAsGrid(_usersData, listContainer);
    }

    attachSelectionListeners(listContainer);
}

function renderListAsGrid(users, listContainer) {
    listContainer.innerHTML = ''; 

    users.forEach(user => {
        const date = new Date(user.created_at);
        const formattedDate = date.toLocaleDateString();
        const statusLabel = I18nManager.t(`admin.user_status.status.${user.account_status}`) || user.account_status;

        const card = document.createElement('div');
        card.className = 'component-card';
        if (_selectedUserId == user.id) card.classList.add('is-selected');
        card.dataset.userId = user.id;

        const content = document.createElement('div');
        content.className = 'component-list-item-content';

        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'component-card__profile-picture component-avatar--list';
        avatarDiv.dataset.role = user.role;
        
        const img = document.createElement('img');
        img.src = user.avatar_src;
        img.className = 'component-card__avatar-image';
        img.loading = 'lazy';
        img.alt = user.username;
        avatarDiv.appendChild(img);
        content.appendChild(avatarDiv);

        const createBadge = (text, tooltipKey) => {
            const span = document.createElement('span');
            span.className = 'component-badge';
            span.dataset.tooltip = I18nManager.t(tooltipKey);
            span.textContent = text; 
            return span;
        };

        content.appendChild(createBadge(user.username, 'admin.users_module.list.headers.user'));
        content.appendChild(createBadge(user.email, 'admin.users_module.list.headers.email'));
        content.appendChild(createBadge(user.role, 'admin.users_module.list.headers.role'));
        content.appendChild(createBadge(statusLabel, 'admin.users_module.list.headers.status'));
        content.appendChild(createBadge(user.uuid, 'admin.users_module.list.headers.uuid'));
        content.appendChild(createBadge(formattedDate, 'admin.users_module.list.headers.created'));

        card.appendChild(content);
        listContainer.appendChild(card);
    });

    listContainer.scrollTop = 0;
}

function renderListAsTable(users, listContainer) {
    listContainer.innerHTML = ''; 

    const tableWrapper = document.createElement('div');
    tableWrapper.className = 'component-table-wrapper';

    const table = document.createElement('table');
    table.className = 'component-table';

    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    const headers = [
        { width: '50px', text: I18nManager.t('admin.users_module.list.headers.avatar') },
        { text: I18nManager.t('admin.users_module.list.headers.user') },
        { text: I18nManager.t('admin.users_module.list.headers.email') },
        { text: I18nManager.t('admin.users_module.list.headers.role') },
        { text: I18nManager.t('admin.users_module.list.headers.status') },
        { text: I18nManager.t('admin.users_module.list.headers.uuid') },
        { text: I18nManager.t('admin.users_module.list.headers.created') }
    ];

    headers.forEach(h => {
        const th = document.createElement('th');
        if (h.width) th.style.width = h.width;
        th.textContent = h.text;
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    
    users.forEach(user => {
        const date = new Date(user.created_at);
        const formattedDate = date.toLocaleDateString();
        const statusLabel = I18nManager.t(`admin.user_status.status.${user.account_status}`) || user.account_status;

        const tr = document.createElement('tr');
        tr.className = 'table-row-item';
        if (_selectedUserId == user.id) tr.classList.add('is-selected');
        tr.dataset.userId = user.id;
        tr.style.cursor = 'pointer';

        const tdAvatar = document.createElement('td');
        const divAvatar = document.createElement('div');
        divAvatar.className = 'component-card__profile-picture component-avatar--list';
        divAvatar.dataset.role = user.role;
        divAvatar.style.width = '32px';
        divAvatar.style.height = '32px';
        divAvatar.style.minWidth = '32px';
        
        const img = document.createElement('img');
        img.src = user.avatar_src;
        img.className = 'component-card__avatar-image';
        img.loading = 'lazy';
        
        divAvatar.appendChild(img);
        tdAvatar.appendChild(divAvatar);
        tr.appendChild(tdAvatar);

        const addCell = (text, isBold = false, isMono = false, isBadge = false) => {
            const td = document.createElement('td');
            if (isBadge) {
                const span = document.createElement('span');
                span.className = 'component-badge';
                span.style.height = '24px';
                span.style.fontSize = '12px';
                span.textContent = text; 
                td.appendChild(span);
            } else if (isBold) {
                const strong = document.createElement('strong');
                strong.textContent = text; 
                td.appendChild(strong);
            } else {
                td.textContent = text; 
            }
            
            if (isMono) {
                td.style.fontFamily = 'monospace';
                td.style.fontSize = '12px';
            }
            tr.appendChild(td);
        };

        addCell(user.username, true);
        addCell(user.email);
        addCell(user.role, false, false, true); 
        addCell(statusLabel);
        addCell(user.uuid, false, true); 
        addCell(formattedDate);

        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    tableWrapper.appendChild(table);
    listContainer.appendChild(tableWrapper);
    
    listContainer.scrollTop = 0;
}

function selectUser(userId) {
    if (_selectedUserId === userId) {
        deselectUser();
        return;
    }
    _selectedUserId = userId;
    toggleToolbarGroups(true);
    highlightSelectedUser();
}

function deselectUser() {
    _selectedUserId = null;
    toggleToolbarGroups(false);
    highlightSelectedUser();
}

function toggleToolbarGroups(isSelectionActive) {
    if (!_container) return;
    const groupDefault = _container.querySelector('[data-element="toolbar-group-default"]');
    const groupActions = _container.querySelector('[data-element="toolbar-group-actions"]');
    const selectionIndicator = _container.querySelector('[data-element="selection-indicator"]');

    if (selectionIndicator) {
        const text = (I18nManager.t('admin.users_module.toolbar.selected_count') || '%s seleccionados').replace('%s', '1');
        selectionIndicator.textContent = text;
    }

    if (groupDefault && groupActions) {
        if (isSelectionActive) {
            groupDefault.classList.add('d-none');
            groupActions.classList.remove('d-none');
        } else {
            groupDefault.classList.remove('d-none');
            groupActions.classList.add('d-none');
        }
    }
}

function highlightSelectedUser() {
    if (!_container) return;
    const allItems = _container.querySelectorAll('[data-user-id]');
    allItems.forEach(item => {
        if (item.dataset.userId === String(_selectedUserId)) item.classList.add('is-selected');
        else item.classList.remove('is-selected');
    });
}

function attachSelectionListeners(listContainer) {
    const items = listContainer.querySelectorAll('.component-card, .table-row-item');
    items.forEach(item => {
        item.addEventListener('click', () => {
            const userId = item.dataset.userId;
            if(userId) selectUser(userId);
        });
    });
}

function updateViewUI(btnElement) {
    if (!_container) return;
    const wrapper = _container;
    const headerCard = _container.querySelector('[data-element="page-header"]');
    const iconSpan = btnElement.querySelector('.material-symbols-rounded');
    const toolbarTitle = _container.querySelector('[data-element="toolbar-title"]');

    if (_viewMode === 'table') {
        if (wrapper) wrapper.classList.add('component-wrapper--full');
        if (headerCard) headerCard.classList.add('d-none');
        if (toolbarTitle) toolbarTitle.classList.remove('d-none'); 
        if (iconSpan) iconSpan.textContent = 'table_rows'; 
        btnElement.dataset.tooltip = I18nManager.t('admin.users_module.toolbar.view_grid') || 'Vista en Cuadrícula';
    } else {
        if (wrapper) wrapper.classList.remove('component-wrapper--full');
        if (headerCard) headerCard.classList.remove('d-none');
        if (toolbarTitle) toolbarTitle.classList.add('d-none'); 
        if (iconSpan) iconSpan.textContent = 'grid_view';
        btnElement.dataset.tooltip = I18nManager.t('admin.users_module.toolbar.view_table') || 'Vista en Tabla';
    }
}