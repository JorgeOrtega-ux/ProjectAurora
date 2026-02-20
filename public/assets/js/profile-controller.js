// public/assets/js/profile-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';
import { Toast } from './toast-controller.js';

export class ProfileController {
    constructor() {
        this.init();
    }

    init() {
        document.body.addEventListener('click', (e) => {
            if (e.target.closest('#btn-upload-init') || e.target.closest('[data-action="profile-picture-change"]') || e.target.closest('#btn-trigger-upload')) {
                e.preventDefault();
                const fileInput = document.getElementById('upload-avatar');
                if (fileInput) fileInput.click();
                return;
            }

            if (e.target.closest('[data-action="profile-picture-cancel"]')) {
                e.preventDefault();
                this.cancelAvatarChange();
                return;
            }

            const btnSaveAvatar = e.target.closest('[data-action="profile-picture-save"]');
            if (btnSaveAvatar) {
                e.preventDefault();
                this.saveAvatar(btnSaveAvatar);
                return;
            }

            const btnDeleteAvatar = e.target.closest('[data-action="profile-picture-delete"]');
            if (btnDeleteAvatar) {
                e.preventDefault();
                window.dialogController.open('dialog-delete-avatar');
                return;
            }

            if (e.target.closest('#btn-confirm-delete-avatar')) {
                e.preventDefault();
                const btn = document.getElementById('btn-confirm-delete-avatar');
                this.deleteAvatar(btn);
                return;
            }

            if (e.target.closest('#btn-confirm-email-code')) {
                e.preventDefault();
                this.confirmEmailChange(e.target.closest('#btn-confirm-email-code'));
                return;
            }

            const btnStartEdit = e.target.closest('[data-action="start-edit"]');
            if (btnStartEdit) { e.preventDefault(); this.handleStartEdit(btnStartEdit.dataset.target); return; }

            const btnCancelEdit = e.target.closest('[data-action="cancel-edit"]');
            if (btnCancelEdit) { e.preventDefault(); this.handleCancelEdit(btnCancelEdit.dataset.target); return; }

            const btnSaveField = e.target.closest('[data-action="save-field"]');
            if (btnSaveField) { 
                e.preventDefault(); 
                if (btnSaveField.dataset.target === 'email') {
                    this.requestEmailChange(btnSaveField);
                } else {
                    this.handleSaveField(btnSaveField.dataset.target); 
                }
                return; 
            }

            const triggerSelector = e.target.closest('[data-action="toggle-dropdown"]');
            if (triggerSelector) { e.preventDefault(); this.handleDropdownToggle(triggerSelector, e); return; }

            const optionSelect = e.target.closest('[data-action="select-option"]');
            if (optionSelect) { 
                e.preventDefault(); 
                this.handleOptionSelect(optionSelect); 
                
                if (window.preferencesController) {
                    window.preferencesController.updatePreference('language', optionSelect.dataset.value);
                }
                return; 
            }
        });

        document.body.addEventListener('change', (e) => {
            if (e.target.id === 'upload-avatar') {
                this.handleFileSelection(e.target);
            }

            if (e.target.id === 'pref-open-links' || e.target.id === 'pref-open-links-guest') {
                if (window.preferencesController) {
                    window.preferencesController.updatePreference('open_links_new_tab', e.target.checked);
                }
            }
        });

        document.body.addEventListener('input', (e) => {
            const filterInput = e.target.closest('[data-action="filter-options"]');
            if (filterInput) this.handleFilter(filterInput);
        });
    }

    handleFileSelection(input) {
        const file = input.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            Toast.show(window.t('js.profile.err_img_size'), 'error');
            input.value = ''; 
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('preview-avatar');
            if (preview) preview.src = e.target.result;
            this.switchAvatarControlsState('preview');
        };
        reader.readAsDataURL(file);
    }

    cancelAvatarChange() {
        const preview = document.getElementById('preview-avatar');
        const input = document.getElementById('upload-avatar');
        if (input) input.value = ''; 
        if (preview) {
            const originalSrc = preview.getAttribute('data-original-src');
            preview.src = originalSrc;
            
            if (originalSrc.includes('/default/')) {
                this.switchAvatarControlsState('default');
            } else {
                this.switchAvatarControlsState('custom');
            }
        }
    }

    async saveAvatar(btn) {
        const input = document.getElementById('upload-avatar');
        const file = input.files[0];
        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';

        if (!file) return;

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('csrf_token', csrfToken);

        try {
            const res = await ApiService.postFormData(API_ROUTES.SETTINGS.UPLOAD_AVATAR, formData);
            
            if (res.success) {
                this.updateAvatarVisuals(res.avatar);
                this.switchAvatarControlsState('custom'); 
                Toast.show(window.t(res.message) || 'Foto de perfil actualizada', 'success'); 
            } else {
                Toast.show(window.t(res.message), 'error');
                this.cancelAvatarChange(); 
            }
        } catch (error) {
            Toast.show(window.t('js.profile.err_net'), 'error');
            this.cancelAvatarChange();
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
            input.value = '';
        }
    }

    async deleteAvatar(btn) {
        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button" style="border-top-color: #d32f2f;"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.DELETE_AVATAR, { csrf_token: csrfToken });
            if (res.success) {
                this.updateAvatarVisuals(res.avatar);
                this.switchAvatarControlsState('default');
                window.dialogController.close('dialog-delete-avatar');
                Toast.show(window.t(res.message) || 'Foto de perfil eliminada', 'success');
            } else {
                Toast.show(window.t(res.message), 'error');
            }
        } catch (error) {
            Toast.show(window.t('js.profile.err_proc'), 'error'); 
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    updateAvatarVisuals(newPath) {
        const preview = document.getElementById('preview-avatar');
        const headerAvatar = document.getElementById('user-avatar-img');

        let finalPath = newPath;
        if (!finalPath.startsWith('/ProjectAurora/')) {
            finalPath = '/ProjectAurora/' + finalPath.replace(/^\//, '');
        }

        if (preview) {
            preview.src = finalPath;
            preview.setAttribute('data-original-src', finalPath);
        }
        if (headerAvatar) {
            headerAvatar.src = finalPath;
        }
    }

    switchAvatarControlsState(state) {
        document.querySelectorAll('[data-state^="profile-picture-actions-"]').forEach(el => {
            el.classList.replace('active', 'disabled');
        });

        const target = document.querySelector(`[data-state="profile-picture-actions-${state}"]`);
        if (target) {
            target.classList.replace('disabled', 'active');
        }
    }

    toggleFieldState(target, mode) {
        const viewState = document.querySelector(`[data-state="${target}-view-state"]`);
        const editState = document.querySelector(`[data-state="${target}-edit-state"]`);
        const viewActions = document.querySelector(`[data-state="${target}-actions-view"]`);
        const editActions = document.querySelector(`[data-state="${target}-actions-edit"]`);

        if (!viewState || !editState || !viewActions || !editActions) return;

        if (mode === 'edit') {
            viewState.classList.replace('active', 'disabled');
            viewActions.classList.replace('active', 'disabled');
            editState.classList.replace('disabled', 'active');
            editActions.classList.replace('disabled', 'active');
            const input = document.getElementById(`input-${target}`);
            if (input) input.focus();
        } else {
            editState.classList.replace('active', 'disabled');
            editActions.classList.replace('active', 'disabled');
            viewState.classList.replace('disabled', 'active');
            viewActions.classList.replace('disabled', 'active');
        }
    }

    handleStartEdit(target) { this.toggleFieldState(target, 'edit'); }
    
    handleCancelEdit(target) {
        const originalValue = document.getElementById(`display-${target}`).textContent;
        const inputEl = document.getElementById(`input-${target}`);
        if (inputEl) inputEl.value = originalValue;
        this.toggleFieldState(target, 'view');
    }

    async requestEmailChange(btn) {
        const inputEl = document.getElementById('input-email');
        const newEmail = inputEl.value.trim();
        const displayEl = document.getElementById('display-email');
        
        if (newEmail === "" || newEmail === displayEl.textContent) {
            this.toggleFieldState('email', 'view');
            return;
        }

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.REQUEST_EMAIL_CHANGE, { 
                new_email: newEmail, 
                csrf_token: csrfToken 
            });
            
            if (res.success) {
                Toast.show(window.t(res.message) || 'Código enviado', 'success');
                window.dialogController.open('dialog-verify-email');
            } else { 
                Toast.show(window.t(res.message), 'error'); 
            }
        } catch (error) {
            Toast.show(window.t('js.profile.err_db'), 'error'); 
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async confirmEmailChange(btn) {
        const codeInput = document.getElementById('input-email-code');
        const code = codeInput.value.trim();
        const inputEl = document.getElementById('input-email');
        const displayEl = document.getElementById('display-email');
        
        if (!code || code.length !== 6) {
            Toast.show('Por favor ingresa un código de 6 dígitos válido', 'error');
            return;
        }

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.CONFIRM_EMAIL_CHANGE, { 
                code: code, 
                csrf_token: csrfToken 
            });
            
            if (res.success) {
                displayEl.textContent = res.newValue;
                inputEl.value = res.newValue;
                this.toggleFieldState('email', 'view');
                window.dialogController.close('dialog-verify-email');
                Toast.show(window.t(res.message) || 'Correo actualizado', 'success');
            } else { 
                Toast.show(window.t(res.message), 'error'); 
            }
        } catch (error) {
            Toast.show(window.t('js.profile.err_db'), 'error'); 
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async handleSaveField(target) {
        const inputEl = document.getElementById(`input-${target}`);
        const displayEl = document.getElementById(`display-${target}`);
        const btnSave = document.querySelector(`[data-action="save-field"][data-target="${target}"]`);
        
        if (!inputEl || !displayEl || !btnSave) return;

        const newValue = inputEl.value.trim();
        if (newValue === "") {
            Toast.show(window.t('js.profile.err_empty'), 'error');
            return;
        }

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const fieldMap = { 'username': 'nombre' }; 
        const apiField = fieldMap[target];

        const originalText = btnSave.textContent;
        btnSave.disabled = true;
        btnSave.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.UPDATE_FIELD, { 
                field: apiField, 
                value: newValue, 
                csrf_token: csrfToken 
            });
            
            if (res.success) {
                displayEl.textContent = res.newValue;
                this.toggleFieldState(target, 'view');
                Toast.show(window.t(res.message) || 'Actualizado correctamente', 'success'); 
            } else { 
                Toast.show(window.t(res.message), 'error');
            }
        } catch (error) {
            Toast.show(window.t('js.profile.err_db'), 'error'); 
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = originalText;
        }
    }

    handleDropdownToggle(selector, event) {
        event.stopPropagation();
        const wrapper = selector.closest('.component-dropdown');
        const module = wrapper.querySelector('.component-module');
        this.closeAllDropdowns(module);
        if (module) module.classList.toggle('disabled');
    }

    handleOptionSelect(option) {
        const wrapper = option.closest('.component-dropdown');
        const module = wrapper.querySelector('.component-module');
        const textDisplay = wrapper.querySelector('.component-dropdown-text');
        textDisplay.textContent = option.dataset.label;
        
        module.querySelectorAll('.component-menu-link').forEach(link => link.classList.remove('active'));
        option.classList.add('active');
        module.classList.add('disabled');
    }

    handleFilter(searchInput) {
        const module = searchInput.closest('.component-module');
        const term = searchInput.value.toLowerCase().trim();
        module.querySelectorAll('.component-menu-link').forEach(link => {
            const label = link.dataset.label.toLowerCase();
            link.style.display = label.includes(term) ? 'flex' : 'none';
        });
    }

    closeAllDropdowns(exceptThisOne = null) {
        document.querySelectorAll('.component-dropdown .component-module:not(.disabled)').forEach(m => {
            if (m !== exceptThisOne) m.classList.add('disabled');
        });
    }
}