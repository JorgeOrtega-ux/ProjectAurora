/**
 * ProfileController.js
 * Maneja la lógica de la sección de perfil (Datos, Foto y Preferencias).
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

/* --- LÓGICA UI PARA DROPDOWNS (Y GUARDADO DE IDIOMA) --- */
const setupDropdownUI = () => {
    document.addEventListener('click', (e) => {
        // 1. Manejar apertura/cierre
        if (e.target.closest('[data-action="toggle-dropdown"]')) {
            const trigger = e.target.closest('[data-action="toggle-dropdown"]');
            const wrapper = trigger.closest('.trigger-select-wrapper');
            const menu = wrapper.querySelector('.popover-module');
            
            // Cerrar otros
            document.querySelectorAll('.popover-module.active').forEach(el => {
                if (el !== menu) el.classList.remove('active');
            });
            document.querySelectorAll('.trigger-selector.active').forEach(el => {
                if (el !== trigger) el.classList.remove('active');
            });

            trigger.classList.toggle('active');
            menu.classList.toggle('active');
            e.stopPropagation();
            return;
        }

        // 2. Manejar selección (Visual + Guardado)
        if (e.target.closest('.menu-link')) {
            const link = e.target.closest('.menu-link');
            const menu = link.closest('.popover-module');
            const wrapper = link.closest('.trigger-select-wrapper');
            
            if (wrapper) {
                // Actualizar UI
                const triggerText = wrapper.querySelector('.trigger-select-text');
                const triggerIcon = wrapper.querySelector('.trigger-select-icon');

                const linkText = link.querySelector('.menu-link-text');
                const linkIcon = link.querySelector('.menu-link-icon span');
                const newValue = link.dataset.value; // Valor técnico (ej: es-419)

                if(triggerText && linkText) triggerText.textContent = linkText.textContent;
                if(triggerIcon && linkIcon) triggerIcon.textContent = linkIcon.textContent;

                menu.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');

                menu.classList.remove('active');
                wrapper.querySelector('.trigger-selector').classList.remove('active');
                
                // [NUEVO] LOGICA DE GUARDADO AUTOMATICO (Si es el selector de idioma)
                if (wrapper.dataset.pref === 'language') {
                    console.log("Guardando idioma:", newValue);
                    AuthService.updatePreferences({ language: newValue }).then(res => {
                        if(res.status !== 'success') alert("Error al guardar preferencia de idioma.");
                    });
                }
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
    
    const btn = document.querySelector(`[data-action="${sectionType}-save-trigger-btn"]`);
    const originalText = btn.innerText;
    btn.disabled = true; btn.innerText = "...";

    try {
        const result = await AuthService.updateProfile(currentUsername, currentEmail);

        if (result.status === 'success') {
            const display = document.querySelector(`[data-element="${sectionType}-display-text"]`);
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

/* --- LÓGICA DE PREFERENCIAS (Toggle Links) --- */
const setupPreferencesLogic = () => {
    // Escuchar cambios en el toggle de enlaces
    const linksToggle = document.getElementById('pref-links-new-tab');
    if (linksToggle) {
        linksToggle.addEventListener('change', (e) => {
            const isChecked = e.target.checked ? 1 : 0;
            console.log("Guardando preferencia enlaces:", isChecked);
            
            // Feedback visual opcional: deshabilitar temporalmente
            e.target.disabled = true;
            
            AuthService.updatePreferences({ open_links_new_tab: isChecked })
                .then(res => {
                    if (res.status !== 'success') {
                        e.target.checked = !e.target.checked; // Revertir
                        alert("Error al guardar configuración.");
                    }
                })
                .catch(err => {
                    e.target.checked = !e.target.checked;
                    alert("Error de conexión");
                })
                .finally(() => {
                    e.target.disabled = false;
                });
        });
    }
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
    setupPreferencesLogic(); // Iniciar listener de toggle
};

export const initProfileController = () => {
    console.log('ProfileController: Inicializado.');
    setupProfileListeners();
};