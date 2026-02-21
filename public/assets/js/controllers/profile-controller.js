// public/assets/js/profile-controller.js
import { ApiService } from '../api/api-services.js';
import { API_ROUTES } from '../api/api-routes.js';
import { Toast } from '../components/toast-controller.js';

export class ProfileController {
    constructor() {
        this.init();
    }

    init() {
        document.body.addEventListener('click', (e) => {
            // --- MANEJO DE ELIMINAR CUENTA ---
            if (e.target.closest('[data-action="delete-account-submit"]')) {
                e.preventDefault();
                this.handleDeleteAccount(e.target.closest('button'));
                return;
            }

            // --- MANEJO DE FOTO DE PERFIL ---
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

            // --- MANEJO DE CORREO Y CAMPOS EDITABLES ---
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

            // --- MANEJO DE SEGURIDAD (CONTRASEÑA MULTI-PASO) ---
            if (e.target.closest('[data-action="pass-start-flow"]')) { 
                e.preventDefault(); 
                this.handlePassStartFlow(); 
                return; 
            }
            if (e.target.closest('[data-action="pass-cancel-flow"]')) { 
                e.preventDefault(); 
                this.handlePassCancelFlow(); 
                return; 
            }
            if (e.target.closest('[data-action="pass-go-step-2"]')) { 
                e.preventDefault(); 
                this.handlePassVerify(e.target.closest('button')); 
                return; 
            }
            if (e.target.closest('[data-action="pass-submit-final"]')) { 
                e.preventDefault(); 
                this.handlePassUpdate(e.target.closest('button')); 
                return; 
            }

            // --- MANEJO DE DROPDOWNS ---
            const triggerSelector = e.target.closest('[data-action="toggle-dropdown"]');
            if (triggerSelector) { e.preventDefault(); this.handleDropdownToggle(triggerSelector, e); return; }

            const optionSelect = e.target.closest('[data-action="select-option"]');
            if (optionSelect) { 
                e.preventDefault(); 
                this.handleOptionSelect(optionSelect); 
                
                if (window.preferencesController) {
                    const dropdown = optionSelect.closest('.component-dropdown');
                    const prefKey = dropdown ? (dropdown.dataset.prefKey || 'language') : 'language';
                    window.preferencesController.updatePreference(prefKey, optionSelect.dataset.value);
                }
                return; 
            }
        });

        document.body.addEventListener('change', (e) => {
            // --- MANEJO CHECKBOX ELIMINAR CUENTA ---
            if (e.target.id === 'confirmDeleteCheckbox') {
                const passwordArea = document.getElementById('passwordConfirmationArea');
                const passwordInput = document.getElementById('deleteAccountPassword');
                if (passwordArea) {
                    if (e.target.checked) {
                        passwordArea.classList.replace('disabled', 'active');
                        if(passwordInput) setTimeout(() => passwordInput.focus(), 50);
                    } else {
                        passwordArea.classList.replace('active', 'disabled');
                        if(passwordInput) passwordInput.value = '';
                    }
                }
                return;
            }

            if (e.target.id === 'upload-avatar') {
                this.handleFileSelection(e.target);
            }

            if (e.target.id === 'pref-open-links' || e.target.id === 'pref-open-links-guest') {
                if (window.preferencesController) {
                    window.preferencesController.updatePreference('open_links_new_tab', e.target.checked);
                }
            } else if (e.target.id === 'pref-extended-alerts') {
                if (window.preferencesController) {
                    window.preferencesController.updatePreference('extended_alerts', e.target.checked);
                }
            }
        });

        document.body.addEventListener('input', (e) => {
            const filterInput = e.target.closest('[data-action="filter-options"]');
            if (filterInput) this.handleFilter(filterInput);
        });
    }

    // ==========================================
    // MÉTODO: ELIMINAR CUENTA
    // ==========================================
    async handleDeleteAccount(btn) {
        const passwordInput = document.getElementById('deleteAccountPassword');
        const password = passwordInput ? passwordInput.value.trim() : '';

        if (!password) {
            Toast.show('Ingresa tu contraseña para continuar.', 'error');
            return;
        }

        const confirmDialog = confirm("¿Estás absolutamente seguro? Esta acción eliminará todo de inmediato y no se puede deshacer.");
        
        if (confirmDialog) {
            const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<div class="component-spinner-button"></div>';
            btn.style.opacity = '0.8';

            try {
                // Fetch a la ruta configurada en api-routes.js
                const res = await ApiService.post(API_ROUTES.SETTINGS.DELETE_ACCOUNT, {
                    csrf_token: csrfToken,
                    password: password
                });

                if (res.success) {
                    Toast.show(res.message || 'Cuenta eliminada exitosamente.', 'success');
                    // Redirigir al usuario al login tras un instante
                    setTimeout(() => {
                        window.location.href = '/ProjectAurora/login';
                    }, 1500);
                } else {
                    btn.disabled = false;
                    btn.textContent = originalText;
                    btn.style.opacity = '1';
                    Toast.show(window.t ? window.t(res.message) || res.message : res.message, 'error');
                }
            } catch (error) {
                btn.disabled = false;
                btn.textContent = originalText;
                btn.style.opacity = '1';
                Toast.show('Error de conexión.', 'error');
            }
        }
    }

    // ==========================================
    // FLUJO DE CAMBIO DE CONTRASEÑA
    // ==========================================

    handlePassStartFlow() {
        document.querySelector('[data-state="password-stage-0"]').classList.replace('active', 'disabled');
        document.querySelector('[data-state="password-stage-1"]').classList.replace('disabled', 'active');
        setTimeout(() => document.getElementById('current-password-input').focus(), 50);
    }

    handlePassCancelFlow() {
        const stage1 = document.querySelector('[data-state="password-stage-1"]');
        const stage2 = document.querySelector('[data-state="password-stage-2"]');
        
        if(stage1) stage1.classList.replace('active', 'disabled');
        if(stage2) stage2.classList.replace('active', 'disabled');
        
        document.querySelector('[data-state="password-stage-0"]').classList.replace('disabled', 'active');
        
        document.getElementById('current-password-input').value = '';
        document.getElementById('new-password-input').value = '';
        document.getElementById('repeat-password-input').value = '';
    }

    async handlePassVerify(btn) {
        const pass = document.getElementById('current-password-input').value;
        if (!pass) { Toast.show(window.t('js.profile.err_empty'), 'error'); return; }
        
        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.VERIFY_PASSWORD, { password: pass, csrf_token: csrfToken });
            if (res.success) {
                document.querySelector('[data-state="password-stage-1"]').classList.replace('active', 'disabled');
                document.querySelector('[data-state="password-stage-2"]').classList.replace('disabled', 'active');
                setTimeout(() => document.getElementById('new-password-input').focus(), 50);
            } else {
                Toast.show(window.t(res.message), 'error');
            }
        } catch (error) { 
            Toast.show(window.t('js.profile.err_net'), 'error'); 
        } finally { 
            btn.disabled = false; 
            btn.textContent = originalText; 
        }
    }

    async handlePassUpdate(btn) {
        const currentPass = document.getElementById('current-password-input').value;
        const newPass = document.getElementById('new-password-input').value;
        const repPass = document.getElementById('repeat-password-input').value;
        
        if (!newPass || !repPass) { Toast.show(window.t('js.profile.err_empty'), 'error'); return; }
        if (newPass !== repPass) { Toast.show(window.t('js.auth.err_pass_match'), 'error'); return; }
        
        const minPass = parseInt(window.APP_CONFIG?.min_password_length || 12);
        const maxPass = parseInt(window.APP_CONFIG?.max_password_length || 64);
        if (newPass.length < minPass || newPass.length > maxPass) {
            Toast.show(window.t('js.auth.err_pass_length'), 'error'); return;
        }

        const csrfToken = document.getElementById('csrf_token_settings') ? document.getElementById('csrf_token_settings').value : '';
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.innerHTML = '<div class="component-spinner-button"></div>';

        try {
            const res = await ApiService.post(API_ROUTES.SETTINGS.UPDATE_PASSWORD, { current_password: currentPass, new_password: newPass, csrf_token: csrfToken });
            if (res.success) {
                Toast.show(window.t(res.message), 'success');
                this.handlePassCancelFlow();
            } else {
                Toast.show(window.t(res.message), 'error');
            }
        } catch (error) { 
            Toast.show(window.t('js.profile.err_net'), 'error'); 
        } finally { 
            btn.disabled = false; 
            btn.textContent = originalText; 
        }
    }

    // ==========================================
    // MÉTODOS DE FOTO DE PERFIL
    // ==========================================

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

    // ==========================================
    // MÉTODOS DE CAMPOS EDITABLES
    // ==========================================

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
        const fieldMap = { 'username': 'username' }; 
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

    // ==========================================
    // MÉTODOS DE DROPDOWNS
    // ==========================================

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
        
        const iconDisplay = wrapper.querySelector('.trigger-select-icon');
        const optionIcon = option.querySelector('.component-menu-link-icon span');
        if (iconDisplay && optionIcon) {
            iconDisplay.textContent = optionIcon.textContent;
        }
        
        module.querySelectorAll('.component-menu-link').forEach(link => link.classList.remove('active'));
        option.classList.add('active');
        module.classList.add('disabled');
    }

handleFilter(searchInput) {
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

        // Remover mensajes anteriores de la búsqueda
        const oldMsg = listContainer.querySelector('.dropdown-no-results-container');
        if (oldMsg) oldMsg.remove();

        // Lógica de "Sin resultados"
        if (!hasMatch && term !== '') {
            // Forzar a mostrar siempre el elemento activo
            if (activeElement) {
                activeElement.style.display = 'flex';
            }
            
            // Sanitizar el término para evitar ataques XSS
            const termEscaped = term.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            
            // Inyectar divisor y texto indicando que no hay resultados
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

    closeAllDropdowns(exceptThisOne = null) {
        document.querySelectorAll('.component-dropdown .component-module:not(.disabled)').forEach(m => {
            if (m !== exceptThisOne) m.classList.add('disabled');
        });
    }
}