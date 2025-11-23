// public/assets/js/modules/admin-user-details.js

(function () {
    // Helper de traducción local
    function t(key) {
        return window.t ? window.t(key) : key;
    }

    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';

    class AdminUserManager {
        constructor() {
            this.context = null;
            this.targetId = null;
            this.prefix = null; 

            this.detectContext();

            if (!this.targetId) return;

            this.user = null;
            this.currentUserState = {
                isSuspended: false,
                isPermanent: false,
                reason: null
            };

            this.initGlobalListeners();
            this.loadData();
        }

        detectContext() {
            const statusId = document.getElementById('target-user-id');
            const manageId = document.getElementById('manage-target-id');
            const historyId = document.getElementById('history-target-id');
            const roleId = document.getElementById('role-target-id'); // [NUEVO]

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
            } else if (roleId) { // [NUEVO]
                this.context = 'role';
                this.targetId = roleId.value;
                this.prefix = 'role-';
            }
        }

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
                return { success: false, message: t('global.error_connection') };
            }
        }

        setLoading(btn, isLoading, originalText = '') {
            if (!btn) return;
            if (isLoading) {
                btn.dataset.original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<div class="small-spinner" style="border-color:currentColor; border-top-color:transparent;"></div>';
            } else {
                btn.innerHTML = originalText || btn.dataset.original;
                btn.disabled = false;
            }
        }

        showError(message, show = true) {
            let errorBox = null;
            
            if (this.context === 'manage') errorBox = document.getElementById('manage-error-msg');
            else if (this.context === 'role') errorBox = document.getElementById('role-error-msg'); // [NUEVO]
            else {
                const container = document.querySelector('.component-wrapper');
                errorBox = container.querySelector('.component-card__error');
                if (!errorBox && show) {
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
                alert(message);
            }
        }

        async loadData() {
            const data = await this.fetchApi({ 
                action: 'get_user_details', 
                target_id: this.targetId 
            });

            if (data.success) {
                this.user = data.user;
                this.renderHeader();

                if (this.context === 'status') this.initStatusLogic();
                if (this.context === 'manage') this.initManageLogic();
                if (this.context === 'history') this.renderHistoryTable(data.history);
                if (this.context === 'role') this.initRoleLogic(); // [NUEVO]
            } else {
                this.showError(t('global.error_connection') + ': ' + data.message);
            }
        }

        renderHeader() {
            const u = this.user;
            if (!u) return;

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

        initGlobalListeners() {
            document.body.addEventListener('click', (e) => {
                const toggleBtn = e.target.closest('[data-action="toggle-dropdown"]');
                if (toggleBtn) {
                    e.stopPropagation();
                    this.toggleDropdown(toggleBtn.dataset.target);
                    return;
                }

                const option = e.target.closest('.menu-link[data-action]');
                if (option) {
                    this.handleDropdownSelection(option);
                    return;
                }

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
                if (el.closest('.section-content')) el.classList.add('disabled');
            });
        }

        handleDropdownSelection(option) {
            const action = option.dataset.action;
            const val = option.dataset.value;
            const label = option.dataset.label || option.querySelector('.menu-link-text').textContent;
            const icon = option.dataset.icon;
            const color = option.dataset.color;

            const menuList = option.closest('.menu-list');
            if (menuList) {
                menuList.querySelectorAll('.menu-link').forEach(o => {
                    o.classList.remove('active');
                    if (o.lastElementChild) o.lastElementChild.innerHTML = '';
                });
                option.classList.add('active');
                if (option.lastElementChild) option.lastElementChild.innerHTML = '<span class="material-symbols-rounded">check</span>';
            }

            if (action === 'select-status-option') this.updateStatusUI(val, label, icon, color);
            if (action === 'select-duration-option') this.updateDurationUI(val);
            if (action === 'select-reason-option') this.updateReasonUI(val);
            if (action === 'select-manage-status') this.updateManageStatusUI(val, label, icon, color);
            if (action === 'select-deletion-type') this.updateDeletionTypeUI(val, label);
            if (action === 'select-role-option') this.updateRoleUI(val, label, icon, color); // [NUEVO]

            this.closeAllDropdowns();
        }

        // =======================================================
        // LOGICA STATUS (User Status)
        // =======================================================
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
                btnSaveText.textContent = t('admin.status.update_ban');

                let activeText = '';
                if (u.suspension_end_date === null) {
                    this.currentUserState.isPermanent = true;
                    activeText = t('admin.status.perm_ban');
                    this.updateStatusUI('suspended_perm', activeText, 'block', '#d32f2f');
                    this.setDropdownInitialActive('dropdown-status-options', 'suspended_perm');
                } else {
                    this.currentUserState.isPermanent = false;
                    const endDate = new Date(u.suspension_end_date).toLocaleDateString();
                    activeText = t('admin.status.until') + ' ' + endDate; 
                    this.updateStatusUI('suspended_temp', t('admin.status.temp_ban'), 'timer', '#f57c00');
                    this.setDropdownInitialActive('dropdown-status-options', 'suspended_temp');
                }
                
                activeAlertDesc.innerHTML = `<strong>${activeText}</strong><br>${t('admin.status.reason_label')}: ${u.suspension_reason}`;
                if (u.suspension_reason) {
                    this.updateReasonUI(u.suspension_reason);
                    this.setDropdownInitialActive('dropdown-reasons', u.suspension_reason);
                }

            } else {
                activeAlert.classList.add('d-none');
                btnLift.classList.add('d-none');
                btnSaveText.textContent = t('admin.status.apply_ban');
                
                document.getElementById('input-status-value').value = '';
                document.getElementById('current-status-text').textContent = t('admin.status.select_type');
                document.getElementById('current-status-icon').textContent = 'gavel';
                document.getElementById('current-status-icon').style.color = '#666';
                
                document.getElementById('input-duration-value').value = '';
                document.getElementById('current-duration-text').textContent = t('admin.status.select_duration');
                
                document.getElementById('wrapper-duration').classList.add('d-none');
                document.getElementById('wrapper-reason').classList.add('d-none'); 
                
                const dropdowns = ['dropdown-status-options', 'dropdown-duration'];
                dropdowns.forEach(id => {
                    const dd = document.getElementById(id);
                    if(dd) {
                        dd.querySelectorAll('.menu-link').forEach(o => {
                            o.classList.remove('active');
                            if (o.lastElementChild) o.lastElementChild.innerHTML = '';
                        });
                    }
                });
            }

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
            const wrapperReason = document.getElementById('wrapper-reason');
            const durationVal = document.getElementById('input-duration-value').value;

            if (val === 'suspended_temp') {
                wrapperDuration.classList.remove('d-none');
                if (durationVal) wrapperReason.classList.remove('d-none');
                else wrapperReason.classList.add('d-none');
            } else if (val === 'suspended_perm') {
                wrapperDuration.classList.add('d-none');
                wrapperReason.classList.remove('d-none'); 
            } else {
                wrapperDuration.classList.add('d-none');
                wrapperReason.classList.add('d-none');
            }
        }

        updateDurationUI(days) {
            document.getElementById('input-duration-value').value = days;
            document.getElementById('current-duration-text').textContent = days + ' ' + t('global.days');
            document.getElementById('wrapper-reason').classList.remove('d-none');
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

            if (!statusType) { 
                this.showError(t('admin.error.type_required') || 'Selecciona un tipo de sanción.'); 
                return; 
            }

            if (statusType === 'suspended_temp' && !duration) {
                this.showError(t('admin.error.duration_required') || 'Selecciona una duración.');
                return;
            }

            if (!reason) { this.showError(t('admin.error.reason_required')); return; }

            if (this.currentUserState.isPermanent && statusType === 'suspended_perm' && reason === this.currentUserState.reason) {
                this.showError(t('admin.status.already_suspended')); return;
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
                this.loadData(); 
            } else {
                this.showError(res.message);
            }
            
            this.setLoading(btnSave, false, `<span class="material-symbols-rounded">save</span><span id="btn-save-text">${this.currentUserState.isSuspended ? t('admin.status.update_ban') : t('admin.status.apply_ban')}</span>`);
        }

        async liftBan() {
            if (!confirm(t('global.are_you_sure') || '¿Seguro?')) return;
            
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

        // =======================================================
        // LOGICA MANAGE (User Manage - General)
        // =======================================================
        initManageLogic() {
            const u = this.user;
            const btnSave = document.getElementById('btn-save-manage');

            if (u.account_status === 'deleted') {
                this.updateManageStatusUI('deleted', t('global.deleted'), 'delete_forever', '#616161');
                this.setDropdownInitialActive('dropdown-manage-status', 'deleted');

                if (u.deletion_type) {
                    const typeText = (u.deletion_type === 'user_decision') ? t('admin.manage.user_dec') : t('admin.manage.admin_dec');
                    this.updateDeletionTypeUI(u.deletion_type, typeText);
                    this.setDropdownInitialActive('dropdown-deletion-type', u.deletion_type);
                }
                if (u.deletion_reason) document.getElementById('input-user-reason').value = u.deletion_reason;
                if (u.admin_comments) document.getElementById('input-admin-comments').value = u.admin_comments;
            } else {
                this.updateManageStatusUI('active', t('global.active'), 'check_circle', '#2e7d32');
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
                if (!adminComments) { this.showError(t('admin.manage.admin_comments_desc')); return; }
                payload.deletion_type = delType;
                payload.admin_comments = adminComments;
                if (delType === 'user_decision') {
                    if (!userReason) { this.showError(t('admin.manage.user_reason_desc')); return; }
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
            this.setLoading(btnSave, false, `<span class="material-symbols-rounded">save</span> ${t('global.save_status')}`);
        }

        // =======================================================
        // [NUEVO] LOGICA ROL (User Role)
        // =======================================================
        initRoleLogic() {
            const u = this.user;
            const btnSave = document.getElementById('btn-save-role');
            
            // Establecer estado inicial basado en el usuario cargado
            let roleLabel = 'Usuario';
            let roleIcon = 'person';
            let roleColor = '#666';

            if (u.role === 'moderator') {
                roleLabel = 'Moderador';
                roleIcon = 'security';
                roleColor = '#0000FF';
            } else if (u.role === 'administrator') {
                roleLabel = 'Administrador';
                roleIcon = 'admin_panel_settings';
                roleColor = '#d32f2f';
            } else if (u.role === 'founder') {
                roleLabel = 'Fundador';
                roleIcon = 'diamond';
                roleColor = '#FFC107';
                // Opcional: Deshabilitar cambio de rol si es Founder
            }

            this.updateRoleUI(u.role, roleLabel, roleIcon, roleColor);
            this.setDropdownInitialActive('dropdown-roles', u.role);

            btnSave.onclick = () => this.saveRoleChanges();
        }

        updateRoleUI(val, text, icon, color) {
            document.getElementById('role-input-value').value = val;
            document.getElementById('current-role-text').textContent = text;
            const iconEl = document.getElementById('current-role-icon');
            iconEl.textContent = icon;
            iconEl.style.color = color;
        }

        async saveRoleChanges() {
            const newRole = document.getElementById('role-input-value').value;
            const btnSave = document.getElementById('btn-save-role');

            this.showError('', false);
            this.setLoading(btnSave, true);

            const payload = {
                action: 'update_user_role',
                target_id: this.targetId,
                role: newRole
            };

            const res = await this.fetchApi(payload);

            if (res.success) {
                if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
                this.loadData(); // Recargar para ver cambios reflejados (header)
            } else {
                this.showError(res.message);
            }
            this.setLoading(btnSave, false, `<span class="material-symbols-rounded">save</span> ${t('global.save')}`);
        }

        // =======================================================
        // HISTORY LOGIC
        // =======================================================
        renderHistoryTable(logs) {
            const tbody = document.getElementById('full-history-body');
            if (!tbody) return;

            if (!logs || logs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="component-table-empty">
                            <span class="material-symbols-rounded component-table-empty-icon">history_toggle_off</span>
                            <p>${t('admin.history.empty')}</p>
                        </td>
                    </tr>`;
                return;
            }

            let html = '';
            logs.forEach(log => {
                const start = new Date(log.started_at).toLocaleString();
                const adminName = log.admin_name || 'Sistema';
                
                let durationDisplay = (parseInt(log.duration_days) === -1) 
                    ? `<span style="color: #d32f2f; font-weight: 600;">${t('admin.status.perm_ban')}</span>` 
                    : log.duration_days + ' ' + t('global.days');
                
                let endDisplay = (parseInt(log.duration_days) === -1) 
                    ? t('admin.history.indefinite') || 'Indefinido'
                    : (log.ends_at ? new Date(log.ends_at).toLocaleString() : '-');

                let liftedDisplay = '';
                if (log.lifted_at) {
                    const liftedDate = new Date(log.lifted_at).toLocaleString();
                    const lifter = log.lifter_name || 'Admin';
                    liftedDisplay = `
                        <div style="display:flex; flex-direction:column;">
                            <span style="color:#2e7d32; font-weight:600; font-size:13px; display:flex; align-items:center; gap:4px;">
                                <span class="material-symbols-rounded" style="font-size:16px;">check_circle</span> 
                                ${t('admin.history.lifted_on') || 'Levantada el'} ${liftedDate}
                            </span>
                            <span style="color:#888; font-size:11px; margin-left:20px;">${t('admin.history.by') || 'por'} ${lifter}</span>
                        </div>`;
                } else {
                    const now = new Date();
                    const endDate = log.ends_at ? new Date(log.ends_at) : null;
                    if (parseInt(log.duration_days) === -1 || (endDate && endDate > now)) {
                        liftedDisplay = '<span class="component-badge component-badge--danger">' + (t('global.active') || 'Activa') + '</span>';
                    } else {
                        liftedDisplay = '<span class="component-badge component-badge--neutral">' + (t('global.status.expired') || 'Expirada') + '</span>';
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

        setDropdownInitialActive(dropdownId, value) {
            const dropdown = document.getElementById(dropdownId);
            if (!dropdown) return;
            const option = dropdown.querySelector(`.menu-link[data-value="${value}"]`);
            if (option) {
                dropdown.querySelectorAll('.menu-link').forEach(o => {
                    o.classList.remove('active');
                    if (o.lastElementChild) o.lastElementChild.innerHTML = '';
                });
                option.classList.add('active');
                if (option.lastElementChild) option.lastElementChild.innerHTML = '<span class="material-symbols-rounded">check</span>';
            }
        }
    }

    new AdminUserManager();

})();