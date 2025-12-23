/**
 * public/assets/js/modules/settings/delete-account-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

export const DeleteAccountController = {
    init: () => {
        const toggleConfirm = document.getElementById('check-confirm-delete');
        const areaConfirmation = document.getElementById('delete-confirmation-area');
        const btnDelete = document.getElementById('btn-delete-final');
        const inputPass = document.getElementById('delete-password-input');

        if (!toggleConfirm || !areaConfirmation || !btnDelete) return;

        console.log("DeleteAccountController: Inicializado");

        // 1. Manejar el Toggle de confirmación
        toggleConfirm.checked = false; // Reset inicial
        toggleConfirm.addEventListener('change', (e) => {
            if (e.target.checked) {
                areaConfirmation.classList.remove('disabled');
                areaConfirmation.classList.add('active');
                // Auto-focus al input
                if(inputPass) setTimeout(() => inputPass.focus(), 100);
            } else {
                areaConfirmation.classList.add('disabled');
                areaConfirmation.classList.remove('active');
                if(inputPass) inputPass.value = '';
            }
        });

        // 2. Manejar el click de eliminar
        btnDelete.addEventListener('click', async () => {
            const password = inputPass.value;

            if (!password) {
                Toast.show('Debes ingresar tu contraseña.', 'warning');
                inputPass.focus();
                return;
            }

            if (!confirm('ÚLTIMA ADVERTENCIA: ¿Estás realmente seguro?')) return;

            // UI Loading
            const originalText = btnDelete.innerText;
            btnDelete.innerText = 'Procesando...';
            btnDelete.disabled = true;
            toggleConfirm.disabled = true;
            inputPass.disabled = true;

            const formData = new FormData();
            formData.append('action', 'delete_account');
            formData.append('password', password);

            try {
                const res = await ApiService.post('settings-handler.php', formData);

                if (res.success) {
                    Toast.show('Cuenta eliminada. Adiós.', 'success');
                    setTimeout(() => {
                        window.location.href = window.BASE_PATH + 'login';
                    }, 1500);
                } else {
                    Toast.show(res.message, 'error');
                    // Restaurar UI
                    btnDelete.innerText = originalText;
                    btnDelete.disabled = false;
                    toggleConfirm.disabled = false;
                    inputPass.disabled = false;
                    inputPass.value = '';
                }
            } catch (error) {
                console.error(error);
                Toast.show('Error de conexión.', 'error');
                btnDelete.innerText = originalText;
                btnDelete.disabled = false;
                toggleConfirm.disabled = false;
                inputPass.disabled = false;
            }
        });
    }
};