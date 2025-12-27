/**
 * public/assets/js/modules/settings/profile-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { DialogDefinitions } from '../../core/dialog-definitions.js';

export const ProfileController = {
    
    init: () => {
        console.log("ProfileController: Inicializado (Full)");
        initAvatarLogic();
        initIdentityLogic();
    }
};

/**
 * Lógica para la foto de perfil (Avatar)
 */
function initAvatarLogic() {
    const fileInput = document.getElementById('upload-avatar');
    const previewImg = document.getElementById('preview-avatar');
    const triggerBtn = document.getElementById('btn-trigger-upload');
    const initBtn = document.getElementById('btn-upload-init');
    const container = document.querySelector('[data-component="profile-picture-section"]');
    
    if (!fileInput || !previewImg || !container) return;

    let originalSrc = previewImg.src;

    const openFileSelector = () => fileInput.click();
    if(triggerBtn) triggerBtn.addEventListener('click', openFileSelector);
    if(initBtn) initBtn.addEventListener('click', openFileSelector);

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                Toast.show(I18n.t('js.profile.invalid_image'), 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = (evt) => {
                previewImg.src = evt.target.result;
                toggleProfileActions('preview');
            };
            reader.readAsDataURL(file);
        }
    });

    container.addEventListener('click', async (e) => {
        const action = e.target.dataset.action;
        if (!action) return;

        if (action === 'profile-picture-cancel') {
            previewImg.src = originalSrc;
            fileInput.value = ''; 
            const isCustom = originalSrc.includes('/custom/');
            toggleProfileActions(isCustom ? 'custom' : 'default');
        }

        if (action === 'profile-picture-save') {
            const file = fileInput.files[0];
            if (!file) return;

            const btn = e.target;
            const originalText = btn.innerText;
            btn.innerText = I18n.t('js.profile.saving');
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'upload_avatar');
            formData.append('avatar', file);

            try {
                const res = await ApiService.post('settings-handler.php', formData);
                if (res.success) {
                    Toast.show(I18n.t('js.profile.pic_updated'), 'success');
                    
                    const newAvatarSrc = previewImg.src; 
                    originalSrc = res.new_src || newAvatarSrc; 
                    toggleProfileActions('custom');
                    fileInput.value = ''; 

                    const event = new CustomEvent('user:avatar_update', { 
                        detail: { src: newAvatarSrc } 
                    });
                    document.dispatchEvent(event);

                } else {
                    Toast.show(res.message, 'error');
                }
            } catch(err) {
                console.error(err);
                Toast.show(I18n.t('js.core.connection_error'), 'error');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        if (action === 'profile-picture-delete') {
            const confirmed = await Dialog.confirm(DialogDefinitions.Profile.DELETE_AVATAR);

            if (!confirmed) return;

            const btn = e.target;
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'delete_avatar');
            
            try {
                const res = await ApiService.post('settings-handler.php', formData);
                if (res.success) {
                    Toast.show(I18n.t('js.profile.pic_deleted'), 'info');
                    
                    if (res.new_src) {
                        const newUrl = res.new_src;
                        
                        previewImg.src = newUrl;
                        originalSrc = newUrl;
                        
                        toggleProfileActions('default');

                        const event = new CustomEvent('user:avatar_update', { 
                            detail: { src: newUrl } 
                        });
                        document.dispatchEvent(event);
                    } else {
                         window.location.reload();
                    }

                } else {
                    Toast.show(res.message, 'error');
                }
            } catch(err) { Toast.show(I18n.t('js.core.connection_error'), 'error'); }
            finally { btn.disabled = false; }
        }

        if (action === 'profile-picture-change') {
            fileInput.click();
        }
    });
}

function toggleProfileActions(state) {
    const actionsDefault = document.querySelector('[data-state="profile-picture-actions-default"]'); 
    const actionsPreview = document.querySelector('[data-state="profile-picture-actions-preview"]'); 
    const actionsCustom  = document.querySelector('[data-state="profile-picture-actions-custom"]'); 

    [actionsDefault, actionsPreview, actionsCustom].forEach(el => {
        if(el) { el.classList.add('disabled'); el.classList.remove('active'); }
    });

    if (state === 'default' && actionsDefault) {
        actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
    } else if (state === 'preview' && actionsPreview) {
        actionsPreview.classList.remove('disabled'); actionsPreview.classList.add('active');
    } else if (state === 'custom' && actionsCustom) {
        actionsCustom.classList.remove('disabled'); actionsCustom.classList.add('active');
    }
}

/**
 * Lógica para editar Username y Email
 */
function initIdentityLogic() {
    const container = document.querySelector('[data-section="settings/your-profile"]');
    if(!container) return;

    container.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if(!btn || !btn.dataset.action) return;

        const action = btn.dataset.action;
        const targetField = btn.dataset.target; // 'username' o 'email'

        if(action === 'start-edit') {
            // === INTERCEPCIÓN PARA VERIFICACIÓN DE EMAIL ===
            if (targetField === 'email') {
                await handleEmailVerification(targetField);
            } else {
                toggleEditState(targetField, true);
            }
        } else if (action === 'cancel-edit') {
            toggleEditState(targetField, false);
        } else if (action === 'save-field') {
            await saveFieldData(targetField, btn);
        }
    });
}

async function handleEmailVerification(targetField) {
    Dialog.showLoading('Enviando código...');
    
    // 1. Solicitar envío de código
    const formData = new FormData();
    formData.append('action', 'request_email_change_verification');

    try {
        const res = await ApiService.post('settings-handler.php', formData);
        Dialog.close();

        if (res.success) {
            // 2. Mostrar diálogo de input
            const confirmed = await Dialog.confirm(DialogDefinitions.Profile.VERIFY_EMAIL);
            
            if (confirmed) {
                // Obtener el valor del input del diálogo
                // (El input sigue en el DOM momentáneamente o necesitamos leerlo antes de cerrar el dialog)
                // Dado que Dialog.confirm cierra al clickar, debemos asegurarnos de leer el valor.
                // En nuestra implementación de Dialog.confirm, el resolve ocurre DESPUÉS del click.
                // El elemento sigue accesible si la transición de cierre no ha terminado de eliminar el nodo.
                // PERO para mayor seguridad, obtenemos el valor directamente del DOM global ya que el ID es único.
                const inputCode = document.getElementById('verify-email-code');
                const code = inputCode ? inputCode.value.trim() : '';

                if (!code) {
                    Toast.show(I18n.t('js.profile.email_code_req'), 'warning');
                    return;
                }

                Dialog.showLoading('Verificando...');
                
                const verifyData = new FormData();
                verifyData.append('action', 'verify_email_change_code');
                verifyData.append('code', code);

                const verifyRes = await ApiService.post('settings-handler.php', verifyData);
                Dialog.close();

                if (verifyRes.success) {
                    Toast.show('Identidad verificada. Puedes cambiar tu correo.', 'success');
                    toggleEditState(targetField, true);
                } else {
                    Toast.show(verifyRes.message, 'error');
                }
            }
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        Dialog.close();
        Toast.show(I18n.t('js.core.connection_error'), 'error');
    }
}

function toggleEditState(fieldId, isEditing) {
    const parent = document.querySelector(`[data-component="${fieldId}-section"]`);
    if (!parent) return;

    const viewState = parent.querySelector(`[data-state="${fieldId}-view-state"]`);
    const editState = parent.querySelector(`[data-state="${fieldId}-edit-state"]`);
    const actionsView = parent.querySelector(`[data-state="${fieldId}-actions-view"]`);
    const actionsEdit = parent.querySelector(`[data-state="${fieldId}-actions-edit"]`);
    const input = parent.querySelector('input');

    if (isEditing) {
        viewState?.classList.replace('active', 'disabled');
        actionsView?.classList.replace('active', 'disabled');
        
        editState?.classList.replace('disabled', 'active');
        actionsEdit?.classList.replace('disabled', 'active');
        
        if (input) {
            input.dataset.originalValue = input.value;
            const val = input.value;
            input.focus();
            input.value = '';
            input.value = val;
        }
    } else {
        editState?.classList.replace('active', 'disabled');
        actionsEdit?.classList.replace('active', 'disabled');
        
        viewState?.classList.replace('disabled', 'active');
        actionsView?.classList.replace('disabled', 'active');
        
        if (input && input.dataset.originalValue) {
            input.value = input.dataset.originalValue;
        }
    }
}

async function saveFieldData(fieldId, btnSave) {
    const parent = document.querySelector(`[data-component="${fieldId}-section"]`);
    const input = parent.querySelector('input');
    const display = parent.querySelector('.text-display-value');

    if (!input || !display) return;

    const newValue = input.value.trim();
    const originalValue = input.dataset.originalValue;

    if (newValue === originalValue) {
        toggleEditState(fieldId, false);
        return;
    }

    const originalText = btnSave.innerText;
    btnSave.disabled = true;
    btnSave.innerText = I18n.t('js.settings.saving');

    const formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('field', fieldId); 
    formData.append('value', newValue);

    try {
        const res = await ApiService.post('settings-handler.php', formData);

        if (res.success) {
            display.innerText = newValue;
            input.dataset.originalValue = newValue;
            Toast.show(res.message, 'success');
            toggleEditState(fieldId, false);
        } else {
            Toast.show(res.message, 'error');
            input.focus(); 
        }
    } catch (error) {
        Toast.show(I18n.t('js.settings.processing_error'), 'error');
    } finally {
        btnSave.disabled = false;
        btnSave.innerText = originalText;
    }
}