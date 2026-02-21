// public/assets/js/controllers/admin-users-controller.js

export class AdminUsersController {
    constructor() {
        this.itemsPerPage = 10;
        this.currentPage = 1;
        this.allCards = [];
        this.filteredCards = [];
        
        this.filters = {
            roles: [],
            status: []
        };
        
        this.searchQuery = '';
        this.init();
    }

    init() {
        window.addEventListener('viewLoaded', (e) => {
            if (e.detail.url.includes('/admin/users')) {
                this.setupView();
            }
        });

        if (window.location.pathname.includes('/admin/users')) {
            this.setupView();
        }

        this.bindEvents();
    }

    setupView() {
        this.allCards = Array.from(document.querySelectorAll('.js-admin-user-card'));
        this.filteredCards = [...this.allCards];
        this.currentPage = 1;
        this.updatePagination();
    }

    bindEvents() {
        // --- LISTENER DE CAPTURA PARA RESETEAR LA VISTA DEL FILTRO ---
        // Se asegura de reiniciar el slider del filtro cuando el MainController abre el modal dinámico
        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action="toggleModule"]');
            if (btn && btn.dataset.target === 'moduleAdminUsersFilters') {
                this.resetFilterView();
            }
        }, true);

        // --- LISTENER PRINCIPAL ---
        document.body.addEventListener('click', (e) => {
            const card = e.target.closest('.js-admin-user-card');
            if (card && document.getElementById('admin-users-view')) {
                this.handleCardSelection(card);
            }

            // Toolbar: Toggle Búsqueda
            if (e.target.closest('[data-action="admin-toggle-search"]')) {
                this.toggleSearchToolbar();
            }

            // Toolbar: Limpiar Selección
            if (e.target.closest('[data-action="admin-clear-selection"]')) {
                this.clearSelection();
            }

            // Filtros: Navegar a submenú
            const navBtn = e.target.closest('[data-action="admin-filter-nav"]');
            if (navBtn) {
                this.slideFilterView(navBtn.dataset.target, navBtn.querySelector('.component-menu-link-text span').textContent);
            }

            // Filtros: Botón Volver
            if (e.target.closest('#filter-btn-back')) {
                this.resetFilterView();
            }

            // Filtros: Aplicar
            if (e.target.closest('[data-action="admin-filter-apply"]')) {
                // Selecciona dinámicamente el módulo correcto
                const module = document.querySelector('[data-module="moduleAdminUsersFilters"]');
                if (module) module.classList.add('disabled');
                this.applyFiltersAndSearch();
            }

            // Filtros: Limpiar todo
            if (e.target.closest('[data-action="admin-filter-clear"]')) {
                this.filters.roles = [];
                this.filters.status = [];
                document.querySelectorAll('.filter-checkbox-wrapper input').forEach(cb => cb.checked = false);
                this.updateFilterDots();
                this.applyFiltersAndSearch();
            }

            // Paginación
            if (e.target.closest('#admin-pag-prev')) {
                if (this.currentPage > 1) { this.currentPage--; this.updatePagination(); }
            }
            if (e.target.closest('#admin-pag-next')) {
                const maxPages = Math.ceil(this.filteredCards.length / this.itemsPerPage);
                if (this.currentPage < maxPages) { this.currentPage++; this.updatePagination(); }
            }
        });

        // Búsqueda en tiempo real e input de checkboxes
        document.body.addEventListener('input', (e) => {
            if (e.target.id === 'admin-user-search-input') {
                this.searchQuery = e.target.value.toLowerCase().trim();
                this.applyFiltersAndSearch();
            }

            if (e.target.classList.contains('filter-checkbox')) {
                const type = e.target.dataset.type; 
                const val = e.target.value;
                if (e.target.checked) {
                    if (!this.filters[type].includes(val)) this.filters[type].push(val);
                } else {
                    this.filters[type] = this.filters[type].filter(item => item !== val);
                }
                this.updateFilterDots();
            }
        });
    }

    handleCardSelection(card) {
        const isSelected = card.classList.contains('selected');
        this.clearSelection(); 
        
        const selectionToolbar = document.getElementById('toolbar-selection');
        const searchToolbar = document.getElementById('toolbar-search');
        if (searchToolbar) searchToolbar.classList.remove('active');

        if (!isSelected) {
            card.classList.add('selected');
            if (selectionToolbar) selectionToolbar.classList.add('active');
        }
    }

    clearSelection() {
        document.querySelectorAll('.js-admin-user-card.selected').forEach(c => c.classList.remove('selected'));
        const selectionToolbar = document.getElementById('toolbar-selection');
        if (selectionToolbar) selectionToolbar.classList.remove('active');
    }

    toggleSearchToolbar() {
        const searchToolbar = document.getElementById('toolbar-search');
        this.clearSelection();

        if (searchToolbar.classList.contains('active')) {
            searchToolbar.classList.remove('active');
            const input = document.getElementById('admin-user-search-input');
            if(input) {
                input.value = '';
                this.searchQuery = '';
                this.applyFiltersAndSearch();
            }
        } else {
            searchToolbar.classList.add('active');
            setTimeout(() => document.getElementById('admin-user-search-input').focus(), 100);
        }
    }

    applyFiltersAndSearch() {
        this.clearSelection();
        this.filteredCards = [];

        this.allCards.forEach(card => {
            const name = card.dataset.username;
            const email = card.dataset.email;
            const uuid = card.dataset.uuid;
            const role = card.dataset.role;
            const status = card.dataset.status;

            const matchesSearch = this.searchQuery === '' || 
                                  name.includes(this.searchQuery) || 
                                  email.includes(this.searchQuery) || 
                                  uuid.includes(this.searchQuery);

            const matchesRole = this.filters.roles.length === 0 || this.filters.roles.includes(role);
            const matchesStatus = this.filters.status.length === 0 || this.filters.status.includes(status);

            if (matchesSearch && matchesRole && matchesStatus) {
                this.filteredCards.push(card);
            } else {
                card.style.display = 'none';
            }
        });

        const filterBtn = document.getElementById('btn-admin-filter');
        if (filterBtn) {
            if (this.filters.roles.length > 0 || this.filters.status.length > 0) {
                filterBtn.style.color = 'var(--action-primary)';
                filterBtn.style.borderColor = 'var(--action-primary)';
            } else {
                filterBtn.style.color = '';
                filterBtn.style.borderColor = '';
            }
        }

        this.currentPage = 1;
        this.updatePagination();
    }

    updatePagination() {
        const total = this.filteredCards.length;
        const maxPages = Math.ceil(total / this.itemsPerPage) || 1;
        const info = document.getElementById('admin-pag-info');
        const btnPrev = document.getElementById('admin-pag-prev');
        const btnNext = document.getElementById('admin-pag-next');
        const emptyState = document.getElementById('admin-empty-state');

        if (info) info.textContent = `${total === 0 ? 0 : this.currentPage} / ${maxPages}`;
        if (btnPrev) btnPrev.disabled = this.currentPage === 1;
        if (btnNext) btnNext.disabled = this.currentPage === maxPages || total === 0;

        if (total === 0) {
            if (emptyState) emptyState.style.display = 'block';
        } else {
            if (emptyState) emptyState.style.display = 'none';
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;

            this.filteredCards.forEach((card, index) => {
                if (index >= start && index < end) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    }

    resetFilterView() {
        const viewport = document.getElementById('filter-viewport');
        const btnBack = document.getElementById('filter-btn-back');
        const title = document.getElementById('filter-module-title');
        
        if(viewport) viewport.style.transform = 'translateX(0)';
        if(btnBack) btnBack.style.display = 'none';
        if(title) title.textContent = window.t ? window.t('admin.filter.title') : 'Filtros';
    }

    slideFilterView(targetType, newTitle) {
        const viewport = document.getElementById('filter-viewport');
        const btnBack = document.getElementById('filter-btn-back');
        const title = document.getElementById('filter-module-title');
        const container = document.getElementById('options-container');

        if (!viewport || !container) return;

        let html = '';
        if (targetType === 'view-roles') {
            const roles = ['user', 'moderator', 'administrator', 'founder'];
            roles.forEach(role => {
                const isChecked = this.filters.roles.includes(role) ? 'checked' : '';
                const label = window.t ? window.t(`admin.filter.role.${role}`) : role;
                html += `
                    <label class="filter-checkbox-wrapper">
                        <input type="checkbox" class="filter-checkbox" data-type="roles" value="${role}" ${isChecked}>
                        <span style="font-size: 15px;">${label}</span>
                    </label>
                `;
            });
        } else if (targetType === 'view-status') {
            const statuses = ['active', 'suspended', 'deleted'];
            statuses.forEach(status => {
                const isChecked = this.filters.status.includes(status) ? 'checked' : '';
                const label = window.t ? window.t(`admin.filter.status.${status}`) : status;
                html += `
                    <label class="filter-checkbox-wrapper">
                        <input type="checkbox" class="filter-checkbox" data-type="status" value="${status}" ${isChecked}>
                        <span style="font-size: 15px;">${label}</span>
                    </label>
                `;
            });
        }

        container.innerHTML = html;
        title.textContent = newTitle;
        btnBack.style.display = 'flex';
        viewport.style.transform = 'translateX(-50%)'; 
    }

    updateFilterDots() {
        const dotRoles = document.getElementById('dot-roles');
        const dotStatus = document.getElementById('dot-status');
        
        if (dotRoles) dotRoles.parentElement.classList.toggle('has-active-filters', this.filters.roles.length > 0);
        if (dotStatus) dotStatus.parentElement.classList.toggle('has-active-filters', this.filters.status.length > 0);
    }
}