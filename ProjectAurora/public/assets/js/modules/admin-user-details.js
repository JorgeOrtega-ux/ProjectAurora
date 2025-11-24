// public/assets/js/modules/admin-user-details.js

import { t } from '../core/i18n-manager.js';

const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';

// Estado del módulo
let currentContext = null;
let targetId = null;
let prefix = null;
let userData = null;
let currentUserState = {
    isSuspended: false,
    isPermanent: false,
    reason: null
};
let isInitialized = false;

/**
 * Inicializa la lógica para las páginas de detalles de usuario (Status, Manage, History, Role, Notification).
 */
export function initAdminUserDetails() {
    detectContext();

    if (!targetId) return;

    if (!isInitialized) {
        initGlobalListeners();
        isInitialized = true;
    }

    // Resetear estado local al cargar nuevo usuario
    currentUserState = { isSuspended: false, isPermanent: false, reason: null };
    loadData();
}

function detectContext() {
    const statusId = document.getElementById('target-user-id');
    const manageId = document.getElementById('manage-target-id');
    const historyId = document.getElementById('history-target-id');
    const roleId = document.getElementById('role-target-id');
    const notifId = document.getElementById('notification-target-id'); // [NUEVO]

    if (statusId) {
        currentContext = 'status';
        targetId = statusId.value;
        prefix = 'status-';
    } else if (manageId) {
        currentContext = 'manage';
        targetId = manageId.value;
        prefix = 'manage-';
    } else if (historyId) {
        currentContext = 'history';
        targetId = historyId.value;
        prefix = 'history-';
    } else if (roleId) {
        currentContext = 'role';
        targetId = roleId.value;
        prefix = 'role-';
    } else if (notifId) {
        currentContext = 'notification';
        targetId = notifId.value;
        prefix = 'notification-';
    } else {
        currentContext = null;
        targetId = null;
    }
}

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

async function fetchApi(payload) {
    try {
        const res = await fetch(API_ADMIN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify(payload)
        });
        return await res.json();
    } catch (e) {
        console.error("API Error:", e);
        return { success: false, message: t('global.error_connection') };
    }
}

function setLoading(btn, isLoading, originalText = '') {
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

function updateCardError(element, message = '', show = true) {
    if (!element) return;
    const cardContainer = element.closest('.component-card') || element.closest('.component-wrapper');
    if (!cardContainer) return;

    let nextElement = cardContainer.nextElementSibling;
    let errorDiv = null;

    if (nextElement && nextElement.classList.contains('component-card__error')) {
        errorDiv = nextElement;
    }

    if (!errorDiv && show) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'component-card__error';
        errorDiv.style.marginTop = '16px'; 
        cardContainer.after(errorDiv);
    }

    if (show && errorDiv) {
        errorDiv.textContent = message;
        requestAnimationFrame(() => errorDiv.classList.add('active'));
    } else if (!show && errorDiv) {
        errorDiv.classList.remove('active');
        setTimeout(() => {
            if (errorDiv.parentNode) errorDiv.parentNode.removeChild(errorDiv);
        }, 200);
    }
}

function showError(message, show = true) {
    let anchorElement = null;

    if (currentContext === 'manage') {
        anchorElement = document.querySelector('#dropdown-manage-status') || document.getElementById('manage-target-id');
    } else if (currentContext === 'role') {
        anchorElement = document.querySelector('#dropdown-roles') || document.getElementById('role-target-id');
    } else if (currentContext === 'status') {
        anchorElement = document.querySelector('#dropdown-status-options') || document.getElementById('target-user-id');
    } else if (currentContext === 'notification') {
        anchorElement = document.getElementById('btn-send-notification');
    }

    if (anchorElement) {
        const card = anchorElement.closest('.component-card');
        if (card) anchorElement = card;
    }

    if (!anchorElement) {
        anchorElement = document.querySelector('.component-wrapper .component-card:last-of-type');
    }

    if (anchorElement) {
        updateCardError(anchorElement, message, show);
    } else {
        if (show && message) alert(message);
    }
}

async function loadData() {
    const data = await fetchApi({ 
        action: 'get_user_details', 
        target_id: targetId 
    });

    if (data.success) {
        userData = data.user;
        renderHeader();

        if (currentContext === 'status') initStatusLogic();
        if (currentContext === 'manage') initManageLogic();
        if (currentContext === 'history') renderHistoryTable(data.history);
        if (currentContext === 'role') initRoleLogic();
        if (currentContext === 'notification') initNotificationLogic(); // [NUEVO]
    } else {
        showError(t('global.error_connection') + ': ' + data.message);
    }
}

function renderHeader() {
    if (!userData) return;

    const elUsername = document.getElementById(`${prefix}username`);
    const elEmail = document.getElementById(`${prefix}email`);
    const elAvatarContainer = document.getElementById(`${prefix}pfp-container`); // Corrección de ID base
    const elAvatarImg = document.getElementById(`${prefix}user-avatar`);
    const elAvatarIcon = document.getElementById(`${prefix}user-icon`);

    if (elUsername) elUsername.textContent = userData.username;
    if (elEmail) elEmail.textContent = userData.email;
    if (elAvatarContainer) elAvatarContainer.dataset.role = userData.role;

    if (userData.profile_picture && elAvatarImg) {
        elAvatarImg.src = (window.BASE_PATH || '/ProjectAurora/') + userData.profile_picture;
        elAvatarImg.style.display = 'block';
        if (elAvatarIcon) elAvatarIcon.style.display = 'none';
    }
}

function initGlobalListeners() {
    document.body.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('[data-action="toggle-dropdown"]');
        if (toggleBtn) {
            e.stopPropagation();
            toggleDropdown(toggleBtn.dataset.target);
            return;
        }

        const option = e.target.closest('.menu-link[data-action]');
        if (option) {
            handleDropdownSelection(option);
            return;
        }

        if (!e.target.closest('.trigger-select-wrapper')) {
            closeAllDropdowns();
        }
    });
}

function toggleDropdown(targetId) {
    const targetEl = document.getElementById(targetId);
    if (targetEl) {
        const isCurrentlyOpen = !targetEl.classList.contains('disabled');
        closeAllDropdowns();
        if (!isCurrentlyOpen) targetEl.classList.remove('disabled');
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('.popover-module:not(.disabled)').forEach(el => {
        if (el.closest('.section-content')) el.classList.add('disabled');
    });
}

function handleDropdownSelection(option) {
    const action = option.dataset.action;
    const val = option.dataset.value;
    const label = option.dataset.label || option.querySelector('.menu-link-text').textContent;
    const icon = option.dataset.icon;

    // Gestión visual de selección
    const menuList = option.closest('.menu-list');
    if (menuList) {
        menuList.querySelectorAll('.menu-link').forEach(o => {
            o.classList.remove('active');
            const lastIcon = o.lastElementChild;
            if (lastIcon && lastIcon.classList.contains('menu-link-icon')) lastIcon.innerHTML = '';
        });
        option.classList.add('active');
        const lastIcon = option.lastElementChild;
        if (lastIcon && lastIcon.classList.contains('menu-link-icon')) {
            lastIcon.innerHTML = '<span class="material-symbols-rounded">check</span>';
        }
    }

    if (action === 'select-status-option') updateStatusUI(val, label, icon);
    if (action === 'select-duration-option') updateDurationUI(val);
    if (action === 'select-reason-option') updateReasonUI(val);
    if (action === 'select-manage-status') updateManageStatusUI(val, label, icon);
    if (action === 'select-deletion-type') updateDeletionTypeUI(val, label);
    if (action === 'select-role-option') updateRoleUI(val, label, icon);
    
    // [NUEVO] Selección de plantilla de notificación
    if (action === 'select-notif-template') {
        const level = option.dataset.level;
        const message = option.dataset.message;
        const displayText = option.querySelector('.menu-link-text').textContent;
        updateNotifTemplateUI(level, message, displayText);
    }

    closeAllDropdowns();
}

// --- STATUS LOGIC ---
function initStatusLogic() {
    const u = userData;
    const activeAlert = document.getElementById('active-sanction-alert');
    const activeAlertDesc = document.getElementById('active-sanction-desc');
    const btnLift = document.getElementById('btn-lift-ban');
    const btnSave = document.getElementById('btn-save-status');

    if (u.account_status === 'suspended') {
        currentUserState.isSuspended = true;
        currentUserState.reason = u.suspension_reason;
        
        activeAlert.classList.remove('d-none');
        btnLift.classList.remove('d-none');
        
        btnSave.setAttribute('data-i18n-tooltip', 'admin.status.update_ban');
        btnSave.setAttribute('data-tooltip', t('admin.status.update_ban'));

        let activeText = '';
        if (u.suspension_end_date === null) {
            currentUserState.isPermanent = true;
            activeText = t('admin.status.perm_ban');
            updateStatusUI('suspended_perm', activeText, 'block');
            setDropdownInitialActive('dropdown-status-options', 'suspended_perm');
        } else {
            currentUserState.isPermanent = false;
            const endDate = new Date(u.suspension_end_date).toLocaleDateString();
            activeText = t('admin.status.until') + ' ' + endDate; 
            updateStatusUI('suspended_temp', t('admin.status.temp_ban'), 'timer');
            setDropdownInitialActive('dropdown-status-options', 'suspended_temp');
        }
        
        activeAlertDesc.innerHTML = `<strong>${activeText}</strong><br>${t('admin.status.reason_label')}: ${u.suspension_reason}`;
        if (u.suspension_reason) {
            updateReasonUI(u.suspension_reason);
            setDropdownInitialActive('dropdown-reasons', u.suspension_reason);
        }

    } else {
        activeAlert.classList.add('d-none');
        btnLift.classList.add('d-none');
        
        btnSave.setAttribute('data-i18n-tooltip', 'admin.status.apply_ban');
        btnSave.setAttribute('data-tooltip', t('admin.status.apply_ban'));
        
        document.getElementById('input-status-value').value = '';
        document.getElementById('current-status-text').textContent = t('admin.status.select_type');
        document.getElementById('current-status-icon').textContent = 'gavel';
        document.getElementById('current-status-icon').style.color = '';
        
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

    btnSave.onclick = () => saveStatusSanction();
    btnLift.onclick = () => liftBan();
}

function updateStatusUI(val, text, icon) {
    document.getElementById('input-status-value').value = val;
    document.getElementById('current-status-text').textContent = text;
    const iconEl = document.getElementById('current-status-icon');
    iconEl.textContent = icon;
    iconEl.style.color = ''; 

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

function updateDurationUI(days) {
    document.getElementById('input-duration-value').value = days;
    document.getElementById('current-duration-text').textContent = days + ' ' + t('global.days');
    document.getElementById('wrapper-reason').classList.remove('d-none');
}

function updateReasonUI(reason) {
    document.getElementById('input-reason-value').value = reason;
    document.getElementById('current-reason-text').textContent = reason;
}

async function saveStatusSanction() {
    const statusType = document.getElementById('input-status-value').value;
    const reason = document.getElementById('input-reason-value').value;
    const duration = document.getElementById('input-duration-value').value;
    const btnSave = document.getElementById('btn-save-status');

    showError('', false);

    if (!statusType) { 
        showError(t('admin.error.type_required') || 'Selecciona un tipo de sanción.'); 
        return; 
    }

    if (statusType === 'suspended_temp' && !duration) {
        showError(t('admin.error.duration_required') || 'Selecciona una duración.');
        return;
    }

    if (!reason) { showError(t('admin.error.reason_required')); return; }

    if (currentUserState.isPermanent && statusType === 'suspended_perm' && reason === currentUserState.reason) {
        showError(t('admin.status.already_suspended')); return;
    }

    setLoading(btnSave, true);

    const payload = {
        action: 'update_user_status',
        target_id: targetId,
        status: 'suspended',
        reason: reason,
        duration_days: (statusType === 'suspended_perm') ? 'permanent' : parseInt(duration)
    };

    const res = await fetchApi(payload);
    
    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
        loadData(); 
    } else {
        showError(res.message);
    }
    
    setLoading(btnSave, false);
}

async function liftBan() {
    if (!confirm(t('global.are_you_sure') || '¿Seguro?')) return;
    
    const btnLift = document.getElementById('btn-lift-ban');
    setLoading(btnLift, true);

    const res = await fetchApi({
        action: 'update_user_status',
        target_id: targetId,
        status: 'active'
    });

    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
        loadData();
    } else {
        showError(res.message);
    }
    setLoading(btnLift, false);
}

// --- MANAGE LOGIC ---
function initManageLogic() {
    const u = userData;
    const btnSave = document.getElementById('btn-save-manage');

    if (u.account_status === 'deleted') {
        updateManageStatusUI('deleted', t('global.deleted'), 'delete_forever');
        setDropdownInitialActive('dropdown-manage-status', 'deleted');

        if (u.deletion_type) {
            const typeText = (u.deletion_type === 'user_decision') ? t('admin.manage.user_dec') : t('admin.manage.admin_dec');
            updateDeletionTypeUI(u.deletion_type, typeText);
            setDropdownInitialActive('dropdown-deletion-type', u.deletion_type);
        }
        if (u.deletion_reason) document.getElementById('input-user-reason').value = u.deletion_reason;
        if (u.admin_comments) document.getElementById('input-admin-comments').value = u.admin_comments;
    } else {
        updateManageStatusUI('active', t('global.active'), 'check_circle');
        setDropdownInitialActive('dropdown-manage-status', 'active');
    }

    btnSave.onclick = () => saveManageChanges();
}

// --- NOTIFICATION LOGIC (NUEVO) ---
function initNotificationLogic() {
    // Resetear UI por si acaso
    updateNotifTemplateUI('info', '', 'Seleccionar plantilla...');
    
    const btnSendNotif = document.getElementById('btn-send-notification');
    if (btnSendNotif) {
        btnSendNotif.onclick = () => sendCustomNotification();
    }
}

function updateNotifTemplateUI(level, message, displayText) {
    document.getElementById('notif-template-text').textContent = displayText;
    
    const messageInput = document.getElementById('input-notif-message');
    const levelInput = document.getElementById('notif-level-value');
    
    if (messageInput) messageInput.value = message;
    if (levelInput) levelInput.value = level;

    const badge = document.getElementById('notif-level-indicator');
    const badgeLabel = document.getElementById('notif-level-label');
    
    if (!badge || !badgeLabel) return;

    badge.className = 'component-badge';
    
    if (level === 'urgent') {
        badge.classList.add('component-badge--danger');
        badgeLabel.textContent = 'Urgente / Crítico';
        badge.style.backgroundColor = ''; 
        badge.style.color = '';
        badge.style.border = '';
    } else if (level === 'warning') {
        badge.style.backgroundColor = '#fff3e0';
        badge.style.color = '#e65100';
        badge.style.border = '1px solid #ffe0b2';
        badgeLabel.textContent = 'Advertencia';
    } else {
        badge.classList.add('component-badge--neutral');
        badge.style.backgroundColor = ''; 
        badge.style.color = '';
        badge.style.border = '';
        badgeLabel.textContent = 'Informativa';
    }
}

async function sendCustomNotification() {
    const level = document.getElementById('notif-level-value').value;
    const message = document.getElementById('input-notif-message').value.trim();
    const btnSend = document.getElementById('btn-send-notification');

    showError('', false); 

    if (!message) {
        showError('El mensaje no puede estar vacío.');
        return;
    }

    setLoading(btnSend, true);

    const payload = {
        action: 'send_admin_notification',
        target_id: targetId,
        level: level,
        message: message
    };

    const res = await fetchApi(payload);

    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert('Notificación enviada correctamente', 'success');
        document.getElementById('input-notif-message').value = ''; 
        updateNotifTemplateUI('info', '', 'Seleccionar plantilla...');
    } else {
        showError(res.message);
    }
    setLoading(btnSend, false);
}

function updateManageStatusUI(val, text, icon) {
    document.getElementById('manage-status-value').value = val;
    document.getElementById('manage-status-text').textContent = text;
    const iconEl = document.getElementById('manage-status-icon');
    iconEl.textContent = icon;
    iconEl.style.color = ''; 

    const wrapper = document.getElementById('wrapper-deletion-details');
    if (val === 'deleted') wrapper.classList.remove('d-none');
    else wrapper.classList.add('d-none');
}

function updateDeletionTypeUI(val, text) {
    document.getElementById('manage-deletion-type').value = val;
    document.getElementById('text-deletion-type').textContent = text;

    const wrapper = document.getElementById('wrapper-user-reason');
    if (val === 'user_decision') wrapper.classList.remove('d-none');
    else wrapper.classList.add('d-none');
}

async function saveManageChanges() {
    const status = document.getElementById('manage-status-value').value;
    const delType = document.getElementById('manage-deletion-type').value;
    const userReason = document.getElementById('input-user-reason').value.trim();
    const adminComments = document.getElementById('input-admin-comments').value.trim();
    const btnSave = document.getElementById('btn-save-manage');

    showError('', false);

    const payload = {
        action: 'update_user_general',
        target_id: targetId,
        status: status
    };

    if (status === 'deleted') {
        if (!adminComments) { showError(t('admin.manage.admin_comments_desc')); return; }
        payload.deletion_type = delType;
        payload.admin_comments = adminComments;
        if (delType === 'user_decision') {
            if (!userReason) { showError(t('admin.manage.user_reason_desc')); return; }
            payload.deletion_reason = userReason;
        }
    }

    setLoading(btnSave, true);
    const res = await fetchApi(payload);

    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
        loadData();
    } else {
        showError(res.message);
    }
    setLoading(btnSave, false);
}

// --- ROLE LOGIC ---
function initRoleLogic() {
    const u = userData;
    const btnSave = document.getElementById('btn-save-role');
    
    let roleLabel = 'Usuario';
    let roleIcon = 'person';

    if (u.role === 'moderator') {
        roleLabel = 'Moderador';
        roleIcon = 'security';
    } else if (u.role === 'administrator') {
        roleLabel = 'Administrador';
        roleIcon = 'admin_panel_settings';
    } else if (u.role === 'founder') {
        roleLabel = 'Fundador';
        roleIcon = 'diamond';
    }

    updateRoleUI(u.role, roleLabel, roleIcon);
    setDropdownInitialActive('dropdown-roles', u.role);

    btnSave.onclick = () => saveRoleChanges();
}

function updateRoleUI(val, text, icon) {
    document.getElementById('role-input-value').value = val;
    document.getElementById('current-role-text').textContent = text;
    const iconEl = document.getElementById('current-role-icon');
    iconEl.textContent = icon;
    iconEl.style.color = ''; 
}

async function saveRoleChanges() {
    const newRole = document.getElementById('role-input-value').value;
    const btnSave = document.getElementById('btn-save-role');

    showError('', false);
    setLoading(btnSave, true);

    const payload = {
        action: 'update_user_role',
        target_id: targetId,
        role: newRole
    };

    const res = await fetchApi(payload);

    if (res.success) {
        if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
        loadData(); 
    } else {
        showError(res.message);
    }
    setLoading(btnSave, false);
}

// --- HISTORY LOGIC ---
function renderHistoryTable(logs) {
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
        const start = new Date(log.event_date).toLocaleString();
        const adminName = log.admin_name || 'Sistema';
        
        let icon = 'gavel';
        let reasonText = log.reason;
        let durationDisplay = '-';
        let endDisplay = '-';
        let liftedDisplay = '-';

        if (log.log_type === 'suspension') {
            icon = 'gavel';
            durationDisplay = (parseInt(log.duration_days) === -1) 
                ? `<span style="font-weight: 600;">${t('admin.status.perm_ban')}</span>` 
                : log.duration_days + ' ' + t('global.days');
            
            endDisplay = (parseInt(log.duration_days) === -1) 
                ? t('admin.history.indefinite') || 'Indefinido'
                : (log.ends_at ? new Date(log.ends_at).toLocaleString() : '-');

            if (log.lifted_at) {
                const liftedDate = new Date(log.lifted_at).toLocaleString();
                const lifter = log.lifter_name || 'Admin';
                liftedDisplay = `
                    <div style="display:flex; flex-direction:column;">
                        <span style="font-weight:600; font-size:13px; display:flex; align-items:center; gap:4px;">
                            <span class="material-symbols-rounded" style="font-size:16px;">check_circle</span> 
                            ${t('admin.history.lifted_on') || 'Levantada el'} ${liftedDate}
                        </span>
                        <span style="color:#888; font-size:11px; margin-left:20px;">${t('admin.history.by') || 'por'} ${lifter}</span>
                    </div>`;
            } else {
                const now = new Date();
                const endDate = log.ends_at ? new Date(log.ends_at) : null;
                if (parseInt(log.duration_days) === -1 || (endDate && endDate > now)) {
                    liftedDisplay = '<span class="component-badge component-badge--neutral">' + (t('global.active') || 'Activa') + '</span>';
                } else {
                    liftedDisplay = '<span class="component-badge component-badge--neutral">' + (t('global.status.expired') || 'Expirada') + '</span>';
                }
            }
        
        } else if (log.log_type === 'role_change') {
            icon = 'manage_accounts';
            reasonText = `<span style="font-weight:600;">${log.old_role}</span> <span class="material-symbols-rounded" style="font-size:12px; vertical-align:middle;">arrow_forward</span> <span style="font-weight:600; color:#000;">${log.new_role}</span>`;
            liftedDisplay = '<span class="component-badge component-badge--neutral">Cambio de Rol</span>';
        }

        html += `
            <tr class="component-table-row">
                <td>${start}</td>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-rounded" style="color:#666; font-size:18px;">${icon}</span>
                        ${reasonText}
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

function setDropdownInitialActive(dropdownId, value) {
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