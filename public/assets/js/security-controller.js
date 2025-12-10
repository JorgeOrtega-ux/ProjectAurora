/**
 * SecurityController.js
 * Maneja la lógica de la sección "Login & Security", específicamente
 * el componente de actualización de contraseña por etapas.
 */

import { SettingsService } from './api-services.js';
import { Toast } from './toast-service.js';

let currentPasswordBuffer = ''; // Variable temporal para guardar la contraseña del paso 1

const resetFlow = (container) => {
    currentPasswordBuffer = '';
    
    // Inputs
    const inputs = container.querySelectorAll('input');
    inputs.forEach(input => input.value = '');

    // UI States
    const stage0 = container.querySelector('[data-state="password-stage-0"]');
    const stage1 = container.querySelector('[data-state="password-stage-1"]');
    const stage2 = container.querySelector('[data-state="password-stage-2"]');
    
    // También el contenedor padre para ajustar el diseño si fuera necesario (stacked vs row)
    const itemContainer = container.closest('.component-group-item');
    if (itemContainer) {
        itemContainer.classList.remove('component-group-item--stacked');
    }

    if(stage0) { stage0.classList.remove('disabled'); stage0.classList.add('active'); }
    if(stage1) { stage1.classList.remove('active'); stage1.classList.add('disabled'); }
    if(stage2) { stage2.classList.remove('active'); stage2.classList.add('disabled'); }
};

const goToStage1 = (container) => {
    const stage0 = container.querySelector('[data-state="password-stage-0"]');
    const stage1 = container.querySelector('[data-state="password-stage-1"]');
    
    // Cambiar layout a stacked para que los inputs ocupen su propia línea abajo
    const itemContainer = container.closest('.component-group-item');
    if (itemContainer) {
        itemContainer.classList.add('component-group-item--stacked');
    }

    if(stage0) { stage0.classList.remove('active'); stage0.classList.add('disabled'); }
    if(stage1) { stage1.classList.remove('disabled'); stage1.classList.add('active'); }
    
    // Focus
    setTimeout(() => {
        const input = container.querySelector('#current-password-input');
        if(input) input.focus();
    }, 100);
};

const goToStage2 = (container) => {
    const inputCurrent = container.querySelector('#current-password-input');
    
    if (!inputCurrent || !inputCurrent.value) {
        Toast.error(window.t('js.error.complete_fields'));
        return;
    }

    currentPasswordBuffer = inputCurrent.value;

    const stage1 = container.querySelector('[data-state="password-stage-1"]');
    const stage2 = container.querySelector('[data-state="password-stage-2"]');

    if(stage1) { stage1.classList.remove('active'); stage1.classList.add('disabled'); }
    if(stage2) { stage2.classList.remove('disabled'); stage2.classList.add('active'); }

    // Focus
    setTimeout(() => {
        const inputNew = container.querySelector('#new-password-input');
        if(inputNew) inputNew.focus();
    }, 100);
};

const submitPasswordChange = async (container) => {
    const inputNew = container.querySelector('#new-password-input');
    const inputRepeat = container.querySelector('#repeat-password-input');
    
    if (!inputNew || !inputRepeat || !inputNew.value || !inputRepeat.value) {
        Toast.error(window.t('js.error.complete_fields'));
        return;
    }

    if (inputNew.value !== inputRepeat.value) {
        Toast.error(window.t('js.error.pass_mismatch'));
        return;
    }
    
    if (inputNew.value.length < 8) {
        Toast.error(window.t('api.error.password_short'));
        return;
    }

    const btn = container.querySelector('[data-action="pass-submit-final"]');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = window.t('global.processing');

    try {
        // Enviamos la contraseña antigua (del paso 1) y la nueva
        const result = await SettingsService.updatePassword(currentPasswordBuffer, inputNew.value);
        
        if (result.status === 'success') {
            Toast.success(result.message);
            resetFlow(container);
        } else {
            Toast.error(result.message);
            // Si el error es contraseña incorrecta, quizás deberíamos devolver al paso 1?
            // Por ahora, solo dejamos que reintente en el paso actual o cancele.
        }
    } catch (error) {
        console.error(error);
        Toast.error(window.t('js.error.connection'));
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
};

const setupSecurityListeners = () => {
    document.addEventListener('click', (e) => {
        // Delegación de eventos para el componente de contraseña
        const container = e.target.closest('[data-component="password-update-section"]');
        if (!container) return;

        if (e.target.closest('[data-action="pass-start-flow"]')) {
            goToStage1(container);
        }

        if (e.target.closest('[data-action="pass-cancel-flow"]')) {
            resetFlow(container);
        }

        if (e.target.closest('[data-action="pass-go-step-2"]')) {
            goToStage2(container);
        }

        if (e.target.closest('[data-action="pass-submit-final"]')) {
            submitPasswordChange(container);
        }
    });
};

export const initSecurityController = () => {
    console.log('SecurityController: Inicializado.');
    setupSecurityListeners();
};