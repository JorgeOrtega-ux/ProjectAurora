/**
 * public/assets/js/modules/admin/users-controller.js
 * Versión Refactorizada: Paginación y Búsqueda Server-Side
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { UserDetailsController } from './user-details-controller.js'; 
import { UserRoleController } from './user-role-controller.js'; 
import { UserStatusController } from './user-status-controller.js';
import { navigateTo } from '../../core/url-manager.js';

const AdminAPI = ApiService.Routes.Admin;

let _container = null;    
let _usersData = [];      // Datos de la página actual
let _currentPage = 1;     
let _totalPages = 1;
const _itemsPerPage = 20; 

let _viewMode = 'grid'; 
let _selectedUserId = null; 
let _searchQuery = '';
let _searchTimeout = null; // Para debounce

export const UsersController = {
    init: () => {
        console.log("UsersController: Inicializado (Server-Side Pagination)");
        
        // 1. Delegación de sub-vistas
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

        // Limpieza de estado
        _usersData = [];
        _currentPage = 1;
        _totalPages = 1;
        _viewMode = 'grid'; 
        _selectedUserId = null;
        _searchQuery = '';

        // Recuperar parámetros de URL iniciales
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('q')) {
            _searchQuery = urlParams.get('q');
            // Pre-llenar el input y abrir la barra
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

    // Gestionar acciones de la toolbar
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
        // Opcional: Limpiar búsqueda al cerrar
        // _searchQuery = '';
        // searchInput.value = '';
        // loadUsers();
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

        // BÚSQUEDA CON DEBOUNCE
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            if (_searchTimeout) clearTimeout(_searchTimeout);
            
            _searchTimeout = setTimeout(() => {
                if (query !== _searchQuery) {
                    _searchQuery = query;
                    _currentPage = 1; // Reset a primera página al buscar
                    updateURL(query);
                    loadUsers();
                }
            }, 400); // 400ms de espera
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

// === LÓGICA DE CARGA ===

async function loadUsers() {
    if (!_container) return;
    
    const listContainer = _container.querySelector('[data-component="user-list"]');
    if (!listContainer) return;

    // Mostrar loader solo si es carga inicial o cambio de página drástico
    // Para búsquedas rápidas se puede optimizar, pero mantenemos simple por ahora
    listContainer.innerHTML = `
        <div class="state-loading">
            <div class="spinner-sm"></div>
            <p class="state-text">${I18n.t('admin.users_module.list.loading')}</p>
        </div>`;

    const formData = new FormData();
    formData.append('page', _currentPage);
    formData.append('limit', _itemsPerPage);
    if (_searchQuery) formData.append('search', _searchQuery);

    try {
        const res = await ApiService.post(AdminAPI.GetUsers, formData);

        if (res.success) {
            _usersData = res.users;
            
            // Actualizar metadatos de paginación
            if (res.pagination) {
                _currentPage = res.pagination.current;
                _totalPages = res.pagination.total_pages;
                updatePaginationUI(res.pagination.total_items);
            }

            renderList();

        } else {
            listContainer.innerHTML = `<div class="state-error">${res.message}</div>`;
        }
    } catch (error) {
        console.error(error);
        listContainer.innerHTML = `<div class="state-error">${I18n.t('js.core.connection_error')}</div>`;
    }
}

function updatePaginationUI(totalItems) {
    const paginationPanel = _container.querySelector('[data-element="pagination-wrapper"]');
    const infoText = _container.querySelector('[data-element="pagination-info"]');
    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');

    if (paginationPanel) {
        if (totalItems > 0) {
            paginationPanel.style.display = 'flex';
            if (infoText) {
                infoText.textContent = `${_currentPage}/${_totalPages}`;
            }
            if (btnPrev) btnPrev.disabled = (_currentPage <= 1);
            if (btnNext) btnNext.disabled = (_currentPage >= _totalPages);
        } else {
            paginationPanel.style.display = 'none';
        }
    }
}

function renderList() {
    if (!_container) return;
    const listContainer = _container.querySelector('[data-component="user-list"]');
    if (!listContainer) return;

    if (_usersData.length === 0) {
        listContainer.innerHTML = `<div class="state-empty">${I18n.t('admin.users_module.list.empty')}</div>`;
        return;
    }

    if (_viewMode === 'table') {
        renderListAsTable(_usersData, listContainer);
    } else {
        renderListAsGrid(_usersData, listContainer);
    }

    attachSelectionListeners(listContainer);
}

// === RENDERS VISUALES (IGUAL QUE ANTES) ===

function renderListAsGrid(users, listContainer) {
    let html = '';

    users.forEach(user => {
        const date = new Date(user.created_at);
        const formattedDate = date.toLocaleDateString();
        const selectedClass = (_selectedUserId == user.id) ? 'is-selected' : '';

        html += `
        <div class="component-card ${selectedClass}" data-user-id="${user.id}">
            <div class="component-list-item-content">
                
                <div class="component-card__profile-picture component-avatar--list" 
                     data-role="${user.role}">
                    <img src="${user.avatar_src}" class="component-card__avatar-image" loading="lazy">
                </div>

                <span class="component-badge" data-tooltip="Username">
                    ${escapeHtml(user.username)}
                </span>

                <span class="component-badge" data-tooltip="Email"> 
                    ${escapeHtml(user.email)}
                </span>

                <span class="component-badge" data-tooltip="Rol">
                    ${user.role} 
                </span>

                <span class="component-badge" data-tooltip="Estado">
                    ${user.account_status}
                </span>

                <span class="component-badge" data-tooltip="UUID">
                    ${user.uuid}
                </span>

                <span class="component-badge" data-tooltip="Fecha Registro">
                    ${formattedDate}
                </span>

            </div>
        </div>`;
    });

    listContainer.innerHTML = html;
    listContainer.scrollTop = 0;
}

function renderListAsTable(users, listContainer) {
    let rows = '';
    
    users.forEach(user => {
        const date = new Date(user.created_at);
        const formattedDate = date.toLocaleDateString();
        const selectedClass = (_selectedUserId == user.id) ? 'is-selected' : '';

        rows += `
        <tr class="table-row-item ${selectedClass}" data-user-id="${user.id}" style="cursor: pointer;">
            <td>
                <div class="component-card__profile-picture component-avatar--list" 
                     data-role="${user.role}" style="width: 32px; height: 32px; min-width: 32px;">
                    <img src="${user.avatar_src}" class="component-card__avatar-image" loading="lazy">
                </div>
            </td>
            <td><strong>${escapeHtml(user.username)}</strong></td>
            <td>${escapeHtml(user.email)}</td>
            <td><span class="component-badge" style="height: 24px; font-size: 12px;">${user.role}</span></td>
            <td>${user.account_status}</td>
            <td style="font-family: monospace; font-size: 12px;">${user.uuid}</td>
            <td>${formattedDate}</td>
        </tr>`;
    });

    const tableHtml = `
    <div class="component-table-wrapper">
        <table class="component-table">
            <thead>
                <tr>
                    <th style="width: 50px;">${I18n.t('admin.users_module.list.headers.avatar')}</th>
                    <th>${I18n.t('admin.users_module.list.headers.user')}</th>
                    <th>${I18n.t('admin.users_module.list.headers.email')}</th>
                    <th>${I18n.t('admin.users_module.list.headers.role')}</th>
                    <th>${I18n.t('admin.users_module.list.headers.status')}</th>
                    <th>${I18n.t('admin.users_module.list.headers.uuid')}</th>
                    <th>${I18n.t('admin.users_module.list.headers.created')}</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    </div>
    `;

    listContainer.innerHTML = tableHtml;
    listContainer.scrollTop = 0;
}

// === GESTIÓN DE SELECCIÓN ===

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
        const text = I18n.t('admin.users_module.toolbar.selected_count').replace('%s', '1');
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
        btnElement.dataset.tooltip = I18n.t('admin.users_module.toolbar.view_grid');
    } else {
        if (wrapper) wrapper.classList.remove('component-wrapper--full');
        if (headerCard) headerCard.classList.remove('d-none');
        if (toolbarTitle) toolbarTitle.classList.add('d-none'); 
        if (iconSpan) iconSpan.textContent = 'grid_view';
        btnElement.dataset.tooltip = I18n.t('admin.users_module.toolbar.view_table');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}