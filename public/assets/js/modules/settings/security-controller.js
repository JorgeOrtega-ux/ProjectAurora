/**
 * public/assets/js/modules/settings/security-controller.js
 * Maneja el flujo de cambio de contraseña.
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

export const SecurityController = {
    init: () => {
        console.log("SecurityController: Inicializado");
        initPasswordFlow();
    }
};

function initPasswordFlow() {
    const container = document.querySelector('[data-component="password-update-section"]');
    if (!container) return; 

    // Configuración de clases
    const activeClass = 'active';
    const disabledClass = 'disabled';

    container.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if(!btn || !btn.dataset.action) return;
        const action = btn.dataset.action;

        const stage0 = container.querySelector('[data-state="password-stage-0"]'); 
        const stage1 = container.querySelector('[data-state="password-stage-1"]'); 
        const stage2 = container.querySelector('[data-state="password-stage-2"]'); 
        const parentGroup = container.closest('.component-group-item'); 

        // 1. Iniciar flujo (Mostrar input pass actual)
        if (action === 'pass-start-flow') {
            if(parentGroup) parentGroup.classList.add('component-group-item--stacked');
            switchState(stage0, stage1);
            const input = document.getElementById('current-password-input');
            if(input) { input.value = ''; input.focus(); }
        }

        // 2. Cancelar flujo
        if (action === 'pass-cancel-flow') {
            if(parentGroup) parentGroup.classList.remove('component-group-item--stacked');
            
            // Ocultar etapas activas
            [stage1, stage2].forEach(s => {
                if(s) { s.classList.remove(activeClass); s.classList.add(disabledClass); }
            });
            // Mostrar inicial
            if(stage0) { stage0.classList.remove(disabledClass); stage0.classList.add(activeClass); }
            
            container.querySelectorAll('input').forEach(i => i.value = '');
        }

        // 3. Verificar pass actual -> Ir a paso 2
        if (action === 'pass-go-step-2') {
            const currentPassInput = document.getElementById('current-password-input');
            const currentPass = currentPassInput.value;
            
            if (!currentPass) {
                Toast.show(I18n.t('js.settings.enter_current_pass'), 'warning');
                return;
            }

            const originalText = btn.innerText;
            btn.innerText = I18n.t('js.settings.verifying');
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'validate_current_password');
            formData.append('current_password', currentPass);

            try {
                const res = await ApiService.post('settings-handler.php', formData);
                if (res.success) {
                    switchState(stage1, stage2);
                    const inputNew = document.getElementById('new-password-input');
                    if(inputNew) { inputNew.value = ''; inputNew.focus(); }
                    document.getElementById('repeat-password-input').value = '';
                } else {
                    Toast.show(res.message, 'error');
                    currentPassInput.focus();
                }
            } catch (err) {
                Toast.show(I18n.t('js.settings.processing_error'), 'error');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        // 4. Guardar nueva contraseña
        if (action === 'pass-submit-final') {
            const currentPass = document.getElementById('current-password-input').value;
            const newPass = document.getElementById('new-password-input').value;
            const repeatPass = document.getElementById('repeat-password-input').value;

            if (!newPass || !repeatPass) { Toast.show(I18n.t('js.settings.fill_all'), 'warning'); return; }
            if (newPass !== repeatPass) { Toast.show(I18n.t('js.settings.pass_mismatch'), 'error'); return; }
            if (newPass.length < 6) { Toast.show(I18n.t('js.settings.pass_short'), 'warning'); return; }

            const originalText = btn.innerText;
            btn.innerText = I18n.t('js.settings.saving');
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPass);
            formData.append('new_password', newPass);

            try {
                const res = await ApiService.post('settings-handler.php', formData);
                if (res.success) {
                    Toast.show(I18n.t('js.settings.pass_updated'), 'success');
                    
                    // Resetear UI
                    if(parentGroup) parentGroup.classList.remove('component-group-item--stacked');
                    [stage1, stage2].forEach(s => {
                        if(s) { s.classList.remove(activeClass); s.classList.add(disabledClass); }
                    });
                    if(stage0) { stage0.classList.remove(disabledClass); stage0.classList.add(activeClass); }
                    container.querySelectorAll('input').forEach(i => i.value = '');
                    
                } else { Toast.show(res.message, 'error'); }
            } catch(err) { Toast.show(I18n.t('js.settings.processing_error'), 'error'); } 
            finally { btn.innerText = originalText; btn.disabled = false; }
        }
    });

    function switchState(hideElement, showElement) {
        if (hideElement) {
            hideElement.classList.remove(activeClass);
            hideElement.classList.add(disabledClass);
        }
        if (showElement) {
            showElement.classList.remove(disabledClass);
            showElement.classList.add(activeClass);
        }
    }
}