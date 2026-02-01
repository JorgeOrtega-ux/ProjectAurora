/**
 * public/assets/js/modules/app/create-canvas-controller.js
 */
import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { ApiRoutes } from '../../core/api-routes.js';

export const CreateCanvasController = {
    
    state: {
        selectedSize: 64,
        selectedPrivacy: 'public', // Valor por defecto
        accessCode: ''
    },

    init: () => {
        // 1. Buscamos el contenedor principal
        const container = document.querySelector('[data-section="app/create-canvas"]');
        
        if (!container) {
            console.warn('⚠️ CreateCanvasController: No se encontró [data-section="app/create-canvas"]');
            return;
        }

        console.log("✅ CreateCanvasController: Iniciado.");

        // 2. Cacheamos referencias (panelPublic puede ser null si lo borraste del HTML)
        const dom = {
            container: container,
            btnCreate: document.getElementById('btn-create-canvas'),
            displaySize: document.getElementById('display-size'),
            displayPrivacy: document.getElementById('display-privacy'),
            triggerPrivacy: document.getElementById('trigger-privacy'),
            
            // Estos pueden o no existir dependiendo de tu HTML
            panelPublic: document.getElementById('privacy-info-public'), 
            panelPrivate: document.getElementById('privacy-info-private'),
            
            inputCode: document.getElementById('access-code-display'),
            btnRefreshCode: document.getElementById('btn-refresh-code')
        };

        // --- Funciones Internas ---

        const generateCode = () => {
            let result = '';
            const characters = '0123456789';
            for (let i = 0; i < 12; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            CreateCanvasController.state.accessCode = result;
            if (dom.inputCode) {
                dom.inputCode.value = result.match(/.{1,4}/g).join('-'); 
            }
        };

        const closeDropdowns = (element) => {
            const wrapper = element.closest('.trigger-select-wrapper');
            if(wrapper) {
                const popover = wrapper.querySelector('.popover-module');
                if(popover) popover.classList.remove('active');
                const trigger = wrapper.querySelector('.trigger-selector');
                if(trigger) trigger.classList.remove('active');
            }
        };

        const togglePrivacyPanels = () => {
            // [CORRECCIÓN] Ya no obligamos a que existan ambos. Verificamos uno por uno.

            // 1. Panel Público (Si existe en el HTML, lo ocultamos/mostramos)
            if (dom.panelPublic) {
                if (CreateCanvasController.state.selectedPrivacy === 'private') {
                    dom.panelPublic.classList.add('d-none');
                } else {
                    dom.panelPublic.classList.remove('d-none');
                }
            }

            // 2. Panel Privado (El input del código)
            if (dom.panelPrivate) {
                if (CreateCanvasController.state.selectedPrivacy === 'private') {
                    // Mostrar privado
                    dom.panelPrivate.classList.remove('d-none');
                    // Asegurar que hay código
                    if (!CreateCanvasController.state.accessCode) generateCode();
                } else {
                    // Ocultar privado
                    dom.panelPrivate.classList.add('d-none');
                }
            }
        };

        // --- Event Listeners ---

        // Delegación de eventos en el container (más robusto que onclick)
        container.addEventListener('click', (e) => {
            
            // A. Selección de Tamaño
            const sizeOption = e.target.closest('[data-action="select-size"]');
            if (sizeOption) {
                container.querySelectorAll('[data-action="select-size"]').forEach(el => el.classList.remove('active'));
                sizeOption.classList.add('active');

                const val = parseInt(sizeOption.dataset.value);
                CreateCanvasController.state.selectedSize = val;
                if (dom.displaySize) dom.displaySize.textContent = `${val} x ${val} Píxeles`;
                
                closeDropdowns(sizeOption);
            }

            // B. Selección de Privacidad
            const privacyOption = e.target.closest('[data-action="select-privacy"]');
            if (privacyOption) {
                container.querySelectorAll('[data-action="select-privacy"]').forEach(el => el.classList.remove('active'));
                privacyOption.classList.add('active');

                const val = privacyOption.dataset.value;
                const label = privacyOption.dataset.label;
                const iconName = privacyOption.dataset.icon;

                // Actualizar el trigger visualmente
                if (dom.triggerPrivacy) {
                    const iconSpan = dom.triggerPrivacy.querySelector('.trigger-select-icon');
                    const textSpan = dom.triggerPrivacy.querySelector('.trigger-select-text');
                    if (iconSpan) iconSpan.textContent = iconName;
                    if (textSpan) textSpan.textContent = label;
                }

                // Actualizar estado y UI
                CreateCanvasController.state.selectedPrivacy = val;
                togglePrivacyPanels(); // <--- Ahora esto funcionará aunque falte el div público
                
                closeDropdowns(privacyOption);
            }
        });

        // C. Botón Crear
        if (dom.btnCreate) {
            dom.btnCreate.addEventListener('click', async () => {
                const btnContent = dom.btnCreate.innerHTML;
                dom.btnCreate.innerHTML = '<span class="spinner-sm"></span>';
                dom.btnCreate.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('size', CreateCanvasController.state.selectedSize);
                    formData.append('privacy', CreateCanvasController.state.selectedPrivacy);
                    
                    if (CreateCanvasController.state.selectedPrivacy === 'private') {
                        formData.append('access_code', CreateCanvasController.state.accessCode);
                    }

                    const response = await ApiService.post(ApiRoutes.Canvas.Create, formData);

                    if (response.success) {
                        Toast.show('Lienzo creado correctamente', 'success');
                        setTimeout(() => window.location.href = response.data.redirect_url || '/app', 1000);
                    } else {
                        Toast.show(response.message || 'Error', 'error');
                        dom.btnCreate.innerHTML = btnContent;
                        dom.btnCreate.disabled = false;
                    }
                } catch (error) {
                    console.error(error);
                    Toast.show('Error de conexión', 'error');
                    dom.btnCreate.innerHTML = btnContent;
                    dom.btnCreate.disabled = false;
                }
            });
        }

        // D. Botón Refrescar Código
        if (dom.btnRefreshCode) {
            dom.btnRefreshCode.addEventListener('click', (e) => {
                e.stopPropagation();
                generateCode();
                Toast.show('Nuevo código generado', 'info');
            });
        }

        // Ejecución inicial: Generar un código por si acaso
        generateCode();
    }
};