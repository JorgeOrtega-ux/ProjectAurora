/**
 * ProfileController.js
 * Maneja la lógica de la sección de perfil (Datos y Foto).
 */

import { AuthService } from './api-services.js';

/* --- UTILIDADES --- */
const toggleEditMode = (section, isEditing) => {
    const viewState = section.querySelector('[data-state$="-view-state"]');
    const editState = section.querySelector('[data-state$="-edit-state"]');
    const actionsView = section.querySelector('[data-state$="-actions-view"]');
    const actionsEdit = section.querySelector('[data-state$="-actions-edit"]');

    if (isEditing) {
        if(viewState) { viewState.classList.remove('active'); viewState.classList.add('disabled'); }
        if(actionsView) { actionsView.classList.remove('active'); actionsView.classList.add('disabled'); }
        if(editState) { editState.classList.remove('disabled'); editState.classList.add('active'); }
        if(actionsEdit) { actionsEdit.classList.remove('disabled'); actionsEdit.classList.add('active'); }
        
        const input = section.querySelector('input');
        if(input) {
            // [CORRECCIÓN CURSOR]: 
            // Guardamos el valor, lo vaciamos y lo restauramos.
            // Esto obliga al navegador a poner el cursor al final del texto.
            const val = input.value;
            input.value = '';
            input.value = val;
            
            input.focus();
        }
    } else {
        if(editState) { editState.classList.remove('active'); editState.classList.add('disabled'); }
        if(actionsEdit) { actionsEdit.classList.remove('active'); actionsEdit.classList.add('disabled'); }
        if(viewState) { viewState.classList.remove('disabled'); viewState.classList.add('active'); }
        if(actionsView) { actionsView.classList.remove('disabled'); actionsView.classList.add('active'); }
    }
};

/* --- LÓGICA UI PARA DROPDOWNS --- */
const setupDropdownUI = () => {
    document.addEventListener('click', (e) => {
        // 1. Manejar apertura/cierre
        if (e.target.closest('[data-action="toggle-dropdown"]')) {
            const trigger = e.target.closest('[data-action="toggle-dropdown"]');
            const wrapper = trigger.closest('.trigger-select-wrapper');
            const menu = wrapper.querySelector('.popover-module');
            
            // Cerrar otros abiertos
            document.querySelectorAll('.popover-module.active').forEach(el => {
                if (el !== menu) el.classList.remove('active');
            });
            document.querySelectorAll('.trigger-selector.active').forEach(el => {
                if (el !== trigger) el.classList.remove('active');
            });

            // Toggle actual
            trigger.classList.toggle('active');
            menu.classList.toggle('active');
            e.stopPropagation();
            return;
        }

        // 2. Manejar selección (Solo visual)
        if (e.target.closest('.menu-link')) {
            const link = e.target.closest('.menu-link');
            const menu = link.closest('.popover-module');
            const wrapper = link.closest('.trigger-select-wrapper');
            
            if (wrapper) {
                const triggerText = wrapper.querySelector('.trigger-select-text');
                const triggerIcon = wrapper.querySelector('.trigger-select-icon');

                const linkText = link.querySelector('.menu-link-text');
                const linkIcon = link.querySelector('.menu-link-icon span');

                if(triggerText && linkText) {
                    triggerText.textContent = linkText.textContent;
                }
                
                if(triggerIcon && linkIcon) {
                    triggerIcon.textContent = linkIcon.textContent;
                }

                menu.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');

                menu.classList.remove('active');
                wrapper.querySelector('.trigger-selector').classList.remove('active');
            }
        }

        // 3. Cerrar al hacer clic fuera
        if (!e.target.closest('.trigger-select-wrapper')) {
            document.querySelectorAll('.popover-module.active').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.trigger-selector.active').forEach(el => el.classList.remove('active'));
        }
    });
};

/* --- LÓGICA DE ACTUALIZACIÓN DE DATOS (TEXTO) --- */
const handleProfileSave = async (sectionType) => {
    const usernameInput = document.querySelector('[data-element="username-input"]');
    const emailInput = document.querySelector('[data-element="email-input"]');
    if (!usernameInput || !emailInput) return;

    const currentUsername = usernameInput.value.trim();
    const currentEmail = emailInput.value.trim();
    
    // UI Loading
    const btn = document.querySelector(`[data-action="${sectionType}-save-trigger-btn"]`);
    const originalText = btn.innerText;
    btn.disabled = true; btn.innerText = "...";

    try {
        const result = await AuthService.updateProfile(currentUsername, currentEmail);

        if (result.status === 'success') {
            const display = document.querySelector(`[data-element="${sectionType}-display-text"]`);
            
            // [CORRECCIÓN ARROBA]: Quitamos el '@' manual aquí para que no se agregue al guardar
            if (display) display.innerText = (sectionType === 'username') ? currentUsername : currentEmail;
            
            const section = document.querySelector(`[data-component="${sectionType}-section"]`);
            toggleEditMode(section, false);
        } else {
            alert(result.message || "Error al actualizar.");
        }
    } catch (error) {
        console.error(error);
        alert("Error de conexión.");
    } finally {
        btn.disabled = false; btn.innerText = originalText;
    }
};

/* --- LÓGICA DE FOTO DE PERFIL --- */
const setupProfilePictureLogic = (pfpSection) => {
    const fileInput = pfpSection.querySelector('[data-element="profile-picture-upload-input"]');
    const previewImg = pfpSection.querySelector('[data-element="profile-picture-preview-image"]');
    const actionsDefault = pfpSection.querySelector('[data-state="profile-picture-actions-default"]');
    const actionsPreview = pfpSection.querySelector('[data-state="profile-picture-actions-preview"]');
    const btnDelete = pfpSection.querySelector('[data-action="profile-picture-remove-trigger"]');
    const btnUploadText = pfpSection.querySelector('[data-element="upload-btn-text"]');
    
    let originalSrc = previewImg.src;

    const triggerUpload = () => fileInput.click();
    
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (evt) => {
                previewImg.src = evt.target.result; 
                actionsDefault.classList.remove('active'); actionsDefault.classList.add('disabled');
                actionsPreview.classList.remove('disabled'); actionsPreview.classList.add('active');
            };
            reader.readAsDataURL(file);
        }
    });

    pfpSection.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="profile-picture-cancel-trigger"]')) {
            previewImg.src = originalSrc; 
            fileInput.value = ''; 
            actionsPreview.classList.remove('active'); actionsPreview.classList.add('disabled');
            actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
        }

        if (e.target.closest('[data-action="profile-picture-save-trigger-btn"]')) {
            const file = fileInput.files[0];
            if(!file) return;

            const btn = e.target.closest('button');
            const txt = btn.innerText;
            btn.innerText = "Guardando..."; btn.disabled = true;

            AuthService.uploadProfilePicture(file).then(res => {
                if(res.status === 'success') {
                    originalSrc = res.data.url;
                    previewImg.src = originalSrc;
                    
                    const headerImg = document.querySelector('.profile-img');
                    if(headerImg) headerImg.src = originalSrc;

                    pfpSection.dataset.hasCustom = "true";
                    btnDelete.style.display = "flex";
                    btnUploadText.innerText = "Cambiar";

                    actionsPreview.classList.remove('active'); actionsPreview.classList.add('disabled');
                    actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
                    fileInput.value = '';
                } else {
                    alert(res.message);
                }
            }).catch(err => {
                console.error(err);
                alert("Error de red");
            }).finally(() => {
                btn.innerText = txt; btn.disabled = false;
            });
        }

        if (e.target.closest('[data-action="profile-picture-remove-trigger"]')) {
            if(!confirm("¿Eliminar foto de perfil actual?")) return;
            btnDelete.disabled = true;
            AuthService.deleteProfilePicture().then(res => {
                if(res.status === 'success') {
                    originalSrc = res.data.url;
                    previewImg.src = originalSrc;
                    const headerImg = document.querySelector('.profile-img');
                    if(headerImg) headerImg.src = originalSrc;
                    pfpSection.dataset.hasCustom = "false";
                    btnDelete.style.display = "none";
                    btnUploadText.innerText = "Subir foto";
                } else {
                    alert(res.message);
                }
            }).finally(() => {
                btnDelete.disabled = false;
            });
        }
        
        if (e.target.closest('[data-action="trigger-profile-picture-upload"]') || 
            e.target.closest('[data-action="profile-picture-upload-trigger"]')) {
            triggerUpload();
        }
    });
};

const setupProfileListeners = () => {
    document.addEventListener('click', (e) => {
        const target = e.target;
        if (target.matches('[data-action$="-edit-trigger"]')) {
            const type = target.dataset.action.split('-')[0]; 
            const section = target.closest(`[data-component="${type}-section"]`);
            if(section) {
                const input = section.querySelector('input');
                input.dataset.original = input.value;
                toggleEditMode(section, true);
            }
        }

        if (target.matches('[data-action$="-cancel-trigger"]')) {
            const type = target.dataset.action.split('-')[0];
            if(type !== 'profile') {
                const section = target.closest(`[data-component="${type}-section"]`);
                const input = section.querySelector('input');
                input.value = input.dataset.original || input.value;
                toggleEditMode(section, false);
            }
        }

        if (target.matches('[data-action$="-save-trigger-btn"]')) {
            const type = target.dataset.action.split('-')[0];
            if(type !== 'profile') handleProfileSave(type);
        }
    });

    const pfpSection = document.querySelector('[data-component="profile-picture-section"]');
    if (pfpSection) setupProfilePictureLogic(pfpSection);
    
    setupDropdownUI();
};

export const initProfileController = () => {
    console.log('ProfileController: Inicializado.');
    setupProfileListeners();
};