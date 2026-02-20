// public/assets/js/profile-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';

export class ProfileController {
    constructor() {
        this.init();
    }

    init() {
        document.body.addEventListener('click', (e) => {
            
            // ===============================================
            // GESTIÓN DEL AVATAR
            // ===============================================
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
                if(confirm("¿Estás seguro de que deseas eliminar tu foto de perfil?")) {
                    this.deleteAvatar(btnDeleteAvatar);
                }
                return;
            }

            // ===============================================
            // OTROS CAMPOS DE TEXTO
            // ===============================================
            const btnStartEdit = e.target.closest('[data-action="start-edit"]');
            if (btnStartEdit) { e.preventDefault(); this.handleStartEdit(btnStartEdit.dataset.target); return; }

            const btnCancelEdit = e.target.closest('[data-action="cancel-edit"]');
            if (btnCancelEdit) { e.preventDefault(); this.handleCancelEdit(btnCancelEdit.dataset.target); return; }

            // NUEVO: Envío a la base de datos
            const btnSaveField = e.target.closest('[data-action="save-field"]');
            if (btnSaveField) { e.preventDefault(); this.handleSaveField(btnSaveField.dataset.target); return; }

            const triggerSelector = e.target.closest('[data-action="toggle-dropdown"]');
            if (triggerSelector) { e.preventDefault(); this.handleDropdownToggle(triggerSelector, e); return; }

            const optionSelect = e.target.closest('[data-action="select-option"]');
            if (optionSelect) { e.preventDefault(); this.handleOptionSelect(optionSelect); return; }
        });

        document.body.addEventListener('change', (e) => {
            if (e.target.id === 'upload-avatar') {
                this.handleFileSelection(e.target);
            }
        });

        document.body.addEventListener('input', (e) => {
            const filterInput = e.target.closest('[data-action="filter-options"]');
            if (filterInput) this.handleFilter(filterInput);
        });
    }

    /* ========================================================================
       LÓGICA DEL AVATAR
       ======================================================================== */

    handleFileSelection(input) {
        const file = input.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            alert('La imagen no puede pesar más de 2MB.');
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
            } else {
                alert(res.message);
                this.cancelAvatarChange(); 
            }
        } catch (error) {
            alert('Error de red al subir la imagen.');
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
        btn.innerHTML = '<div class="component-spinner-button dark-spinner"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.DELETE_AVATAR, { csrf_token: csrfToken });
            if (res.success) {
                this.updateAvatarVisuals(res.avatar);
                this.switchAvatarControlsState('default');
            } else {
                alert(res.message);
            }
        } catch (error) {
            alert('Error al procesar la solicitud.');
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

    /* ========================================================================
       LÓGICA ACTUALIZADA DE EDICIÓN DE CAMPOS
       ======================================================================== */

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

    // NUEVO: API Request para guardar cambios
    async handleSaveField(target) {
        const inputEl = document.getElementById(`input-${target}`);
        const displayEl = document.getElementById(`display-${target}`);
        const btnSave = document.querySelector(`[data-action="save-field"][data-target="${target}"]`);
        
        if (!inputEl || !displayEl || !btnSave) return;

        const newValue = inputEl.value.trim();
        if (newValue === "") {
            alert("El campo no puede estar vacío"); 
            return;
        }

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        
        // Mapear los identificadores del Frontend a las columnas SQL
        const fieldMap = { 'username': 'nombre', 'email': 'correo' };
        const apiField = fieldMap[target];

        // Spinner Loading Effect
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
                // Actualiza el span de solo lectura con lo validado por el backend
                displayEl.textContent = res.newValue;
                this.toggleFieldState(target, 'view');
            } else {
                alert(res.message);
            }
        } catch (error) {
            alert('Error al actualizar el campo en la base de datos.');
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