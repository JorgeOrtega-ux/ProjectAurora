/**
 * ProfileController.js
 * Maneja la lógica de la sección de perfil (Datos, Foto y Preferencias).
 */

import { SettingsService } from './api-services.js';
import { Toast } from './toast-service.js';
import { applyAppTheme } from './main-controller.js'; // IMPORTANTE: Importamos para aplicar cambios en tiempo real

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

/* --- LÓGICA UI PARA DROPDOWNS --- */
const setupDropdownUI = () => {
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="toggle-dropdown"]')) {
            const trigger = e.target.closest('[data-action="toggle-dropdown"]');
            const wrapper = trigger.closest('.trigger-select-wrapper');
            const menu = wrapper.querySelector('.popover-module');
            
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

        if (e.target.closest('.menu-link')) {
            const link = e.target.closest('.menu-link');
            const menu = link.closest('.popover-module');
            const wrapper = link.closest('.trigger-select-wrapper');
            
            if (wrapper) {
                const triggerText = wrapper.querySelector('.trigger-select-text');
                const triggerIcon = wrapper.querySelector('.trigger-select-icon');

                const linkText = link.querySelector('.menu-link-text');
                const linkIcon = link.querySelector('.menu-link-icon span'); 
                const newValue = link.dataset.value;

                if(triggerText && linkText) triggerText.textContent = linkText.textContent;
                
                if(wrapper.dataset.pref === 'theme' && triggerIcon && linkIcon) {
                     triggerIcon.textContent = linkIcon.textContent;
                }

                menu.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');

                menu.classList.remove('active');
                wrapper.querySelector('.trigger-selector').classList.remove('active');
                
                // --- LÓGICA DE GUARDADO ---
                if (wrapper.dataset.pref === 'language') {
                    SettingsService.updatePreferences({ language: newValue }).then(res => {
                        if(res.status !== 'success') Toast.error(window.t('global.error'));
                        else window.location.reload(); 
                    });
                }

                if (wrapper.dataset.pref === 'theme') {
                    // MODIFICADO: Aplicar tema inmediatamente y guardar
                    SettingsService.updatePreferences({ theme: newValue }).then(res => {
                        if(res.status === 'success') {
                            Toast.success(res.message);
                            
                            // Actualizar preferencia global y aplicar visualmente
                            if(window.USER_PREFS) window.USER_PREFS.theme = newValue;
                            applyAppTheme(newValue);
                        }
                        else Toast.error(res.message || window.t('global.error'));
                    });
                }
            }
        }

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
    btn.disabled = true; 
    btn.innerText = window.t('global.processing');

    try {
        const result = await SettingsService.updateProfile(currentUsername, currentEmail);

        if (result.status === 'success') {
            Toast.success(result.message);
            const display = document.querySelector(`[data-element="${sectionType}-display-text"]`);
            if (display) display.innerText = (sectionType === 'username') ? currentUsername : currentEmail;
            
            const section = document.querySelector(`[data-component="${sectionType}-section"]`);
            toggleEditMode(section, false);
        } else {
            Toast.error(result.message || window.t('global.error'));
        }
    } catch (error) {
        console.error(error);
        Toast.error(window.t('js.error.connection'));
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
            btn.innerText = window.t('global.processing'); 
            btn.disabled = true;

            SettingsService.uploadProfilePicture(file).then(res => {
                if(res.status === 'success') {
                    Toast.success(res.message);
                    originalSrc = res.data.url;
                    previewImg.src = originalSrc;
                    const headerImg = document.querySelector('.profile-img');
                    if(headerImg) headerImg.src = originalSrc;
                    pfpSection.dataset.hasCustom = "true";
                    btnDelete.style.display = "flex";
                    btnUploadText.innerText = window.t('settings.profile.change_btn');
                    actionsPreview.classList.remove('active'); actionsPreview.classList.add('disabled');
                    actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
                    fileInput.value = '';
                } else {
                    Toast.error(res.message);
                }
            }).catch(err => {
                console.error(err);
                Toast.error(window.t('js.error.connection'));
            }).finally(() => {
                btn.innerText = txt; btn.disabled = false;
            });
        }

        if (e.target.closest('[data-action="profile-picture-remove-trigger"]')) {
            if(!confirm(window.t('global.delete') + "?")) return; 
            btnDelete.disabled = true;

            SettingsService.deleteProfilePicture().then(res => {
                if(res.status === 'success') {
                    Toast.success(res.message);
                    originalSrc = res.data.url;
                    previewImg.src = originalSrc;
                    const headerImg = document.querySelector('.profile-img');
                    if(headerImg) headerImg.src = originalSrc;
                    pfpSection.dataset.hasCustom = "false";
                    btnDelete.style.display = "none";
                    btnUploadText.innerText = window.t('settings.profile.upload_btn');
                } else {
                    Toast.error(res.message);
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

/* --- LÓGICA DE PREFERENCIAS --- */
const setupPreferencesLogic = () => {
    
    // Función genérica para manejar toggles
    const handleToggle = (elementId, fieldName) => {
        const toggle = document.getElementById(elementId);
        if (toggle) {
            toggle.addEventListener('change', (e) => {
                const isChecked = e.target.checked ? 1 : 0;
                
                // NOTA: NO deshabilitamos el input aquí para cumplir con el requisito de "Sin Debounce".
                // Dejamos que el usuario haga clic libremente, el backend se encarga de silenciar ráfagas.
                
                const payload = {};
                payload[fieldName] = isChecked;

                SettingsService.updatePreferences(payload)
                    .then(res => {
                        if (res.status === 'success') {
                            Toast.success(res.message);
                            // Actualizar variable global en tiempo real
                            if (window.USER_PREFS) {
                                window.USER_PREFS[fieldName] = isChecked;
                            }
                        } else {
                            // Revertir visualmente si hubo error (Ej. Spam block)
                            e.target.checked = !e.target.checked; 
                            Toast.error(res.message || window.t('global.error'));
                            
                            // Si el error fue por Rate Limit, bloqueamos visualmente por unos segundos para educar al usuario
                            // El mensaje "espera unos minutos" suele venir del backend en este caso
                            if (res.message && (res.message.toLowerCase().includes('espera') || res.message.toLowerCase().includes('wait'))) {
                                e.target.disabled = true;
                                setTimeout(() => {
                                    e.target.disabled = false;
                                }, 5000); 
                            }
                        }
                    })
                    .catch(err => {
                        e.target.checked = !e.target.checked;
                        Toast.error(window.t('js.error.connection'));
                    });
            });
        }
    };

    // 1. Abrir enlaces en pestaña nueva (Perfil)
    handleToggle('pref-links-new-tab', 'open_links_new_tab');

    // 2. Alertas Extendidas (Accesibilidad)
    handleToggle('pref-extended-alerts', 'extended_alerts');
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
    setupPreferencesLogic();
};

export const initProfileController = () => {
    console.log('ProfileController: Inicializado.');
    setupProfileListeners();
};