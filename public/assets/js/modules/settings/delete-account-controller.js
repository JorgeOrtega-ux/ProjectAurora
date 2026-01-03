/**
 * public/assets/js/modules/settings/delete-account-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { DialogDefinitions } from '../../core/dialog-definitions.js'; // <--- NUEVO IMPORT

export const DeleteAccountController = {
    init: () => {
        const toggleConfirm = document.getElementById('check-confirm-delete');
        const areaConfirmation = document.getElementById('delete-confirmation-area');
        const btnDelete = document.getElementById('btn-delete-final');
        const inputPass = document.getElementById('delete-password-input');

        if (!toggleConfirm || !areaConfirmation || !btnDelete) return;

        console.log("DeleteAccountController: Inicializado");

        toggleConfirm.checked = false;
        toggleConfirm.addEventListener('change', (e) => {
            if (e.target.checked) {
                areaConfirmation.classList.remove('disabled');
                areaConfirmation.classList.add('active');
                if(inputPass) setTimeout(() => inputPass.focus(), 100);
            } else {
                areaConfirmation.classList.add('disabled');
                areaConfirmation.classList.remove('active');
                if(inputPass) inputPass.value = '';
            }
        });

        btnDelete.addEventListener('click', async () => {
            const password = inputPass.value;

            if (!password) {
                Toast.show(I18n.t('js.delete.enter_pass'), 'warning');
                inputPass.focus();
                return;
            }

            // --- USO DE DEFINICIÓN CENTRALIZADA ---
            const confirmed = await Dialog.confirm(DialogDefinitions.Account.DELETE);

            if (!confirmed) return;
            // -------------------------------------

            const originalText = btnDelete.innerText;
            btnDelete.innerText = I18n.t('js.delete.processing');
            btnDelete.disabled = true;
            toggleConfirm.disabled = true;
            inputPass.disabled = true;

            const formData = new FormData();
            formData.append('action', 'delete_account');
            formData.append('password', password);

            try {
                const res = await ApiService.post('settings-handler.php', formData);

                if (res.success) {
                    Toast.show(I18n.t('js.delete.goodbye'), 'success');
                    setTimeout(() => {
                        window.location.href = window.BASE_PATH + 'login';
                    }, 1500);
                } else {
                    Toast.show(res.message, 'error');
                    btnDelete.innerText = originalText;
                    btnDelete.disabled = false;
                    toggleConfirm.disabled = false;
                    inputPass.disabled = false;
                    inputPass.value = '';
                }
            } catch (error) {
                console.error(error);
                Toast.show(I18n.t('js.core.connection_error'), 'error');
                btnDelete.innerText = originalText;
                btnDelete.disabled = false;
                toggleConfirm.disabled = false;
                inputPass.disabled = false;
            }
        });
    }
};