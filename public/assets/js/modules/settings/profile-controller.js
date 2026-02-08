/**
 * public/assets/js/modules/settings/profile-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { ToastManager } from '../../core/toast-manager.js';
import { I18nManager } from '../../core/i18n-manager.js';
import { DialogManager } from '../../core/dialog-manager.js';
import { DialogDefinitions, DialogTemplates } from '../../core/dialog-definitions.js';

export const ProfileController = {
    init: () => {
        console.log("ProfileController: Inicializado (Full)");
        initAvatarLogic();
        initIdentityLogic();
    }
};

// ... (initAvatarLogic y funciones de avatar se mantienen igual) ...
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
                ToastManager.show(I18nManager.t('js.profile.invalid_image'), 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = (evt) => {
                previewImg.src = evt.target.result;
                showAvatarActions('preview');
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
            showAvatarActions(isCustom ? 'custom' : 'default');
        }

        if (action === 'profile-picture-save') {
            const file = fileInput.files[0];
            if (!file) return;

            const btn = e.target;
            const originalText = btn.innerText;
            btn.innerText = I18nManager.t('js.profile.saving');
            btn.disabled = true;

            const formData = new FormData();
            formData.append('avatar', file);

            try {
                const res = await ApiService.post(ApiService.Routes.Settings.UploadAvatar, formData);
                if (res.success) {
                    ToastManager.show(I18nManager.t('js.profile.pic_updated'), 'success');
                    
                    const newAvatarSrc = previewImg.src; 
                    originalSrc = res.new_src || newAvatarSrc; 
                    showAvatarActions('custom');
                    fileInput.value = ''; 

                    const event = new CustomEvent('user:avatar_update', { 
                        detail: { src: newAvatarSrc } 
                    });
                    document.dispatchEvent(event);

                } else {
                    ToastManager.show(res.message, 'error');
                }
            } catch(err) {
                console.error(err);
                ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        if (action === 'profile-picture-delete') {
            const confirmed = await DialogManager.confirm(DialogDefinitions.Profile.DELETE_AVATAR);

            if (!confirmed) return;

            const btn = e.target;
            btn.disabled = true;

            try {
                const res = await ApiService.post(ApiService.Routes.Settings.DeleteAvatar);
                if (res.success) {
                    ToastManager.show(I18nManager.t('js.profile.pic_deleted'), 'info');
                    
                    if (res.new_src) {
                        const newUrl = res.new_src;
                        previewImg.src = newUrl;
                        originalSrc = newUrl;
                        showAvatarActions('default');

                        const event = new CustomEvent('user:avatar_update', { 
                            detail: { src: newUrl } 
                        });
                        document.dispatchEvent(event);
                    } else {
                         window.location.reload();
                    }

                } else {
                    ToastManager.show(res.message, 'error');
                }
            } catch(err) { ToastManager.show(I18nManager.t('js.core.connection_error'), 'error'); }
            finally { btn.disabled = false; }
        }

        if (action === 'profile-picture-change') {
            fileInput.click();
        }
    });
}

function showAvatarActions(state) {
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

function initIdentityLogic() {
    const container = document.querySelector('[data-section="settings/your-profile"]');
    if(!container) return;

    container.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if(!btn || !btn.dataset.action) return;

        const action = btn.dataset.action;
        const targetField = btn.dataset.target; 

        if(action === 'start-edit') {
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
    // 1. Abre el primer diálogo (Spinner)
    DialogManager.showLoading('Comprobando estado...');
    
    try {
        const statusRes = await ApiService.post(ApiService.Routes.Settings.GetEmailStatus);
        
        // [CORRECCIÓN] NO cerramos el diálogo aquí. Mantenemos el spinner girando.

        if (!statusRes.success) {
            DialogManager.close(); // Aquí SÍ cerramos porque hubo error y el flujo termina
            ToastManager.show(statusRes.message, 'error');
            return;
        }

        let { status, cooldown } = statusRes;

        if (status === 'authorized') {
            DialogManager.close(); // Aquí SÍ cerramos porque el usuario ya puede editar (fin del flujo de diálogo)
            toggleEditState(targetField, true);
            return;
        }

        if (status === 'none') {
            // [OPTIMIZACIÓN] Reutilizamos el diálogo abierto cambiando el texto
            DialogManager.showLoading('Enviando código...'); 
            const reqRes = await ApiService.post(ApiService.Routes.Settings.RequestEmailVerification);
            
            // [CORRECCIÓN] NO cerramos aquí tampoco.

            if (!reqRes.success) {
                DialogManager.close(); // Cerrar por error
                ToastManager.show(reqRes.message, 'error');
                return;
            }
            ToastManager.show('Código enviado', 'success');
            cooldown = 60; 
        } else {
            ToastManager.show('Ya tienes un código activo', 'info');
        }

        // 3. Transición directa: El DialogManager reemplazará el contenido del spinner 
        // por el formulario de verificación sin cerrar la ventana.
        showVerificationDialog(targetField, cooldown || 0);

    } catch (e) {
        console.error(e);
        DialogManager.close(); // Cerrar por error crítico (catch)
        ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
    }
}

async function showVerificationDialog(targetField, initialCooldown) {
    // 1. Obtener el correo actual desde la interfaz para mostrarlo en el mensaje
    const currentEmail = document.getElementById('display-email')?.innerText.trim() || 'tu correo';

    // 2. Sobrescribir título y mensaje en las opciones del diálogo
    const dialogOptions = {
        ...DialogDefinitions.Profile.VERIFY_EMAIL,
        title: 'Busca el código que te enviamos',
        message: `Para poder hacer cambios en tu cuenta, primero debes ingresar el código que te enviamos a ${currentEmail}.`,
        onReady: (dialogElement) => bindResendLogic(dialogElement, initialCooldown)
    };

    const result = await DialogManager.confirm(dialogOptions);
    
    if (result) {
        // ... resto de la lógica de verificación (sin cambios) ...
        const code = (typeof result === 'string') ? result.trim() : '';

        if (!code) {
            ToastManager.show(I18nManager.t('js.profile.email_code_req'), 'warning');
            return;
        }

        DialogManager.showLoading('Verificando...');
        
        const verifyData = new FormData();
        verifyData.append('code', code);

        const verifyRes = await ApiService.post(ApiService.Routes.Settings.VerifyEmailCode, verifyData);
        DialogManager.close();

        if (verifyRes.success) {
            ToastManager.show('Identidad verificada. Puedes cambiar tu correo.', 'success');
            toggleEditState(targetField, true);
        } else {
            ToastManager.show(verifyRes.message, 'error');
        }
    }
}

function bindResendLogic(wrapper, initialCooldown) {
    const btnResend = wrapper.querySelector('[data-action="resend-code"]');
    const timerSpan = wrapper.querySelector('[data-element="resend-timer"]');
    
    if (!btnResend || !timerSpan) return;

    let resendInterval = null;
    let isCooldownActive = false; // [SEGURIDAD] Estado interno inmune al DOM

    const startTimer = (seconds) => {
        isCooldownActive = true; // Activar bloqueo lógico
        let timeLeft = seconds;
        
        // Estilos visuales
        btnResend.style.pointerEvents = 'none';
        btnResend.style.opacity = '0.5';
        btnResend.style.color = 'rgb(153, 153, 153)'; 
        
        // Resetear texto si quedó de "Enviando..."
        if (btnResend.innerText === 'Enviando...') {
             btnResend.childNodes[0].textContent = 'Reenviar código de verificación '; 
        }
        
        timerSpan.innerText = `(${timeLeft})`;

        if (resendInterval) clearInterval(resendInterval);

        resendInterval = setInterval(() => {
            timeLeft--;
            timerSpan.innerText = `(${timeLeft})`;
            
            if (timeLeft <= 0) {
                clearInterval(resendInterval);
                isCooldownActive = false; // Liberar bloqueo lógico
                
                // Restaurar UI
                btnResend.style.pointerEvents = 'auto';
                btnResend.style.opacity = '1';
                btnResend.style.color = ''; 
                timerSpan.innerText = '';
            }
        }, 1000);
    };

    if (initialCooldown > 0) {
        startTimer(initialCooldown);
    }

    btnResend.addEventListener('click', async (e) => {
        e.preventDefault();
        
        // [SEGURIDAD] Chequeo de variable interna, no de estilos
        if (isCooldownActive) return;

        // Guardar contenido original del nodo de texto del enlace (sin borrar el span)
        const originalText = btnResend.childNodes[0].textContent;
        btnResend.childNodes[0].textContent = 'Enviando... ';
        
        const formData = new FormData();
        formData.append('force_resend', 'true');

        try {
            const res = await ApiService.post(ApiService.Routes.Settings.RequestEmailVerification, formData);
            
            // Restaurar texto
            btnResend.childNodes[0].textContent = 'Reenviar código de verificación ';

            if (res.success) {
                ToastManager.show('Nuevo código enviado', 'success');
                startTimer(60); 
            } else {
                ToastManager.show(res.message, 'error');
            }
        } catch(err) {
            btnResend.childNodes[0].textContent = 'Reenviar código de verificación ';
            ToastManager.show(I18nManager.t('js.core.connection_error'), 'error');
        }
    });
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
    btnSave.innerText = I18nManager.t('js.settings.saving');

    const formData = new FormData();
    formData.append('field', fieldId); 
    formData.append('value', newValue);

    try {
        const res = await ApiService.post(ApiService.Routes.Settings.UpdateProfile, formData);

        if (res.success) {
            display.innerText = newValue;
            input.dataset.originalValue = newValue;
            ToastManager.show(res.message, 'success');
            toggleEditState(fieldId, false);
        } else {
            ToastManager.show(res.message, 'error');
            input.focus(); 
        }
    } catch (error) {
        ToastManager.show(I18nManager.t('js.settings.processing_error'), 'error');
    } finally {
        btnSave.disabled = false;
        btnSave.innerText = originalText;
    }
}