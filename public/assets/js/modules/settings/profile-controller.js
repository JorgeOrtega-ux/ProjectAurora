/**
 * ProfileController.js
 * Maneja la lógica de la sección de perfil (Datos, Foto y Preferencias).
 */

import { SettingsService } from '../../core/api-services.js';
import { Toast } from '../../core/ui/toast-service.js';
import { applyAppTheme } from '../layout/main-controller.js';
import { navigateTo } from '../../core/url-manager.js';

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

/* --- FUNCIÓN MÁGICA DE ACTUALIZACIÓN DE TEXTOS --- */
const updateLanguageTexts = () => {
    document.querySelectorAll('[data-lang-key]').forEach(el => {
        const key = el.dataset.langKey;
        if (window.t(key)) {
            el.textContent = window.t(key);
        }
    });

    document.querySelectorAll('[data-lang-placeholder]').forEach(el => {
        const key = el.dataset.langPlaceholder;
        if (window.t(key)) {
            el.placeholder = window.t(key);
        }
    });
    
    document.querySelectorAll('[data-lang-tooltip]').forEach(el => {
        const key = el.dataset.langTooltip;
        if (window.t(key)) {
            el.dataset.tooltip = window.t(key);
        }
    });
};

/* --- LÓGICA UI PARA DROPDOWNS --- */
const setupDropdownUI = () => {
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="toggle-dropdown"]')) {
            const trigger = e.target.closest('[data-action="toggle-dropdown"]');
            const wrapper = trigger.closest('.trigger-select-wrapper');
            const menu = wrapper.querySelector('.popover-module');
            
            if (wrapper.classList.contains('disabled-interactive')) return;

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
                
                wrapper.classList.add('disabled-interactive');
                wrapper.style.opacity = '0.7';

                if (wrapper.dataset.pref === 'language') {
                    SettingsService.updatePreferences({ language: newValue })
                        .then(res => {
                            if(res.status === 'success') {
                                Toast.success(res.message);
                                if (res.data && res.data.translations) {
                                    window.TRANSLATIONS = res.data.translations;
                                }
                                updateLanguageTexts();
                            } else {
                                Toast.error(res.message || window.t('global.error'));
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Toast.error(window.t('js.error.connection'));
                        })
                        .finally(() => {
                            wrapper.classList.remove('disabled-interactive');
                            wrapper.style.opacity = '1';
                        });
                }

                if (wrapper.dataset.pref === 'theme') {
                    SettingsService.updatePreferences({ theme: newValue })
                        .then(res => {
                            if(res.status === 'success') {
                                Toast.success(res.message);
                                if(window.USER_PREFS) window.USER_PREFS.theme = newValue;
                                applyAppTheme(newValue);
                            } else {
                                Toast.error(res.message || window.t('global.error'));
                            }
                        })
                        .finally(() => {
                            wrapper.classList.remove('disabled-interactive');
                            wrapper.style.opacity = '1';
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
    // Configuración de límites
    const config = window.SERVER_CONFIG || {};
    const minUser = config.min_username_length || 6;
    const maxEmail = config.max_email_length || 255;

    const usernameInput = document.querySelector('[data-element="username-input"]');
    const emailInput = document.querySelector('[data-element="email-input"]');
    if (!usernameInput || !emailInput) return;

    const currentUsername = usernameInput.value.trim();
    const currentEmail = emailInput.value.trim();

    // Validaciones Client-Side Dinámicas
    if (sectionType === 'username') {
        if (currentUsername.length < minUser) {
            Toast.error(window.t('api.error.username_short', minUser));
            return;
        }
    }
    if (sectionType === 'email') {
        if (currentEmail.length > maxEmail) {
            Toast.error(window.t('api.error.email_format') + ` (Max ${maxEmail})`);
            return;
        }
    }
    
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
                    btnUploadText.dataset.langKey = 'settings.profile.change_btn';
                    
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
                    btnUploadText.dataset.langKey = 'settings.profile.upload_btn';

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

const setupPreferencesLogic = () => {
    const handleToggle = (elementId, fieldName) => {
        const toggle = document.getElementById(elementId);
        if (toggle) {
            toggle.addEventListener('change', (e) => {
                const isChecked = e.target.checked ? 1 : 0;
                
                const wrapper = e.target.closest('.component-toggle-switch');
                if (wrapper) {
                    wrapper.classList.add('disabled-interactive');
                    wrapper.style.opacity = '0.7';
                }
                
                e.target.disabled = true;

                const payload = {};
                payload[fieldName] = isChecked;

                SettingsService.updatePreferences(payload)
                    .then(res => {
                        if (res.status === 'success') {
                            Toast.success(res.message);
                            if (window.USER_PREFS) {
                                window.USER_PREFS[fieldName] = isChecked;
                            }
                        } else {
                            e.target.checked = !e.target.checked; 
                            Toast.error(res.message || window.t('global.error'));
                            if (res.message && (res.message.toLowerCase().includes('espera') || res.message.toLowerCase().includes('wait'))) {
                                setTimeout(() => { 
                                    e.target.disabled = false;
                                    if(wrapper) {
                                        wrapper.classList.remove('disabled-interactive');
                                        wrapper.style.opacity = '1';
                                    }
                                }, 5000);
                                return;
                            }
                        }
                    })
                    .catch(err => {
                        e.target.checked = !e.target.checked;
                        Toast.error(window.t('js.error.connection'));
                    })
                    .finally(() => {
                        e.target.disabled = false;
                        if (wrapper) {
                            wrapper.classList.remove('disabled-interactive');
                            wrapper.style.opacity = '1';
                        }
                    });
            });
        }
    };

    handleToggle('pref-links-new-tab', 'open_links_new_tab');
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