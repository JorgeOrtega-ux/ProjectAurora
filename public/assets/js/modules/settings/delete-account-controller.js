/**
 * public/assets/js/modules/settings/delete-account-controller.js
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';
import { DialogDefinitions } from '../../core/components/dialog-definitions.js';

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
                ToastManager.show(I18nManager.t('js.delete.enter_pass'), 'warning');
                inputPass.focus();
                return;
            }

            const confirmed = await DialogManager.confirm(DialogDefinitions.Account.DELETE);

            if (!confirmed) return;

            const originalText = btnDelete.innerText;
            btnDelete.innerText = I18nManager.t('js.delete.processing');
            btnDelete.disabled = true;
            toggleConfirm.disabled = true;
            inputPass.disabled = true;

            const formData = new FormData();
            formData.append('password', password);

            try {
                const res = await ApiService.post(ApiService.Routes.Settings.DeleteAccount, formData);

                if (res.success) {
                    ToastManager.show(I18nManager.t('js.delete.goodbye'), 'success');
                    setTimeout(() => {
                        window.location.href = window.BASE_PATH + 'login';
                    }, 1500);
                } else {
                    ToastManager.show(res.message, 'error');
                    btnDelete.innerText = originalText;
                    btnDelete.disabled = false;
                    toggleConfirm.disabled = false;
                    inputPass.disabled = false;
                    inputPass.value = '';
                }
            } catch (error) {
                console.error(error);
                ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
                btnDelete.innerText = originalText;
                btnDelete.disabled = false;
                toggleConfirm.disabled = false;
                inputPass.disabled = false;
            }
        });
    }
};