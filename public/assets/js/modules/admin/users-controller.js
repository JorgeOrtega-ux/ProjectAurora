/**
 * public/assets/js/modules/admin/users-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { UserDetailsController } from './user-details-controller.js'; 
import { UserRoleController } from './user-role-controller.js'; 
import { UserStatusController } from './user-status-controller.js'; // IMPORTADO NUEVO
import { navigateTo } from '../../core/url-manager.js';

const AdminAPI = ApiService.Routes.Admin;

let _container = null;    
let _allUsers = [];       
let _filteredUsers = [];  
let _currentPage = 1;     
const _itemsPerPage = 20; 

let _viewMode = 'grid'; 
let _selectedUserId = null; 

export const UsersController = {
    init: () => {
        console.log("UsersController: Inicializando");
        
        // 1. Delegación de sub-vistas
        const detailsContainer = document.querySelector('[data-section="admin-user-details"]');
        if (detailsContainer) {
            UserDetailsController.init();
            return;
        }

        const roleContainer = document.querySelector('[data-section="admin-user-role"]');
        if (roleContainer) {
            UserRoleController.init();
            return;
        }

        // --- NUEVO: Delegación para Status ---
        const statusContainer = document.querySelector('[data-section="admin-user-status"]');
        if (statusContainer) {
            UserStatusController.init();
            return;
        }
        // -------------------------------------

        _container = document.querySelector('[data-section="admin-users"]');

        if (!_container) {
            return;
        }

        _allUsers = [];
        _filteredUsers = [];
        _currentPage = 1;
        _viewMode = 'grid'; 
        _selectedUserId = null;

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
            renderCurrentPage();
        });
    }

    if (btnCloseSelection) {
        btnCloseSelection.addEventListener('click', () => {
            deselectUser();
        });
    }

    // Gestionar acciones de la toolbar
    const groupActions = _container.querySelector('[data-element="toolbar-group-actions"]');
    if (groupActions) {
        const buttons = groupActions.querySelectorAll('.header-button');
        buttons.forEach(btn => {
            const icon = btn.querySelector('.material-symbols-rounded');
            
            // Navegar a Detalles
            if (icon && icon.textContent === 'manage_accounts') {
                btn.addEventListener('click', () => {
                    if (_selectedUserId) {
                        navigateTo('admin/user-details', { id: _selectedUserId });
                    }
                });
            }

            // Navegar a Roles
            if (icon && icon.textContent === 'badge') {
                btn.addEventListener('click', () => {
                    if (_selectedUserId) {
                        navigateTo('admin/user-role', { id: _selectedUserId });
                    }
                });
            }

            // --- NUEVO: Navegar a Status ---
            if (icon && icon.textContent === 'gpp_maybe') {
                btn.addEventListener('click', () => {
                    if (_selectedUserId) {
                        navigateTo('admin/user-status', { id: _selectedUserId });
                    }
                });
            }
            // -----------------------------
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

    const toggleSearch = () => {
        const isActive = toolbarSearch.classList.contains('active');
        isActive ? closeSearch() : openSearch();
    };

    btnToggleSearch.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSearch();
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
            const query = e.target.value.trim().toLowerCase();
            applyFilter(query); 
            updateURL(query);
        });
    }
}

function selectUser(userId) {
    if (_selectedUserId === userId) {
        deselectUser();
        return;
    }

    _selectedUserId = userId;

    const toolbarSearch = _container ? _container.querySelector('[data-element="search-panel"]') : null;
    const btnToggleSearch = _container ? _container.querySelector('[data-action="toggle-search"]') : null;
    if (toolbarSearch) toolbarSearch.classList.remove('active');
    if (btnToggleSearch) btnToggleSearch.classList.remove('active');

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
        if (item.dataset.userId === String(_selectedUserId)) {
            item.classList.add('is-selected');
        } else {
            item.classList.remove('is-selected');
        }
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

function initPaginationEvents() {
    if (!_container) return;

    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (_currentPage > 1) {
                _currentPage--;
                renderCurrentPage();
            }
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            const maxPages = Math.ceil(_filteredUsers.length / _itemsPerPage);
            if (_currentPage < maxPages) {
                _currentPage++;
                renderCurrentPage();
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

    try {
        if(_allUsers.length === 0) {
            listContainer.innerHTML = `
                <div class="state-loading">
                    <div class="spinner-sm"></div>
                    <p class="state-text">${I18n.t('admin.users_module.list.loading')}</p>
                </div>`;
        }

        const res = await ApiService.post(AdminAPI.GetUsers);

        if (res.success) {
            _allUsers = res.users; 
            _filteredUsers = _allUsers; 

            const urlParams = new URLSearchParams(window.location.search);
            const queryFromUrl = urlParams.get('q');

            if (queryFromUrl) {
                const searchInput = _container.querySelector('[data-element="search-input"]');
                const toolbarSearch = _container.querySelector('[data-element="search-panel"]');
                const btnToggleSearch = _container.querySelector('[data-action="toggle-search"]');
                
                if(searchInput) searchInput.value = queryFromUrl;
                if(toolbarSearch) toolbarSearch.classList.add('active');
                if(btnToggleSearch) btnToggleSearch.classList.add('active');

                applyFilter(queryFromUrl.toLowerCase());
            } else {
                renderCurrentPage();
            }

        } else {
            listContainer.innerHTML = `<div class="state-error">${res.message}</div>`;
        }
    } catch (error) {
        console.error(error);
        listContainer.innerHTML = `<div class="state-error">${I18n.t('js.core.connection_error')}</div>`;
    }
}

function applyFilter(query) {
    if (!query) {
        _filteredUsers = _allUsers;
    } else {
        _filteredUsers = _allUsers.filter(user => {
            return (user.username && user.username.toLowerCase().includes(query)) ||
                   (user.email && user.email.toLowerCase().includes(query)) ||
                   (user.role && user.role.toLowerCase().includes(query)) ||
                   (user.uuid && user.uuid.toLowerCase().includes(query));
        });
    }
    
    _currentPage = 1; 
    renderCurrentPage();
}

function renderCurrentPage() {
    if (!_container) return;

    const listContainer = _container.querySelector('[data-component="user-list"]');
    const paginationPanel = _container.querySelector('[data-element="pagination-wrapper"]');
    const infoText = _container.querySelector('[data-element="pagination-info"]');
    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');

    if (!listContainer) return;

    if (_filteredUsers.length === 0) {
        listContainer.innerHTML = `<div class="state-empty">${I18n.t('admin.users_module.list.empty')}</div>`;
        if (paginationPanel) paginationPanel.style.display = 'none';
        return;
    }

    const totalItems = _filteredUsers.length;
    const totalPages = Math.ceil(totalItems / _itemsPerPage);
    
    if (_currentPage < 1) _currentPage = 1;
    if (_currentPage > totalPages) _currentPage = totalPages;

    const startIndex = (_currentPage - 1) * _itemsPerPage;
    const endIndex = Math.min(startIndex + _itemsPerPage, totalItems);
    
    const usersToShow = _filteredUsers.slice(startIndex, endIndex);

    if (_viewMode === 'table') {
        renderListAsTable(usersToShow, listContainer);
    } else {
        renderListAsGrid(usersToShow, listContainer);
    }

    attachSelectionListeners(listContainer);

    if (paginationPanel) {
        paginationPanel.style.display = 'flex'; 
        if (infoText) {
            infoText.textContent = `${_currentPage}/${totalPages}`;
        }
        if (btnPrev) btnPrev.disabled = (_currentPage === 1);
        if (btnNext) btnNext.disabled = (_currentPage === totalPages);
    }
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

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}