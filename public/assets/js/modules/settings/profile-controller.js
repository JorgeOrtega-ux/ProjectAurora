/**
 * public/assets/js/modules/settings/profile-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

export const ProfileController = {
    
    init: () => {
        const fileInput = document.getElementById('upload-avatar');
        const previewImg = document.getElementById('preview-avatar');
        
        if (!fileInput || !previewImg) return;

        console.log("ProfileController: Inicializado");

        let originalSrc = previewImg.src;

        // PREVIEW
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

        // BOTONES
        const container = document.querySelector('[data-component="profile-picture-section"]');
        
        container.addEventListener('click', async (e) => {
            const action = e.target.dataset.action;
            if (!action) return;

            // CANCELAR
            if (action === 'profile-picture-cancel') {
                previewImg.src = originalSrc;
                fileInput.value = ''; 
                
                const isCustom = originalSrc.includes('/custom/');
                toggleProfileActions(isCustom ? 'custom' : 'default');
            }

            // GUARDAR
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

                const res = await ApiService.post('settings-handler.php', formData);

                btn.innerText = originalText;
                btn.disabled = false;

                if (res.success) {
                    Toast.show(I18n.t('js.profile.pic_updated'), 'success');
                    originalSrc = previewImg.src; 
                    toggleProfileActions('custom');
                    fileInput.value = ''; 
                } else {
                    Toast.show(res.message, 'error');
                }
            }

            // ELIMINAR
            if (action === 'profile-picture-delete') {
                if(!confirm(I18n.t('js.profile.confirm_delete'))) return;

                const btn = e.target;
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'delete_avatar');

                const res = await ApiService.post('settings-handler.php', formData);

                btn.disabled = false;

                if (res.success) {
                    Toast.show(I18n.t('js.profile.pic_deleted'), 'info');
                    window.location.reload(); 
                } else {
                    Toast.show(res.message, 'error');
                }
            }

            // CAMBIAR
            if (action === 'profile-picture-change') {
                fileInput.click();
            }
        });
    }
};

function toggleProfileActions(state) {
    const actionsDefault = document.querySelector('[data-state="profile-picture-actions-default"]'); 
    const actionsPreview = document.querySelector('[data-state="profile-picture-actions-preview"]'); 
    const actionsCustom  = document.querySelector('[data-state="profile-picture-actions-custom"]'); 

    if(actionsDefault) actionsDefault.classList.add('disabled');
    if(actionsDefault) actionsDefault.classList.remove('active');
    
    if(actionsPreview) actionsPreview.classList.add('disabled');
    if(actionsPreview) actionsPreview.classList.remove('active');
    
    if(actionsCustom)  actionsCustom.classList.add('disabled');
    if(actionsCustom)  actionsCustom.classList.remove('active');

    if (state === 'default' && actionsDefault) {
        actionsDefault.classList.remove('disabled');
        actionsDefault.classList.add('active');
    } else if (state === 'preview' && actionsPreview) {
        actionsPreview.classList.remove('disabled');
        actionsPreview.classList.add('active');
    } else if (state === 'custom' && actionsCustom) {
        actionsCustom.classList.remove('disabled');
        actionsCustom.classList.add('active');
    }
}