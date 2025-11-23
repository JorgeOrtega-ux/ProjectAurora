// public/assets/js/modules/admin-user-details.js

/**
 * AdminUserDetails
 * Módulo unificado para la gestión de detalles de usuario en el panel de administración.
 * Maneja: Sanciones (Status), Gestión General (Manage) e Historial (History).
 */

(function () {
    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';

    class AdminUserManager {
        constructor() {
            // 1. Detección de Contexto y ID del Objetivo
            this.context = null; // 'status', 'manage', 'history'
            this.targetId = null;
            this.prefix = null;  // Prefijo de IDs del DOM (ej: 'status-', 'manage-')

            this.detectContext();

            if (!this.targetId) return; // Si no hay ID, no hacemos nada

            // 2. Estado Global
            this.user = null;
            this.currentUserState = {
                isSuspended: false,
                isPermanent: false,
                reason: null
            };

            // 3. Inicialización
            this.initGlobalListeners();
            this.loadData();
        }

        /**
         * Detecta en qué página estamos buscando los inputs ocultos de ID específicos
         */
        detectContext() {
            const statusId = document.getElementById('target-user-id');
            const manageId = document.getElementById('manage-target-id');
            const historyId = document.getElementById('history-target-id');

            if (statusId) {
                this.context = 'status';
                this.targetId = statusId.value;
                this.prefix = 'status-';
            } else if (manageId) {
                this.context = 'manage';
                this.targetId = manageId.value;
                this.prefix = 'manage-';
            } else if (historyId) {
                this.context = 'history';
                this.targetId = historyId.value;
                this.prefix = 'history-';
            }
        }

        // ============================================================
        // UTILIDADES DRY (API, CSRF, UI)
        // ============================================================

        getCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        }

        async fetchApi(payload) {
            try {
                const res = await fetch(API_ADMIN, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.getCsrf() },
                    body: JSON.stringify(payload)
                });
                return await res.json();
            } catch (e) {
                console.error("API Error:", e);
                return { success: false, message: 'Error de conexión con el servidor.' };
            }
        }

        setLoading(btn, isLoading, originalText = '') {
            if (!btn) return;
            if (isLoading) {
                btn.dataset.original = btn.innerHTML; // Guardar HTML completo (iconos incluidos)
                btn.disabled = true;
                btn.innerHTML = '<div class="small-spinner" style="border-color:currentColor; border-top-color:transparent;"></div>';
            } else {
                btn.innerHTML = originalText || btn.dataset.original;
                btn.disabled = false;
            }
        }

        showError(message, show = true) {
            // Intenta encontrar el contenedor de error específico del contexto
            let errorBox = null;
            
            if (this.context === 'manage') errorBox = document.getElementById('manage-error-msg');
            else {
                // Fallback genérico para Status (busca .component-card__error dinámicamente)
                const container = document.querySelector('.component-wrapper');
                errorBox = container.querySelector('.component-card__error');
                if (!errorBox && show) {
                    // Si no existe, lo creamos (Lógica de admin-status-manager)
                    const card = document.querySelector('.component-card--grouped');
                    if (card) {
                        errorBox = document.createElement('div');
                        errorBox.className = 'component-card__error';
                        errorBox.style.marginTop = '16px';
                        card.after(errorBox);
                    }
                }
            }

            if (errorBox) {
                if (show) {
                    errorBox.textContent = message;
                    errorBox.classList.add('active');
                } else {
                    errorBox.classList.remove('active');
                }
            } else if (show) {
                alert(message); // Fallback final
            }
        }

        // ============================================================
        // CARGA DE DATOS
        // ============================================================

        async loadData() {
            const data = await this.fetchApi({ 
                action: 'get_user_details', 
                target_id: this.targetId 
            });

            if (data.success) {
                this.user = data.user;
                this.renderHeader();

                // Enrutamiento de lógica según contexto
                if (this.context === 'status') this.initStatusLogic();
                if (this.context === 'manage') this.initManageLogic();
                if (this.context === 'history') this.renderHistoryTable(data.history);
            } else {
                this.showError('Error cargando datos del usuario: ' + data.message);
            }
        }

        renderHeader() {
            const u = this.user;
            if (!u) return;

            // Elementos comunes con prefijo dinámico
            const elUsername = document.getElementById(`${this.prefix}username`);
            const elEmail = document.getElementById(`${this.prefix}email`);
            const elAvatarContainer = document.getElementById(`${this.prefix}avatar-container`);
            const elAvatarImg = document.getElementById(`${this.prefix}user-avatar`);
            const elAvatarIcon = document.getElementById(`${this.prefix}user-icon`);

            if (elUsername) elUsername.textContent = u.username;
            if (elEmail) elEmail.textContent = u.email;
            if (elAvatarContainer) elAvatarContainer.dataset.role = u.role;

            if (u.avatar && elAvatarImg) {
                elAvatarImg.src = (window.BASE_PATH || '/ProjectAurora/') + u.avatar;
                elAvatarImg.style.display = 'block';
                if (elAvatarIcon) elAvatarIcon.style.display = 'none';
            }
        }

        // ============================================================
        // EVENTOS GLOBALES (Dropdowns)
        // ============================================================

        initGlobalListeners() {
            document.body.addEventListener('click', (e) => {
                
                // 1. Toggle Dropdowns
                const toggleBtn = e.target.closest('[data-action="toggle-dropdown"]');
                if (toggleBtn) {
                    e.stopPropagation();
                    this.toggleDropdown(toggleBtn.dataset.target);
                    return;
                }

                // 2. Selección en Dropdowns (Delegación genérica)
                const option = e.target.closest('.menu-link[data-action]');
                if (option) {
                    this.handleDropdownSelection(option);
                    return;
                }

                // 3. Cerrar al hacer clic fuera
                if (!e.target.closest('.trigger-select-wrapper')) {
                    this.closeAllDropdowns();
                }
            });
        }

        toggleDropdown(targetId) {
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                const isCurrentlyOpen = !targetEl.classList.contains('disabled');
                this.closeAllDropdowns();
                if (!isCurrentlyOpen) targetEl.classList.remove('disabled');
            }
        }

        closeAllDropdowns() {
            document.querySelectorAll('.popover-module:not(.disabled)').forEach(el => {
                // Solo cerrar los dropdowns de admin (evitar cerrar notificaciones/perfil si se usaran clases similares)
                if (el.closest('.section-content')) el.classList.add('disabled');
            });
        }

        handleDropdownSelection(option) {
            const action = option.dataset.action;
            const val = option.dataset.value;
            const label = option.dataset.label || option.querySelector('.menu-link-text').textContent;
            const icon = option.dataset.icon;
            const color = option.dataset.color;

            // Actualizar UI visual del dropdown (Checkmark)
            const menuList = option.closest('.menu-list');
            if (menuList) {
                menuList.querySelectorAll('.menu-link').forEach(o => {
                    o.classList.remove('active');
                    if (o.lastElementChild) o.lastElementChild.innerHTML = '';
                });
                option.classList.add('active');
                if (option.lastElementChild) option.lastElementChild.innerHTML = '<span class="material-symbols-rounded">check</span>';
            }

            // Lógica específica por acción
            if (action === 'select-status-option') this.updateStatusUI(val, label, icon, color);
            if (action === 'select-duration-option') this.updateDurationUI(val);
            if (action === 'select-reason-option') this.updateReasonUI(val);
            if (action === 'select-manage-status') this.updateManageStatusUI(val, label, icon, color);
            if (action === 'select-deletion-type') this.updateDeletionTypeUI(val, label);

            this.closeAllDropdowns();
        }

        // ============================================================
        // LÓGICA DE ESTADO (SANCIONES)
        // ============================================================

        initStatusLogic() {
            const u = this.user;
            const activeAlert = document.getElementById('active-sanction-alert');
            const activeAlertDesc = document.getElementById('active-sanction-desc');
            const btnLift = document.getElementById('btn-lift-ban');
            const btnSaveText = document.getElementById('btn-save-text');
            const btnSave = document.getElementById('btn-save-status');

            if (u.account_status === 'suspended') {
                this.currentUserState.isSuspended = true;
                this.currentUserState.reason = u.suspension_reason;
                
                activeAlert.classList.remove('d-none');
                btnLift.classList.remove('d-none');
                btnSaveText.textContent = 'Actualizar Sanción';

                let activeText = '';
                if (u.suspension_end_date === null) {
                    this.currentUserState.isPermanent = true;
                    activeText = 'Permanente';
                    this.updateStatusUI('suspended_perm', 'Suspensión Permanente', 'block', '#d32f2f');
                    this.setDropdownInitialActive('dropdown-status-options', 'suspended_perm');
                } else {
                    this.currentUserState.isPermanent = false;
                    const endDate = new Date(u.suspension_end_date).toLocaleDateString();
                    activeText = `Hasta el ${endDate}`;
                    this.updateStatusUI('suspended_temp', 'Suspensión Temporal', 'timer', '#f57c00');
                    this.setDropdownInitialActive('dropdown-status-options', 'suspended_temp');
                }
                
                activeAlertDesc.innerHTML = `<strong>${activeText}</strong><br>Motivo: ${u.suspension_reason}`;
                if (u.suspension_reason) {
                    this.updateReasonUI(u.suspension_reason);
                    this.setDropdownInitialActive('dropdown-reasons', u.suspension_reason);
                }

            } else {
                activeAlert.classList.add('d-none');
                btnLift.classList.add('d-none');
                btnSaveText.textContent = 'Aplicar Sanción';
                this.updateStatusUI('suspended_temp', 'Suspensión Temporal', 'timer', '#f57c00');
                this.setDropdownInitialActive('dropdown-status-options', 'suspended_temp');
            }

            // Listeners Botones
            btnSave.onclick = () => this.saveStatusSanction();
            btnLift.onclick = () => this.liftBan();
        }

        updateStatusUI(val, text, icon, color) {
            document.getElementById('input-status-value').value = val;
            document.getElementById('current-status-text').textContent = text;
            const iconEl = document.getElementById('current-status-icon');
            iconEl.textContent = icon;
            iconEl.style.color = color;

            const wrapperDuration = document.getElementById('wrapper-duration');
            if (val === 'suspended_temp') wrapperDuration.classList.remove('d-none');
            else wrapperDuration.classList.add('d-none');
        }

        updateDurationUI(days) {
            document.getElementById('input-duration-value').value = days;
            document.getElementById('current-duration-text').textContent = days + ' Días';
        }

        updateReasonUI(reason) {
            document.getElementById('input-reason-value').value = reason;
            document.getElementById('current-reason-text').textContent = reason;
        }

        async saveStatusSanction() {
            const statusType = document.getElementById('input-status-value').value;
            const reason = document.getElementById('input-reason-value').value;
            const duration = document.getElementById('input-duration-value').value;
            const btnSave = document.getElementById('btn-save-status');

            this.showError('', false);

            if (!reason) { this.showError('Debes seleccionar una razón para la sanción.'); return; }

            if (this.currentUserState.isPermanent && statusType === 'suspended_perm' && reason === this.currentUserState.reason) {
                this.showError('El usuario ya tiene esta sanción activa.'); return;
            }

            this.setLoading(btnSave, true);

            const payload = {
                action: 'update_user_status',
                target_id: this.targetId,
                status: 'suspended',
                reason: reason,
                duration_days: (statusType === 'suspended_perm') ? 'permanent' : parseInt(duration)
            };

            const res = await this.fetchApi(payload);
            
            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
                this.loadData(); // Recargar para actualizar estado
            } else {
                this.showError(res.message);
            }
            
            this.setLoading(btnSave, false, `<span class="material-symbols-rounded">save</span><span id="btn-save-text">${this.currentUserState.isSuspended ? 'Actualizar Sanción' : 'Aplicar Sanción'}</span>`);
        }

        async liftBan() {
            if (!confirm('¿Levantar sanción y reactivar usuario?')) return;
            
            const btnLift = document.getElementById('btn-lift-ban');
            const originalHtml = btnLift.innerHTML;
            this.setLoading(btnLift, true);

            const res = await this.fetchApi({
                action: 'update_user_status',
                target_id: this.targetId,
                status: 'active'
            });

            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
                this.loadData();
            } else {
                this.showError(res.message);
            }
            this.setLoading(btnLift, false, originalHtml);
        }

        // ============================================================
        // LÓGICA DE GESTIÓN GENERAL (MANAGE)
        // ============================================================

        initManageLogic() {
            const u = this.user;
            const btnSave = document.getElementById('btn-save-manage');

            if (u.account_status === 'deleted') {
                this.updateManageStatusUI('deleted', 'Cuenta Eliminada', 'delete_forever', '#616161');
                this.setDropdownInitialActive('dropdown-manage-status', 'deleted');

                if (u.deletion_type) {
                    const typeText = (u.deletion_type === 'user_decision') ? 'Decisión del Usuario' : 'Decisión Administrativa';
                    this.updateDeletionTypeUI(u.deletion_type, typeText);
                    this.setDropdownInitialActive('dropdown-deletion-type', u.deletion_type);
                }
                if (u.deletion_reason) document.getElementById('input-user-reason').value = u.deletion_reason;
                if (u.admin_comments) document.getElementById('input-admin-comments').value = u.admin_comments;
            } else {
                this.updateManageStatusUI('active', 'Activo', 'check_circle', '#2e7d32');
                this.setDropdownInitialActive('dropdown-manage-status', 'active');
            }

            btnSave.onclick = () => this.saveManageChanges();
        }

        updateManageStatusUI(val, text, icon, color) {
            document.getElementById('manage-status-value').value = val;
            document.getElementById('manage-status-text').textContent = text;
            const iconEl = document.getElementById('manage-status-icon');
            iconEl.textContent = icon;
            iconEl.style.color = color;

            const wrapper = document.getElementById('wrapper-deletion-details');
            if (val === 'deleted') wrapper.classList.remove('d-none');
            else wrapper.classList.add('d-none');
        }

        updateDeletionTypeUI(val, text) {
            document.getElementById('manage-deletion-type').value = val;
            document.getElementById('text-deletion-type').textContent = text;

            const wrapper = document.getElementById('wrapper-user-reason');
            if (val === 'user_decision') wrapper.classList.remove('d-none');
            else wrapper.classList.add('d-none');
        }

        async saveManageChanges() {
            const status = document.getElementById('manage-status-value').value;
            const delType = document.getElementById('manage-deletion-type').value;
            const userReason = document.getElementById('input-user-reason').value.trim();
            const adminComments = document.getElementById('input-admin-comments').value.trim();
            const btnSave = document.getElementById('btn-save-manage');

            this.showError('', false);

            const payload = {
                action: 'update_user_general',
                target_id: this.targetId,
                status: status
            };

            if (status === 'deleted') {
                if (!adminComments) { this.showError('Comentarios administrativos requeridos.'); return; }
                payload.deletion_type = delType;
                payload.admin_comments = adminComments;
                if (delType === 'user_decision') {
                    if (!userReason) { this.showError('Razón del usuario requerida.'); return; }
                    payload.deletion_reason = userReason;
                }
            }

            this.setLoading(btnSave, true);
            const res = await this.fetchApi(payload);

            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
                this.loadData();
            } else {
                this.showError(res.message);
            }
            this.setLoading(btnSave, false, `<span class="material-symbols-rounded">save</span> Guardar Estado`);
        }

        // ============================================================
        // LÓGICA DE HISTORIAL (HISTORY)
        // ============================================================

        renderHistoryTable(logs) {
            const tbody = document.getElementById('full-history-body');
            if (!tbody) return;

            if (!logs || logs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="component-table-empty">
                            <span class="material-symbols-rounded component-table-empty-icon">history_toggle_off</span>
                            <p>Este usuario no tiene registros de sanciones previas.</p>
                        </td>
                    </tr>`;
                return;
            }

            let html = '';
            logs.forEach(log => {
                const start = new Date(log.started_at).toLocaleString();
                const adminName = log.admin_name || 'Sistema';
                
                let durationDisplay = (parseInt(log.duration_days) === -1) 
                    ? '<span style="color: #d32f2f; font-weight: 600;">Permanente</span>' 
                    : log.duration_days + ' días';
                
                let endDisplay = (parseInt(log.duration_days) === -1) 
                    ? 'Indefinido' 
                    : (log.ends_at ? new Date(log.ends_at).toLocaleString() : '-');

                let liftedDisplay = '';
                if (log.lifted_at) {
                    const liftedDate = new Date(log.lifted_at).toLocaleString();
                    const lifter = log.lifter_name || 'Admin';
                    liftedDisplay = `
                        <div style="display:flex; flex-direction:column;">
                            <span style="color:#2e7d32; font-weight:600; font-size:13px; display:flex; align-items:center; gap:4px;">
                                <span class="material-symbols-rounded" style="font-size:16px;">check_circle</span> 
                                Levantada el ${liftedDate}
                            </span>
                            <span style="color:#888; font-size:11px; margin-left:20px;">por ${lifter}</span>
                        </div>`;
                } else {
                    const now = new Date();
                    const endDate = log.ends_at ? new Date(log.ends_at) : null;
                    if (parseInt(log.duration_days) === -1 || (endDate && endDate > now)) {
                        liftedDisplay = '<span class="component-badge component-badge--danger">Activa</span>';
                    } else {
                        liftedDisplay = '<span class="component-badge component-badge--neutral">Expirada</span>';
                    }
                }

                html += `
                    <tr class="component-table-row">
                        <td>${start}</td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-rounded" style="color:#666; font-size:18px;">gavel</span>
                                ${log.reason}
                            </div>
                        </td>
                        <td>${durationDisplay}</td>
                        <td>${endDisplay}</td>
                        <td>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span class="material-symbols-rounded" style="font-size:16px; color:#666;">security</span>
                                <span style="font-weight:500;">${adminName}</span>
                            </div>
                        </td>
                        <td>${liftedDisplay}</td>
                    </tr>`;
            });
            tbody.innerHTML = html;
        }

        // --- Helper para setear estado visual inicial en dropdowns ---
        setDropdownInitialActive(dropdownId, value) {
            const dropdown = document.getElementById(dropdownId);
            if (!dropdown) return;
            const option = dropdown.querySelector(`.menu-link[data-value="${value}"]`);
            if (option) {
                // Simular click o setear clases manualmente
                dropdown.querySelectorAll('.menu-link').forEach(o => {
                    o.classList.remove('active');
                    if (o.lastElementChild) o.lastElementChild.innerHTML = '';
                });
                option.classList.add('active');
                if (option.lastElementChild) option.lastElementChild.innerHTML = '<span class="material-symbols-rounded">check</span>';
            }
        }
    }

    // Instanciar al cargar
    new AdminUserManager();

})();