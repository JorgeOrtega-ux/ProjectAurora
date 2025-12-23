/**
 * public/assets/js/modules/settings/settings-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

export const SettingsController = (function() {
    
    const CONFIG = {
        activeClass: 'active',
        disabledClass: 'disabled',
        popoverSelector: '.popover-module',
        triggerSelector: '.trigger-selector',
        wrapperSelector: '.trigger-select-wrapper'
    };

    async function savePreference(key, value) {
        if (window.USER_PREFS) {
            window.USER_PREFS[key] = value;
        }

        if (key === 'theme') {
            applyTheme(value);
        }

        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('key', key);
        formData.append('value', value);

        try {
            const res = await ApiService.post('settings-handler.php', formData);
            if (!res.success) {
                Toast.show(res.message || I18n.t('js.settings.pref_error'), 'error');
            } else {
                console.log("Preferencia guardada:", key, value);
            }
        } catch (error) {
            console.error(error);
            Toast.show(I18n.t('js.settings.pref_error'), 'error');
        }
    }

    function applyTheme(theme) {
        const root = document.documentElement;
        if (theme === 'dark') {
            root.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            root.setAttribute('data-theme', 'light');
        } else {
            root.removeAttribute('data-theme');
        }
    }

    function toggleEdit(sectionId, isEditing) {
        const parent = document.querySelector(`[data-component="${sectionId}-section"]`);
        if (!parent) return;

        const viewState = parent.querySelector(`[data-state="${sectionId}-view-state"]`);
        const editState = parent.querySelector(`[data-state="${sectionId}-edit-state"]`);
        const actionsView = parent.querySelector(`[data-state="${sectionId}-actions-view"]`);
        const actionsEdit = parent.querySelector(`[data-state="${sectionId}-actions-edit"]`);
        const input = parent.querySelector('input');

        if (isEditing) {
            _switchState(viewState, editState);
            _switchState(actionsView, actionsEdit);
            if (input) {
                input.dataset.originalValue = input.value;
                input.focus();
            }
        } else {
            _switchState(editState, viewState);
            _switchState(actionsEdit, actionsView);
            if (input && input.dataset.originalValue && input.value !== input.dataset.originalValue) {
                input.value = input.dataset.originalValue;
            }
        }
    }

    async function saveData(sectionId) {
        const parent = document.querySelector(`[data-component="${sectionId}-section"]`);
        const input = parent.querySelector('input');
        const display = parent.querySelector('.text-display-value');
        const btnSave = parent.querySelector(`[data-state="${sectionId}-actions-edit"] .component-button.primary`);

        if (!input || !display) return;

        const newValue = input.value.trim();
        const originalValue = input.dataset.originalValue;

        if (newValue === originalValue) {
            toggleEdit(sectionId, false);
            return;
        }

        if(btnSave) {
            btnSave.disabled = true;
            btnSave.innerText = I18n.t('js.settings.saving');
        }

        const formData = new FormData();
        formData.append('action', 'update_profile');
        formData.append('field', sectionId); 
        formData.append('value', newValue);

        try {
            const res = await ApiService.post('settings-handler.php', formData);

            if (res.success) {
                display.innerText = newValue;
                input.dataset.originalValue = newValue;
                Toast.show(res.message, 'success');
                toggleEdit(sectionId, false);
            } else {
                Toast.show(res.message, 'error');
                input.focus(); 
            }
        } catch (error) {
            Toast.show(I18n.t('js.settings.processing_error'), 'error');
        } finally {
            if(btnSave) {
                btnSave.disabled = false;
                btnSave.innerText = I18n.t('js.settings.btn_save');
            }
        }
    }

    function _initPasswordLogic() {
        const container = document.querySelector('[data-component="password-update-section"]');
        if (!container) return; 

        container.addEventListener('click', async (e) => {
            const action = e.target.dataset.action;
            if(!action) return;

            const stage0 = container.querySelector('[data-state="password-stage-0"]'); 
            const stage1 = container.querySelector('[data-state="password-stage-1"]'); 
            const stage2 = container.querySelector('[data-state="password-stage-2"]'); 
            const parentGroup = container.closest('.component-group-item'); 

            if (action === 'pass-start-flow') {
                if(parentGroup) parentGroup.classList.add('component-group-item--stacked');
                _switchState(stage0, stage1);
                const input = document.getElementById('current-password-input');
                if(input) { input.value = ''; input.focus(); }
            }

            if (action === 'pass-cancel-flow') {
                if(parentGroup) parentGroup.classList.remove('component-group-item--stacked');
                if(stage1) { stage1.classList.remove(CONFIG.activeClass); stage1.classList.add(CONFIG.disabledClass); }
                if(stage2) { stage2.classList.remove(CONFIG.activeClass); stage2.classList.add(CONFIG.disabledClass); }
                if(stage0) { stage0.classList.remove(CONFIG.disabledClass); stage0.classList.add(CONFIG.activeClass); }
                container.querySelectorAll('input').forEach(i => i.value = '');
            }

            if (action === 'pass-go-step-2') {
                const currentPassInput = document.getElementById('current-password-input');
                const currentPass = currentPassInput.value;
                if (!currentPass) {
                    Toast.show(I18n.t('js.settings.enter_current_pass'), 'warning');
                    return;
                }
                const btn = e.target;
                const originalText = btn.innerText;
                btn.innerText = I18n.t('js.settings.verifying');
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'validate_current_password');
                formData.append('current_password', currentPass);

                try {
                    const res = await ApiService.post('settings-handler.php', formData);
                    if (res.success) {
                        _switchState(stage1, stage2);
                        const inputNew = document.getElementById('new-password-input');
                        if(inputNew) { inputNew.value = ''; inputNew.focus(); }
                        document.getElementById('repeat-password-input').value = '';
                    } else {
                        Toast.show(res.message, 'error'); // El backend devuelve "La contraseña es incorrecta" traducido si el handler soporta I18n, o fijo.
                        currentPassInput.focus();
                    }
                } catch (err) {
                    Toast.show(I18n.t('js.settings.processing_error'), 'error');
                } finally {
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            }

            if (action === 'pass-submit-final') {
                const currentPass = document.getElementById('current-password-input').value;
                const newPass = document.getElementById('new-password-input').value;
                const repeatPass = document.getElementById('repeat-password-input').value;

                if (!newPass || !repeatPass) { Toast.show(I18n.t('js.settings.fill_all'), 'warning'); return; }
                if (newPass !== repeatPass) { Toast.show(I18n.t('js.settings.pass_mismatch'), 'error'); return; }
                if (newPass.length < 6) { Toast.show(I18n.t('js.settings.pass_short'), 'warning'); return; }

                const btn = e.target;
                const originalText = btn.innerText;
                btn.innerText = I18n.t('js.settings.saving');
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'change_password');
                formData.append('current_password', currentPass);
                formData.append('new_password', newPass);

                try {
                    const res = await ApiService.post('settings-handler.php', formData);
                    if (res.success) {
                        Toast.show(I18n.t('js.settings.pass_updated'), 'success');
                        if(parentGroup) parentGroup.classList.remove('component-group-item--stacked');
                        if(stage1) { stage1.classList.remove(CONFIG.activeClass); stage1.classList.add(CONFIG.disabledClass); }
                        if(stage2) { stage2.classList.remove(CONFIG.activeClass); stage2.classList.add(CONFIG.disabledClass); }
                        if(stage0) { stage0.classList.remove(CONFIG.disabledClass); stage0.classList.add(CONFIG.activeClass); }
                        container.querySelectorAll('input').forEach(i => i.value = '');
                    } else { Toast.show(res.message, 'error'); }
                } catch(err) { Toast.show(I18n.t('js.settings.processing_error'), 'error'); } 
                finally { btn.innerText = originalText; btn.disabled = false; }
            }
        });
    }

    function _switchState(hideElement, showElement) {
        if (hideElement) {
            hideElement.classList.remove(CONFIG.activeClass);
            hideElement.classList.add(CONFIG.disabledClass);
        }
        if (showElement) {
            showElement.classList.remove(CONFIG.disabledClass);
            showElement.classList.add(CONFIG.activeClass);
        }
    }

    function toggleDropdown(wrapperElement) {
        const menu = wrapperElement.querySelector(CONFIG.popoverSelector);
        const trigger = wrapperElement.querySelector(CONFIG.triggerSelector);
        if (!menu || !trigger) return;

        const isActive = menu.classList.contains(CONFIG.activeClass);
        closeAllDropdowns();

        if (!isActive) {
            menu.classList.add(CONFIG.activeClass);
            trigger.classList.add(CONFIG.activeClass);
            wrapperElement.classList.add('dropdown-active');
        }
        if (event) event.stopPropagation();
    }

    function selectOption(itemElement, textValue, dataValue = null) {
        const wrapper = itemElement.closest(CONFIG.wrapperSelector);
        if (!wrapper) return;
        const isSameValue = itemElement.classList.contains(CONFIG.activeClass);

        const triggerText = wrapper.querySelector('.trigger-select-text');
        if (triggerText) triggerText.innerText = textValue;
        
        const newIcon = itemElement.querySelector('.material-symbols-rounded')?.innerText;
        const triggerIcon = wrapper.querySelector('.trigger-select-icon');
        if(newIcon && triggerIcon) triggerIcon.innerText = newIcon;

        wrapper.querySelectorAll('.menu-link').forEach(link => link.classList.remove(CONFIG.activeClass));
        itemElement.classList.add(CONFIG.activeClass);
        closeAllDropdowns();

        if (dataValue && (!isSameValue || dataValue === 'theme' || dataValue === 'sync' || dataValue === 'light' || dataValue === 'dark')) {
            const isTheme = ['sync', 'light', 'dark'].includes(dataValue);
            savePreference(isTheme ? 'theme' : 'language', dataValue);
        }

        if (event) event.stopPropagation();
    }

    function closeAllDropdowns() {
        document.querySelectorAll(CONFIG.popoverSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.triggerSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.wrapperSelector).forEach(el => el.classList.remove('dropdown-active'));
    }

    function _initToggles() {
        const toggleLinks = document.getElementById('pref-open-links');
        if (toggleLinks) {
            toggleLinks.addEventListener('change', (e) => {
                savePreference('open_links_new_tab', e.target.checked);
            });
        }
        const toggleToast = document.getElementById('pref-extended-toast');
        if (toggleToast) {
            toggleToast.addEventListener('change', (e) => {
                savePreference('extended_toast', e.target.checked);
            });
        }
    }

    function init() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest(CONFIG.wrapperSelector)) {
                closeAllDropdowns();
            }
        });
        
        _initToggles();
        _initPasswordLogic();

        console.log("SettingsController inicializado.");
    }

    return {
        init,
        toggleEdit,
        saveData,
        toggleDropdown,
        selectOption,
        closeAllDropdowns,
        savePreference,
        applyTheme
    };
})();

window.toggleEdit = SettingsController.toggleEdit;
window.saveData = SettingsController.saveData;
window.toggleDropdown = SettingsController.toggleDropdown;
window.selectOption = SettingsController.selectOption;