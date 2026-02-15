/* public/assets/js/modules/admin/metadata-controller.js */

import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js';
import { ToastManager } from '../../core/components/toast-manager.js';

export class MetadataController {
    
    static init() {
        const section = document.querySelector('[data-section="admin-metadata"]');
        if (!section) return;

        this.section = section;
        this.currentType = 'category'; // Default
        this.cacheDOM();
        this.bindEvents();
        this.loadData();
    }

    static cacheDOM() {
        this.dom = {
            tabs: this.section.querySelectorAll('[data-tab]'),
            form: this.section.querySelector('#metadata-form'),
            listContainer: this.section.querySelector('#metadata-list'),
            typeInput: this.section.querySelector('#meta-type'),
            nameInput: this.section.querySelector('#meta-name'),
            extraInput: this.section.querySelector('#meta-extra'),
            actorGroup: this.section.querySelector('#actor-type-group'),
            formTitle: this.section.querySelector('#form-title'),
            emptyState: this.section.querySelector('#empty-state'),
            loadingState: this.section.querySelector('#loading-state')
        };
    }

    static bindEvents() {
        // Navegación de Tabs
        this.dom.tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                // UI Toggle
                this.dom.tabs.forEach(t => t.classList.remove('primary'));
                const target = e.currentTarget; // Usar currentTarget para asegurar que tomamos el botón
                target.classList.add('primary');
                
                // Logic
                this.currentType = target.dataset.tab === 'actors' ? 'actor' : 'category';
                this.updateUI();
                this.loadData();
            });
        });

        // Submit del Formulario
        this.dom.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleCreate();
        });
    }

    static updateUI() {
        this.dom.typeInput.value = this.currentType;
        
        // Reset inputs visuales
        this.dom.nameInput.value = '';
        this.dom.nameInput.focus();

        if (this.currentType === 'actor') {
            this.dom.actorGroup.style.display = 'block';
            this.dom.formTitle.textContent = 'Nuevo Actor/Actriz';
            this.dom.nameInput.placeholder = 'Ej. Scarlett Johansson';
        } else {
            this.dom.actorGroup.style.display = 'none';
            this.dom.formTitle.textContent = 'Nueva Categoría';
            this.dom.nameInput.placeholder = 'Ej. Ciencia Ficción';
        }
    }

    static async loadData() {
        this.dom.listContainer.innerHTML = '';
        this.dom.emptyState.style.display = 'none';
        this.dom.loadingState.style.display = 'block';

        try {
            // Llamada segura a la API
            const response = await ApiService.post(ApiRoutes.Admin.GetMetadata, { type: this.currentType });
            
            this.dom.loadingState.style.display = 'none';

            if (response.success && response.data && response.data.length > 0) {
                this.renderList(response.data);
            } else {
                this.dom.emptyState.style.display = 'block';
            }
        } catch (error) {
            console.error(error);
            this.dom.loadingState.style.display = 'none';
            ToastManager.show('error', 'Error al cargar los datos.');
        }
    }

    static renderList(data) {
        data.forEach(item => {
            const tr = document.createElement('tr');
            
            // Lógica de badges y visuales
            let extraInfo = `<span class="component-badge component-badge--sm">0 videos</span>`;
            let icon = 'category';

            if (this.currentType === 'actor') {
                const isActress = item.type === 'actress';
                const label = isActress ? 'Actriz' : 'Actor';
                const badgeClass = isActress ? 'color: #e91e63; border-color: rgba(233, 30, 99, 0.3);' : 'color: #2196f3; border-color: rgba(33, 150, 243, 0.3);';
                
                extraInfo = `
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="component-badge" style="${badgeClass} background: transparent;">${label}</span>
                        <span class="text-secondary" style="font-size: 12px;">${item.usage_count || 0} videos</span>
                    </div>
                `;
                icon = 'person';
            }

            tr.innerHTML = `
                <td style="padding-left: 24px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="component-card__icon-container component-card__icon-container--small component-card__icon-container--bordered">
                            <span class="material-symbols-rounded" style="font-size: 18px;">${icon}</span>
                        </div>
                        <span style="font-weight: 600; color: var(--text-primary);">${item.name}</span>
                    </div>
                </td>
                <td class="text-secondary" style="font-family: var(--sl-font-mono); font-size: 13px;">${item.slug}</td>
                <td>${extraInfo}</td>
                <td class="text-right" style="padding-right: 24px;">
                    <button class="component-button square component-button--danger-ghost delete-btn" data-id="${item.id}" title="Eliminar">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                </td>
            `;

            const delBtn = tr.querySelector('.delete-btn');
            delBtn.addEventListener('click', () => this.handleDelete(item.id));

            this.dom.listContainer.appendChild(tr);
        });
    }

    static async handleCreate() {
        const name = this.dom.nameInput.value.trim();
        const extra = this.dom.extraInput.value;

        if (!name) return;

        // Feedback visual inmediato (loading en botón)
        const submitBtn = this.dom.form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-sm"></span>';

        try {
            const response = await ApiService.post(ApiRoutes.Admin.CreateMetadata, {
                type: this.currentType,
                name: name,
                extra: extra
            });

            if (response.success) {
                ToastManager.show('success', 'Registro creado correctamente');
                this.loadData(); // Recargar tabla
                this.dom.nameInput.value = ''; // Limpiar input
                this.dom.nameInput.focus();
            } else {
                ToastManager.show('error', response.message || 'Error al crear');
            }
        } catch (error) {
            ToastManager.show('error', 'Error de conexión');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    static async handleDelete(id) {
        if (!confirm('¿Estás seguro de eliminar este registro? Esta acción no se puede deshacer.')) return;

        try {
            const response = await ApiService.post(ApiRoutes.Admin.DeleteMetadata, {
                type: this.currentType,
                id: id
            });

            if (response.success) {
                ToastManager.show('success', 'Registro eliminado');
                this.loadData();
            } else {
                ToastManager.show('error', response.message);
            }
        } catch (error) {
            ToastManager.show('error', 'Error al procesar la solicitud');
        }
    }
}