/**
 * public/assets/js/modules/settings/security-controller.js
 * Maneja el flujo de cambio de contraseña
 */

export const SecurityController = {
    init: () => {
        console.log("SecurityController: Inicializado");
        initPasswordFlow();
    }
};

function getCsrfToken() {
    const input = document.getElementById('security-csrf');
    return input ? input.value : '';
}

function initPasswordFlow() {
    const container = document.querySelector('[data-component="password-update-section"]');
    if (!container) return;

    // Elementos de UI (Etapas)
    const stage0 = container.querySelector('[data-state="password-stage-0"]');
    const stage1 = container.querySelector('[data-state="password-stage-1"]');
    const stage2 = container.querySelector('[data-state="password-stage-2"]');
    
    // Inputs
    const currentInput = document.getElementById('current-password-input');
    const newInput = document.getElementById('new-password-input');
    const repeatInput = document.getElementById('repeat-password-input');

    container.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn || !btn.dataset.action) return;

        const action = btn.dataset.action;

        // --- FLUJO DE UI ---
        
        // 1. Iniciar flujo: Mostrar campo "Contraseña actual"
        if (action === 'pass-start-flow') {
            toggleStage(stage0, false);
            toggleStage(stage1, true);
            if(currentInput) {
                currentInput.value = '';
                currentInput.focus();
            }
        }

        // 2. Cancelar flujo: Limpiar y volver al inicio
        if (action === 'pass-cancel-flow') {
            resetInputs([currentInput, newInput, repeatInput]);
            toggleStage(stage1, false);
            toggleStage(stage2, false);
            toggleStage(stage0, true);
        }

        // 3. Ir al paso 2: Validar contraseña actual (básico) y pedir nueva
        if (action === 'pass-go-step-2') {
            if (!currentInput.value.trim()) {
                alert('Ingresa tu contraseña actual.');
                return;
            }
            toggleStage(stage1, false);
            toggleStage(stage2, true);
            if(newInput) {
                newInput.value = '';
                repeatInput.value = '';
                newInput.focus();
            }
        }

        // 4. Guardar cambios (AJAX)
        if (action === 'pass-submit-final') {
            const currentVal = currentInput.value;
            const newVal = newInput.value;
            const repeatVal = repeatInput.value;

            if (!newVal || !repeatVal) {
                alert('Completa todos los campos.');
                return;
            }

            if (newVal !== repeatVal) {
                alert('Las contraseñas nuevas no coinciden.');
                return;
            }

            if (newVal.length < 12) {
                alert('La contraseña debe tener al menos 12 caracteres.');
                return;
            }

            startLoading(btn, 'Guardando...');

            const formData = new FormData();
            formData.append('action', 'settings_change_password');
            formData.append('current_password', currentVal);
            formData.append('new_password', newVal);
            formData.append('repeat_password', repeatVal);
            formData.append('csrf_token', getCsrfToken());

            try {
                const response = await fetch(window.BASE_PATH, { method: 'POST', body: formData });
                const data = await response.json();

                if (data.status) {
                    alert('Contraseña actualizada correctamente.');
                    // Resetear todo al éxito
                    resetInputs([currentInput, newInput, repeatInput]);
                    toggleStage(stage2, false);
                    toggleStage(stage0, true);
                } else {
                    alert(data.message);
                    // Si el error es contraseña actual incorrecta, podríamos devolver al paso 1,
                    // pero por seguridad a veces es mejor resetear o dejar que el usuario cancele.
                }

            } catch (err) {
                console.error(err);
                alert('Error de conexión.');
            } finally {
                stopLoading(btn, 'Guardar');
            }
        }
    });
}

// --- UTILIDADES DE UI ---
function toggleStage(element, isActive) {
    if (!element) return;
    if (isActive) {
        element.classList.remove('disabled');
        element.classList.add('active');
    } else {
        element.classList.remove('active');
        element.classList.add('disabled');
    }
}

function resetInputs(inputs) {
    inputs.forEach(input => {
        if(input) input.value = '';
    });
}

function startLoading(btn, loadingText) {
    btn.dataset.originalText = btn.innerText;
    btn.innerText = loadingText;
    btn.disabled = true;
    btn.style.opacity = '0.7';
}
function stopLoading(btn, originalText) {
    btn.innerText = originalText || btn.dataset.originalText;
    btn.disabled = false;
    btn.style.opacity = '1';
}