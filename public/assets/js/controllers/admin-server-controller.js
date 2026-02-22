// public/assets/js/controllers/admin-server-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class AdminServerController {
    constructor() {
        this.init();
    }

    init() {
        document.body.addEventListener('click', (e) => {
            const view = document.getElementById('admin-server-view');
            if (!view) return;

            // --- 1. Manejo de Acordeones ---
            const accordionHeader = e.target.closest('.component-accordion-header');
            if (accordionHeader && accordionHeader.closest('#server-config-accordions')) {
                const item = accordionHeader.closest('.component-accordion-item');
                if (item) item.classList.toggle('active');
                return;
            }

            // --- 2. Manejo de Botones del Stepper (Paginación numérica) ---
            const stepperBtn = e.target.closest('.stepper-btn');
            if (stepperBtn) {
                e.preventDefault();
                const wrapper = stepperBtn.closest('.config-stepper');
                const target = wrapper.dataset.target;
                const min = parseInt(wrapper.dataset.min);
                const max = parseInt(wrapper.dataset.max);
                
                const input = document.getElementById(`input-${target}`);
                const display = document.getElementById(`val-${target}`);
                
                let val = parseInt(input.value);
                if (isNaN(val)) val = min;

                if (stepperBtn.dataset.action === 'increase' && val < max) {
                    val++;
                } else if (stepperBtn.dataset.action === 'decrease' && val > min) {
                    val--;
                }

                input.value = val;
                display.textContent = val;
                return;
            }

            // --- 3. Botón de Guardar ---
            const saveBtn = e.target.closest('#btn-save-server-config');
            if (saveBtn) {
                e.preventDefault();
                this.saveConfig(saveBtn);
            }
        });
    }

    async saveConfig(btn) {
        const view = document.getElementById('admin-server-view');
        if (!view) return;

        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';
        
        const payload = {
            csrf_token: csrfToken,
            configs: {}
        };

        // Recolectar dinámicamente todos los inputs
        const inputs = view.querySelectorAll('input[id^="input-"]');
        inputs.forEach(input => {
            const key = input.id.replace('input-', '');
            payload.configs[key] = input.value.trim();
        });

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.ADMIN.UPDATE_SERVER_CONFIG, payload);
            
            if (res.success) {
                Toast.show(res.message, 'success');
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error interno de red al actualizar la configuración.', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }
}