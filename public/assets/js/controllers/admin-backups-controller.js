// public/assets/js/controllers/admin-backups-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class AdminBackupsController {
    constructor() {
        this.currentViewMode = 'cards'; // Estado en memoria, sin persistencia
        this.init();
    }

    init() {
        window.addEventListener('viewLoaded', (e) => {
            if (e.detail.url.includes('/admin/backups')) {
                this.setupView();
            }
        });

        if (window.location.pathname.includes('/admin/backups')) {
            this.setupView();
        }

        document.body.addEventListener('click', (e) => {
            const view = document.getElementById('admin-backups-view');
            if (!view) return;

            if (e.target.closest('[data-action="admin-toggle-view"]')) {
                const newMode = this.currentViewMode === 'cards' ? 'table' : 'cards';
                this.applyViewMode(newMode);
                return;
            }

            const card = e.target.closest('.js-admin-backup-card');
            if (card) {
                this.handleCardSelection(card);
            }

            if (e.target.closest('[data-action="admin-toggle-search-bkp"]')) {
                this.toggleSearchToolbar();
            }

            if (e.target.closest('[data-action="admin-clear-selection-bkp"]')) {
                this.clearSelection();
            }

            const btnCreate = e.target.closest('[data-action="admin-create-backup"]');
            if (btnCreate) {
                this.createBackup(btnCreate);
            }

            if (e.target.closest('[data-action="admin-delete-backup"]')) {
                this.deleteBackup();
            }

            if (e.target.closest('[data-action="admin-restore-backup"]')) {
                this.restoreBackup();
            }
        });

        document.body.addEventListener('input', (e) => {
            if (e.target.id === 'admin-bkp-search-input') {
                this.filterBackups(e.target.value.toLowerCase().trim());
            }
        });
    }

    setupView() {
        // Siempre resetear a la vista de tarjetas al cargar
        this.currentViewMode = 'cards';
        this.applyViewMode(this.currentViewMode);
    }

    applyViewMode(mode) {
        const list = document.getElementById('admin-data-list-backups');
        const icon = document.getElementById('view-toggle-icon-bkp');
        const wrapper = document.querySelector('#admin-backups-view .component-wrapper');
        const header = document.querySelector('#admin-backups-view .component-header-card');

        if (!list) return;
        
        this.currentViewMode = mode;
        
        if (mode === 'table') {
            list.classList.add('component-data-list--table');
            if (icon) icon.textContent = 'grid_view';
            if (wrapper) wrapper.classList.add('component-wrapper--full');
            if (header) header.style.display = 'none';
        } else {
            list.classList.remove('component-data-list--table');
            if (icon) icon.textContent = 'table_rows';
            if (wrapper) wrapper.classList.remove('component-wrapper--full'); 
            if (header) header.style.display = ''; // Limpia el estilo para mostrarlo
        }
    }

    handleCardSelection(card) {
        const isSelected = card.classList.contains('selected');
        this.clearSelection(); 
        
        const selectionToolbar = document.getElementById('toolbar-selection-bkp');
        const searchToolbar = document.getElementById('toolbar-search-bkp');
        if (searchToolbar) searchToolbar.classList.remove('active');

        if (!isSelected) {
            card.classList.add('selected');
            if (selectionToolbar) selectionToolbar.classList.add('active');
        }
    }

    clearSelection() {
        document.querySelectorAll('.js-admin-backup-card.selected').forEach(c => c.classList.remove('selected'));
        const selectionToolbar = document.getElementById('toolbar-selection-bkp');
        if (selectionToolbar) selectionToolbar.classList.remove('active');
    }

    toggleSearchToolbar() {
        const searchToolbar = document.getElementById('toolbar-search-bkp');
        this.clearSelection();

        if (searchToolbar.classList.contains('active')) {
            searchToolbar.classList.remove('active');
            const input = document.getElementById('admin-bkp-search-input');
            if(input) {
                input.value = '';
                this.filterBackups('');
            }
        } else {
            searchToolbar.classList.add('active');
            setTimeout(() => document.getElementById('admin-bkp-search-input').focus(), 100);
        }
    }

    filterBackups(query) {
        const cards = document.querySelectorAll('.js-admin-backup-card');
        cards.forEach(card => {
            const filename = card.dataset.filename.toLowerCase();
            if (filename.includes(query)) {
                card.style.display = 'flex'; 
            } else {
                card.style.display = 'none';
            }
        });
    }

    async createBackup(btn) {
        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.ADMIN.CREATE_BACKUP, { csrf_token: csrfToken });
            if (res.success) {
                Toast.show(res.message, 'success');
                setTimeout(() => window.location.reload(), 1500); 
            } else {
                Toast.show(res.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (error) {
            Toast.show('Error interno de red al crear copia de seguridad.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async deleteBackup() {
        const selectedCard = document.querySelector('.js-admin-backup-card.selected');
        if (!selectedCard) return;
        const filename = selectedCard.dataset.filename;

        if (!confirm(`¿Estás seguro de eliminar permanentemente la copia: ${filename}? Esta acción no se puede deshacer.`)) return;

        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';

        try {
            const res = await ApiService.post(API_ROUTES.ADMIN.DELETE_BACKUP, { filename, csrf_token: csrfToken });
            if (res.success) {
                Toast.show(res.message, 'success');
                selectedCard.remove();
                this.clearSelection();
                
                if (document.querySelectorAll('.js-admin-backup-card').length === 0) {
                    const emptyState = document.getElementById('admin-empty-state-bkp');
                    if (emptyState) emptyState.style.display = 'block';
                }
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error de red al intentar eliminar la copia de seguridad.', 'error');
        }
    }

    async restoreBackup() {
        const selectedCard = document.querySelector('.js-admin-backup-card.selected');
        if (!selectedCard) return;
        const filename = selectedCard.dataset.filename;

        if (!confirm(`ATENCIÓN: Esto sobrescribirá la base de datos actual con los datos del archivo "${filename}". Todos los cambios recientes se perderán. ¿Deseas continuar?`)) return;

        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';

        try {
            Toast.show('Iniciando restauración de base de datos, por favor no recargues la página...', 'success');
            const res = await ApiService.post(API_ROUTES.ADMIN.RESTORE_BACKUP, { filename, csrf_token: csrfToken });
            if (res.success) {
                Toast.show(res.message, 'success');
                this.clearSelection();
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error crítico durante la restauración de la base de datos.', 'error');
        }
    }
}