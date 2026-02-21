// public/assets/js/controllers/admin-manage-user-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class AdminManageUserController {
    constructor() {
        this.init();
    }

    init() {
        document.body.addEventListener('click', (e) => {
            const view = document.getElementById('admin-manage-user-view');
            if (!view) return;

            // --- FOTO DE PERFIL ---
            if (e.target.closest('#admin-btn-upload-init') || e.target.closest('[data-action="admin-avatar-change"]') || e.target.closest('#admin-btn-trigger-upload')) {
                e.preventDefault();
                const fileInput = document.getElementById('admin-upload-avatar');
                if (fileInput) fileInput.click();
                return;
            }

            if (e.target.closest('[data-action="admin-avatar-cancel"]')) {
                e.preventDefault();
                this.cancelAvatarChange();
                return;
            }

            const btnSaveAvatar = e.target.closest('[data-action="admin-avatar-save"]');
            if (btnSaveAvatar) {
                e.preventDefault();
                this.saveAvatar(btnSaveAvatar);
                return;
            }

            const btnDeleteAvatar = e.target.closest('[data-action="admin-avatar-delete"]');
            if (btnDeleteAvatar) {
                e.preventDefault();
                if(confirm("¿Forzar eliminación del avatar de este usuario?")) {
                    this.deleteAvatar(btnDeleteAvatar);
                }
                return;
            }

            // --- CAMPOS DE TEXTO ---
            const btnStartEdit = e.target.closest('[data-action="admin-start-edit"]');
            if (btnStartEdit) { e.preventDefault(); this.toggleFieldState(btnStartEdit.dataset.target, 'edit'); return; }

            const btnCancelEdit = e.target.closest('[data-action="admin-cancel-edit"]');
            if (btnCancelEdit) { e.preventDefault(); this.handleCancelEdit(btnCancelEdit.dataset.target); return; }

            const btnSaveField = e.target.closest('[data-action="admin-save-field"]');
            if (btnSaveField) { 
                e.preventDefault(); 
                this.handleSaveField(btnSaveField.dataset.target, btnSaveField);
                return; 
            }

            // --- DROPDOWNS DE PREFERENCIAS ---
            const adminDropdownTrigger = e.target.closest('[data-action="admin-toggle-dropdown"]');
            if (adminDropdownTrigger) {
                e.preventDefault();
                const wrapper = adminDropdownTrigger.closest('.component-dropdown');
                const module = wrapper.querySelector('.component-module');
                
                // Cerrar otros dropdowns abiertos
                document.querySelectorAll('.component-dropdown .component-module:not(.disabled)').forEach(m => {
                    if (m !== module) m.classList.add('disabled');
                });
                
                if (module) module.classList.toggle('disabled');
                return;
            }

            const adminOptionSelect = e.target.closest('[data-action="admin-select-option"]');
            if (adminOptionSelect) {
                e.preventDefault();
                const wrapper = adminOptionSelect.closest('.component-dropdown');
                const module = wrapper.querySelector('.component-module');
                const textDisplay = wrapper.querySelector('.component-dropdown-text');
                textDisplay.textContent = adminOptionSelect.dataset.label;

                const iconDisplay = wrapper.querySelector('.trigger-select-icon');
                const optionIcon = adminOptionSelect.querySelector('.component-menu-link-icon span');
                if (iconDisplay && optionIcon) {
                    iconDisplay.textContent = optionIcon.textContent;
                }

                module.querySelectorAll('.component-menu-link').forEach(link => link.classList.remove('active'));
                adminOptionSelect.classList.add('active');
                module.classList.add('disabled');

                const prefKey = wrapper.dataset.prefKey;
                this.updateAdminPreference(prefKey, adminOptionSelect.dataset.value);
                return;
            }
        });

        document.body.addEventListener('change', (e) => {
            const view = document.getElementById('admin-manage-user-view');
            if (!view) return;

            if (e.target.id === 'admin-upload-avatar') {
                this.handleFileSelection(e.target);
            }

            // --- TOGGLES DE PREFERENCIAS ---
            if (e.target.id === 'admin-pref-open-links') {
                this.updateAdminPreference('open_links_new_tab', e.target.checked);
            } else if (e.target.id === 'admin-pref-extended-alerts') {
                this.updateAdminPreference('extended_alerts', e.target.checked);
            }
        });

        // --- BUSCADOR DEL DROPDOWN DE IDIOMAS ---
        document.body.addEventListener('input', (e) => {
            const filterInput = e.target.closest('[data-action="admin-filter-options"]');
            if (filterInput) {
                this.handleDropdownFilter(filterInput);
            }
        });
    }

    getTargetUuid() {
        const input = document.getElementById('admin-target-uuid');
        return input ? input.value : '';
    }

    // ==========================================
    // MÉTODOS DE FOTO DE PERFIL
    // ==========================================

    handleFileSelection(input) {
        const file = input.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            Toast.show('La imagen no puede pesar más de 2MB.', 'error');
            input.value = ''; 
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('admin-preview-avatar');
            if (preview) preview.src = e.target.result;
            this.switchAvatarControlsState('preview');
        };
        reader.readAsDataURL(file);
    }

    cancelAvatarChange() {
        const preview = document.getElementById('admin-preview-avatar');
        const input = document.getElementById('admin-upload-avatar');
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
        const input = document.getElementById('admin-upload-avatar');
        const file = input.files[0];
        const uuid = this.getTargetUuid();
        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';

        if (!file || !uuid) return;

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('target_uuid', uuid);
        formData.append('csrf_token', csrfToken);

        try {
            const res = await ApiService.postFormData(API_ROUTES.ADMIN.UPDATE_AVATAR, formData);
            if (res.success) {
                this.updateAvatarVisuals(res.avatar);
                this.switchAvatarControlsState('custom'); 
                Toast.show(res.message, 'success'); 
            } else {
                Toast.show(res.message, 'error');
                this.cancelAvatarChange(); 
            }
        } catch (error) {
            Toast.show('Error de red al actualizar avatar', 'error');
            this.cancelAvatarChange();
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
            input.value = '';
        }
    }

    async deleteAvatar(btn) {
        const uuid = this.getTargetUuid();
        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';

        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button" style="border-top-color: #d32f2f;"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.ADMIN.DELETE_AVATAR, { target_uuid: uuid, csrf_token: csrfToken });
            if (res.success) {
                this.updateAvatarVisuals(res.avatar);
                this.switchAvatarControlsState('default');
                Toast.show(res.message, 'success');
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error interno del servidor', 'error'); 
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    updateAvatarVisuals(newPath) {
        const preview = document.getElementById('admin-preview-avatar');
        let finalPath = newPath;
        if (!finalPath.startsWith('/ProjectAurora/')) {
            finalPath = '/ProjectAurora/' + finalPath.replace(/^\//, '');
        }
        if (preview) {
            preview.src = finalPath;
            preview.setAttribute('data-original-src', finalPath);
        }
    }

    switchAvatarControlsState(state) {
        document.querySelectorAll('[data-state^="admin-avatar-actions-"]').forEach(el => {
            el.classList.replace('active', 'disabled');
        });
        const target = document.querySelector(`[data-state="admin-avatar-actions-${state}"]`);
        if (target) target.classList.replace('disabled', 'active');
    }

    // ==========================================
    // MÉTODOS DE CAMPOS DE TEXTO
    // ==========================================

    toggleFieldState(target, mode) {
        const viewState = document.querySelector(`[data-state="admin-${target}-view-state"]`);
        const editState = document.querySelector(`[data-state="admin-${target}-edit-state"]`);
        const viewActions = document.querySelector(`[data-state="admin-${target}-actions-view"]`);
        const editActions = document.querySelector(`[data-state="admin-${target}-actions-edit"]`);

        if (!viewState || !editState || !viewActions || !editActions) return;

        if (mode === 'edit') {
            viewState.classList.replace('active', 'disabled');
            viewActions.classList.replace('active', 'disabled');
            editState.classList.replace('disabled', 'active');
            editActions.classList.replace('disabled', 'active');
            
            const input = document.getElementById(`admin-input-${target}`);
            if (input) {
                input.focus();
                const val = input.value;
                input.value = '';
                input.value = val;
            }
        } else {
            editState.classList.replace('active', 'disabled');
            editActions.classList.replace('active', 'disabled');
            viewState.classList.replace('disabled', 'active');
            viewActions.classList.replace('disabled', 'active');
        }
    }

    handleCancelEdit(target) {
        const originalValue = document.getElementById(`admin-display-${target}`).textContent;
        const inputEl = document.getElementById(`admin-input-${target}`);
        if (inputEl) inputEl.value = originalValue;
        this.toggleFieldState(target, 'view');
    }

    async handleSaveField(target, btnSave) {
        const inputEl = document.getElementById(`admin-input-${target}`);
        const displayEl = document.getElementById(`admin-display-${target}`);
        const uuid = this.getTargetUuid();
        
        if (!inputEl || !displayEl || !uuid) return;

        const newValue = inputEl.value.trim();
        if (newValue === "") {
            Toast.show('El campo no puede quedar vacío', 'error');
            return;
        }

        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';
        const originalText = btnSave.textContent;
        btnSave.disabled = true;
        btnSave.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.ADMIN.UPDATE_FIELD, { 
                target_uuid: uuid,
                field: target, // 'username' o 'email'
                value: newValue, 
                csrf_token: csrfToken 
            });
            
            if (res.success) {
                displayEl.textContent = res.newValue;
                inputEl.value = res.newValue; // Sincroniza
                this.toggleFieldState(target, 'view');
                Toast.show(res.message, 'success'); 
            } else { 
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error al intentar forzar el cambio en base de datos.', 'error'); 
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = originalText;
        }
    }

    // ==========================================
    // MÉTODOS DE PREFERENCIAS
    // ==========================================

    async updateAdminPreference(field, value) {
        const uuid = this.getTargetUuid();
        if (!uuid) return;

        const csrfToken = document.getElementById('csrf_token_admin') ? document.getElementById('csrf_token_admin').value : '';

        try {
            const res = await ApiService.post(API_ROUTES.ADMIN.UPDATE_PREFERENCE, {
                target_uuid: uuid,
                field: field,
                value: value,
                csrf_token: csrfToken
            });

            if (res.success) {
                Toast.show(res.message, 'success');
            } else {
                Toast.show(res.message, 'error');
            }
        } catch (error) {
            Toast.show('Error al intentar actualizar la preferencia', 'error');
        }
    }

    handleDropdownFilter(searchInput) {
        const module = searchInput.closest('.component-module');
        const term = searchInput.value.toLowerCase().trim();
        const listContainer = module.querySelector('.component-menu-list');
        
        let hasMatch = false;
        let activeElement = null;

        module.querySelectorAll('.component-menu-link').forEach(link => {
            const label = link.dataset.label.toLowerCase();
            const isActive = link.classList.contains('active');
            
            if (isActive) {
                activeElement = link;
            }

            if (label.includes(term)) {
                link.style.display = 'flex';
                hasMatch = true;
            } else {
                link.style.display = 'none';
            }
        });

        const oldMsg = listContainer.querySelector('.dropdown-no-results-container');
        if (oldMsg) oldMsg.remove();

        if (!hasMatch && term !== '') {
            if (activeElement) {
                activeElement.style.display = 'flex';
            }
            
            const termEscaped = term.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            
            const noResultsHtml = `
                <div class="dropdown-no-results-container" style="width: 100%; display: flex; flex-direction: column;">
                    <hr class="component-divider" style="margin: 4px 0; flex-shrink: 0;">
                    <div style="padding: 12px 16px; text-align: center; font-size: 14px; color: var(--text-secondary);">
                        No se encontraron resultados para "<b>${termEscaped}</b>"
                    </div>
                </div>
            `;
            listContainer.insertAdjacentHTML('beforeend', noResultsHtml);
        }
    }
}