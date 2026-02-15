/* public/assets/js/modules/admin/metadata-controller.js */

import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js';
import { ToastManager } from '../../core/components/toast-manager.js';

export class MetadataController {
    
    static init() {
        const section = document.querySelector('[data-section="admin-metadata"]');
        if (!section) return;

        this.section = section;
        this.currentType = 'category';
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
        // Tabs
        this.dom.tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.dom.tabs.forEach(t => t.classList.remove('active'));
                e.target.classList.add('active');
                
                this.currentType = e.target.dataset.tab === 'actors' ? 'actor' : 'category';
                this.updateUI();
                this.loadData();
            });
        });

        // Form Submit
        this.dom.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleCreate();
        });
    }

    static updateUI() {
        this.dom.typeInput.value = this.currentType;
        if (this.currentType === 'actor') {
            this.dom.actorGroup.style.display = 'block';
            this.dom.formTitle.textContent = 'Nuevo Actor/Actriz';
        } else {
            this.dom.actorGroup.style.display = 'none';
            this.dom.formTitle.textContent = 'Nueva Categoría';
        }
    }

    static async loadData() {
        this.dom.listContainer.innerHTML = '';
        this.dom.emptyState.style.display = 'none';
        this.dom.loadingState.style.display = 'block';

        try {
            // Se utiliza ApiRoutes para evitar errores de arquitectura en ApiService
            const response = await ApiService.post(ApiRoutes.Admin.GetMetadata, { type: this.currentType });
            this.dom.loadingState.style.display = 'none';

            if (response.success && response.data.length > 0) {
                this.renderList(response.data);
            } else {
                this.dom.emptyState.style.display = 'block';
            }
        } catch (error) {
            console.error(error);
            this.dom.loadingState.style.display = 'none';
            ToastManager.show('error', 'Error al cargar datos');
        }
    }

    static renderList(data) {
        data.forEach(item => {
            const tr = document.createElement('tr');
            
            let extraInfo = `<span class="badge neutral">${item.usage_count} videos</span>`;
            if (this.currentType === 'actor') {
                const typeLabel = item.type === 'actress' ? 'Actriz' : 'Actor';
                const colorClass = item.type === 'actress' ? 'color: #e91e63;' : 'color: #2196f3;';
                extraInfo = `<span style="font-size: 0.85rem; font-weight:600; margin-right: 8px; ${colorClass}">${typeLabel}</span> ` + extraInfo;
            }

            tr.innerHTML = `
                <td style="font-weight: 500;">${item.name}</td>
                <td class="text-secondary" style="font-size: 0.9em;">${item.slug}</td>
                <td>${extraInfo}</td>
                <td>
                    <button class="icon-btn danger delete-btn" data-id="${item.id}">
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
        const name = this.dom.nameInput.value;
        const extra = this.dom.extraInput.value;

        try {
            // Uso de ApiRoutes.Admin.CreateMetadata definido en api-routes.js
            const response = await ApiService.post(ApiRoutes.Admin.CreateMetadata, {
                type: this.currentType,
                name: name,
                extra: extra
            });

            if (response.success) {
                ToastManager.show('success', response.message);
                this.dom.nameInput.value = ''; // Limpiar
                this.loadData();
            } else {
                ToastManager.show('error', response.message);
            }
        } catch (error) {
            ToastManager.show('error', 'Error de conexión');
        }
    }

    static async handleDelete(id) {
        if (!confirm('¿Seguro que quieres eliminar este registro?')) return;

        try {
            // Uso de ApiRoutes.Admin.DeleteMetadata definido en api-routes.js
            const response = await ApiService.post(ApiRoutes.Admin.DeleteMetadata, {
                type: this.currentType,
                id: id
            });

            if (response.success) {
                ToastManager.show('success', response.message);
                this.loadData();
            } else {
                ToastManager.show('error', response.message);
            }
        } catch (error) {
            ToastManager.show('error', 'Error al eliminar');
        }
    }
}