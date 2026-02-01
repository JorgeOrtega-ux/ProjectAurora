/**
 * public/assets/js/modules/app/create-canvas-controller.js
 * Controlador para la creación de nuevos lienzos.
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { ApiRoutes } from '../../core/api-routes.js';

export class CreateCanvasController {
    constructor() {
        this.selectedSize = 64;
        this.selectedPrivacy = 'public';
        this.accessCode = '';
        this.init();
    }

    init() {
        this.cacheDOM();
        this.bindEvents();
        // Generar un código inicial por si acaso
        this.generateCode();
    }

    cacheDOM() {
        this.container = document.querySelector('[data-section="app/create-canvas"]');
        this.btnCreate = document.getElementById('btn-create-canvas');
        
        // Elementos de UI
        this.displaySize = document.getElementById('display-size');
        this.displayPrivacy = document.getElementById('display-privacy');
        this.triggerPrivacy = document.getElementById('trigger-privacy');
        
        this.panelPublic = document.getElementById('privacy-info-public');
        this.panelPrivate = document.getElementById('privacy-info-private');
        this.inputCode = document.getElementById('access-code-display');
        this.btnRefreshCode = document.getElementById('btn-refresh-code');
    }

    bindEvents() {
        // Event Delegation para los menús desplegables (Trigger Selects)
        this.container.addEventListener('click', (e) => {
            // Selector de Tamaño
            const sizeOption = e.target.closest('[data-action="select-size"]');
            if (sizeOption) {
                this.handleSizeSelection(sizeOption);
            }

            // Selector de Privacidad
            const privacyOption = e.target.closest('[data-action="select-privacy"]');
            if (privacyOption) {
                this.handlePrivacySelection(privacyOption);
            }
        });

        // Botón Crear
        if (this.btnCreate) {
            this.btnCreate.addEventListener('click', () => this.createCanvas());
        }

        // Regenerar código
        if (this.btnRefreshCode) {
            this.btnRefreshCode.addEventListener('click', (e) => {
                e.stopPropagation(); // Evitar cerrar dropdowns si estuvieran abiertos
                this.generateCode();
                Toast.show('Nuevo código generado', 'info');
            });
        }
    }

    handleSizeSelection(element) {
        // UI Update
        document.querySelectorAll('[data-action="select-size"]').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        // Logic Update
        this.selectedSize = parseInt(element.dataset.value);
        this.displaySize.textContent = `${this.selectedSize} x ${this.selectedSize} Píxeles`;
        
        // Cerrar popover
        this.closeDropdowns(element);
    }

    handlePrivacySelection(element) {
        // UI Update
        document.querySelectorAll('[data-action="select-privacy"]').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        const value = element.dataset.value;
        const label = element.dataset.label;
        const iconName = element.dataset.icon;

        // Actualizar el trigger principal
        const iconSpan = this.triggerPrivacy.querySelector('.trigger-select-icon');
        const textSpan = this.triggerPrivacy.querySelector('.trigger-select-text');
        
        iconSpan.textContent = iconName;
        textSpan.textContent = label;

        // Logic Update
        this.selectedPrivacy = value;
        this.togglePrivacyPanels();

        // Cerrar popover
        this.closeDropdowns(element);
    }

    closeDropdowns(element) {
        const wrapper = element.closest('.trigger-select-wrapper');
        if(wrapper) {
            const popover = wrapper.querySelector('.popover-module');
            if(popover) popover.classList.remove('active');
            const trigger = wrapper.querySelector('.trigger-selector');
            if(trigger) trigger.classList.remove('active');
        }
    }

    togglePrivacyPanels() {
        if (this.selectedPrivacy === 'private') {
            this.panelPublic.classList.add('d-none');
            this.panelPrivate.classList.remove('d-none');
            // Asegurar que haya código
            if (!this.accessCode) this.generateCode();
        } else {
            this.panelPublic.classList.remove('d-none');
            this.panelPrivate.classList.add('d-none');
        }
    }

    generateCode() {
        // Generar 12 dígitos aleatorios
        let result = '';
        const characters = '0123456789';
        for (let i = 0; i < 12; i++) {
            result += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        this.accessCode = result;
        if (this.inputCode) {
            this.inputCode.value = result.match(/.{1,4}/g).join('-'); // Formato XXXX-XXXX-XXXX para legibilidad
        }
    }

    async createCanvas() {
        const btnContent = this.btnCreate.innerHTML;
        this.btnCreate.innerHTML = '<span class="spinner-sm"></span>';
        this.btnCreate.disabled = true;

        try {
            // [CORRECCIÓN CRÍTICA] Usar FormData en lugar de objeto simple
            const formData = new FormData();
            formData.append('size', this.selectedSize);
            formData.append('privacy', this.selectedPrivacy);
            
            if (this.selectedPrivacy === 'private') {
                formData.append('access_code', this.accessCode);
            }

            // ApiService inyectará 'route' ('canvas.create') automáticamente al detectar FormData
            const response = await ApiService.post(ApiRoutes.Canvas.Create, formData);

            if (response.success) {
                Toast.show('Lienzo creado correctamente', 'success');
                // Redirigir al lienzo
                setTimeout(() => {
                    window.location.href = response.data.redirect_url || '/app';
                }, 1000);
            } else {
                Toast.show(response.message || 'Error al crear el lienzo', 'error');
                this.resetButton(btnContent);
            }
        } catch (error) {
            console.error(error);
            Toast.show('Error de conexión', 'error');
            this.resetButton(btnContent);
        }
    }

    resetButton(originalContent) {
        this.btnCreate.innerHTML = originalContent;
        this.btnCreate.disabled = false;
    }
}
// Inicialización automática si se carga dinámicamente
new CreateCanvasController();