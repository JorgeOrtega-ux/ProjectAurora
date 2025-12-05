// public/assets/js/modules/admin/admin-community-edit.js

import { AdminApi } from '../../services/api-service.js';
import { t } from '../../core/i18n-manager.js';
import { setButtonLoading } from '../../core/utilities.js'; 

let currentId = 0;
let currentChannels = []; // Array local de canales
let allMembers = []; // Cache local de miembros para filtrado
let bannedUsers = []; // Cache local de baneados

function toggleAccessCodeVisibility(privacyValue) {
    const wrapper = document.getElementById('wrapper-access-code');
    if (!wrapper) return;

    if (privacyValue === 'private') {
        wrapper.classList.remove('d-none');
        setTimeout(() => document.getElementById('input-comm-code')?.focus(), 50); 
    } else {
        wrapper.classList.add('d-none');
    }
}

export function initAdminCommunityEdit() {
    const inputId = document.getElementById('community-target-id');
    currentId = inputId ? parseInt(inputId.value) : 0;
    currentChannels = []; // Resetear canales
    allMembers = [];
    bannedUsers = [];

    if (currentId > 0) {
        loadData();
        loadMembers();
        loadBannedUsers();
    } else {
        generateCode();
        toggleAccessCodeVisibility('public');
        // Canal por defecto activo
        currentChannels.push({ id: 0, name: 'General', type: 'text', max_users: 0, status: 'active', is_default: true });
        renderChannels();
    }

    initListeners();
}

function initListeners() {
    // Dropdown Tipo de Comunidad
    document.body.addEventListener('click', (e) => {
        const opt = e.target.closest('[data-action="select-comm-type"]');
        if (opt) {
            const val = opt.dataset.value;
            const icon = opt.dataset.icon;
            const labelKey = opt.dataset.labelKey;
            
            document.getElementById('input-comm-type').value = val;
            document.getElementById('text-type').textContent = t(labelKey);
            document.getElementById('icon-type').textContent = icon;
        }
    });

    // Dropdown privacidad
    document.body.addEventListener('click', (e) => {
        const opt = e.target.closest('[data-action="select-comm-privacy"]');
        if (opt) {
            const val = opt.dataset.value;
            const label = opt.dataset.label;
            const icon = opt.dataset.icon;
            
            document.getElementById('input-comm-privacy').value = val;
            document.getElementById('text-privacy').textContent = label;
            document.getElementById('icon-privacy').textContent = icon;

            toggleAccessCodeVisibility(val);
        }
    });

    // Dropdown Tipo de Canal (SIMPLIFICADO: Siempre texto)
    document.body.addEventListener('click', (e) => {
        const opt = e.target.closest('[data-action="select-channel-type"]');
        if (opt) {
            const val = opt.dataset.value;
            const label = opt.dataset.label;
            document.getElementById('new-channel-type').value = val;
            document.getElementById('new-channel-type-text').textContent = label;
            
            // [MODIFICADO] Eliminada lógica de mostrar maxUsersWrapper para voz
            const maxUsersWrapper = document.getElementById('wrapper-max-users');
            if(maxUsersWrapper) maxUsersWrapper.classList.add('d-none');
        }
    });

    // Toggle estado del canal (Habilitado/Mantenimiento)
    document.getElementById('channels-list-container')?.addEventListener('click', (e) => {
        const statusBtn = e.target.closest('[data-action="toggle-channel-status"]');
        if (statusBtn) {
            const index = parseInt(statusBtn.dataset.index);
            if (currentChannels[index]) {
                // Alternar estado
                currentChannels[index].status = (currentChannels[index].status === 'maintenance') ? 'active' : 'maintenance';
                renderChannels();
            }
            return;
        }

        const defaultBtn = e.target.closest('[data-action="set-default-channel"]');
        if (defaultBtn) {
            const index = parseInt(defaultBtn.dataset.index);
            currentChannels.forEach(ch => ch.is_default = false);
            if (currentChannels[index]) {
                currentChannels[index].is_default = true;
            }
            renderChannels();
            return;
        }

        const delBtn = e.target.closest('[data-action="remove-channel"]');
        if (delBtn) {
            const index = parseInt(delBtn.dataset.index);
            if (currentChannels[index]) {
                if (currentChannels.length <= 1) {
                    return alert("La comunidad debe tener al menos un canal.");
                }
                const wasDefault = currentChannels[index].is_default;
                currentChannels.splice(index, 1);
                if (wasDefault && currentChannels.length > 0) {
                    currentChannels[0].is_default = true;
                }
                renderChannels();
            }
        }
    });

    // Agregar Canal (SIMPLIFICADO)
    const btnAddChannel = document.getElementById('btn-add-channel');
    if (btnAddChannel) {
        btnAddChannel.onclick = () => {
            const nameInput = document.getElementById('new-channel-name');
            const name = nameInput.value.trim();
            
            // [MODIFICADO] Forzar siempre texto y 0 usuarios
            const type = 'text';
            const maxUsers = 0;

            if (!name) return alert("El nombre del canal es obligatorio.");
            
            // Agregar al array local (estado por defecto: active)
            currentChannels.push({ id: 0, name: name, type: type, max_users: maxUsers, status: 'active', is_default: false });
            
            nameInput.value = '';
            // El input maxUsers ya no se usa ni se limpia
            renderChannels();
        };
    }

    // Buscador de miembros
    const searchMembersInput = document.getElementById('admin-members-search');
    if (searchMembersInput) {
        searchMembersInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const filtered = allMembers.filter(m => m.username.toLowerCase().includes(term));
            renderMembers(filtered);
        });
    }

    // Acciones de Miembros (Kick, Ban, Mute)
    document.getElementById('members-list-container')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const userId = btn.dataset.userId;
        const action = btn.dataset.action;
        if (!userId || !action) return;

        if (action === 'kick') {
            if(!confirm("¿Seguro que deseas expulsar a este usuario?")) return;
            const res = await AdminApi.kickMember(currentId, userId);
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert("Usuario expulsado", 'success');
                loadMembers();
            } else {
                alert(res.message);
            }
        } else if (action === 'ban') {
            const reason = prompt("Razón del baneo:");
            if (reason === null) return;
            
            // [MODIFICADO] Solicitar duración
            const duration = prompt("Duración (12h, 1d, 3d, 1w, o vacío para permanente):", "");
            if (duration === null) return; // Cancelar
            
            const res = await AdminApi.banMember(currentId, userId, reason, duration);
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert("Usuario sancionado", 'success');
                loadMembers();
                loadBannedUsers();
            } else {
                alert(res.message);
            }
        } else if (action === 'mute') {
            const duration = prompt("Duración del silencio (minutos):", "15");
            if (duration === null) return;
            const res = await AdminApi.muteMember(currentId, userId, duration);
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert("Usuario silenciado", 'success');
                // Opcional: recargar si mostramos estado de mute visualmente
            } else {
                alert(res.message);
            }
        }
    });

    // Acciones de Baneados (Unban)
    document.getElementById('banned-list-container')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const userId = btn.dataset.userId;
        const action = btn.dataset.action;
        
        if (action === 'unban') {
            if(!confirm("¿Levantar el baneo a este usuario?")) return;
            const res = await AdminApi.unbanMember(currentId, userId);
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert("Usuario desbaneado", 'success');
                loadBannedUsers();
                loadMembers();
            } else {
                alert(res.message);
            }
        }
    });

    document.getElementById('btn-gen-code').onclick = generateCode;
    document.getElementById('input-comm-pfp').addEventListener('blur', updatePreviews);
    document.getElementById('input-comm-banner').addEventListener('blur', updatePreviews);
    document.getElementById('btn-save-community').onclick = saveCommunity;

    const btnDel = document.getElementById('btn-delete-community');
    if (btnDel) btnDel.onclick = deleteCommunity;
}

async function loadData() {
    const res = await AdminApi.getCommunityDetails(currentId);

    if (res.success && res.community) {
        const c = res.community;
        document.getElementById('input-comm-name').value = c.community_name;
        document.getElementById('input-comm-code').value = c.access_code;
        document.getElementById('input-comm-pfp').value = c.profile_picture || '';
        document.getElementById('input-comm-banner').value = c.banner_picture || '';
        
        // [NUEVO] Cargar Max Members y Status
        document.getElementById('input-comm-max-members').value = c.max_members || 0;
        
        const toggleVerified = document.getElementById('toggle-comm-verified');
        if (toggleVerified) toggleVerified.checked = (parseInt(c.is_verified) === 1);

        const toggleStatus = document.getElementById('toggle-comm-status');
        if (toggleStatus) toggleStatus.checked = (c.status === 'maintenance');

        const privEl = document.querySelector(`[data-action="select-comm-privacy"][data-value="${c.privacy}"]`);
        if(privEl) privEl.click(); 

        const typeEl = document.querySelector(`[data-action="select-comm-type"][data-value="${c.community_type}"]`);
        if (typeEl) {
            typeEl.click();
        } else {
            const defaultType = document.querySelector(`[data-action="select-comm-type"][data-value="other"]`);
            if(defaultType) defaultType.click();
        }
        
        if (c.channels && Array.isArray(c.channels)) {
            currentChannels = c.channels.map(ch => ({
                id: ch.id,
                name: ch.name,
                type: ch.type, // Se mantiene el tipo original por si acaso, pero visualmente será texto
                max_users: ch.max_users,
                status: ch.status || 'active', // Cargar estado del canal
                is_default: (parseInt(ch.id) === parseInt(c.default_channel_id))
            }));
        }
        
        if (currentChannels.length > 0 && !currentChannels.some(ch => ch.is_default)) {
            currentChannels[0].is_default = true;
        }

        renderChannels();
        toggleAccessCodeVisibility(c.privacy);
        updatePreviews();
    }
}

// [NUEVO] Cargar lista de miembros
async function loadMembers() {
    const container = document.getElementById('members-list-container');
    if (!container) return;
    
    // Spinner inicial si está vacío
    if(container.children.length === 0) container.innerHTML = '<div class="small-spinner" style="margin: 20px auto;"></div>';

    const res = await AdminApi.getCommunityMembers(currentId);
    if (res.success) {
        allMembers = res.members;
        renderMembers(allMembers);
    } else {
        container.innerHTML = `<div style="padding:15px; text-align:center; color:#999;">Error al cargar miembros.</div>`;
    }
}

function renderMembers(list) {
    const container = document.getElementById('members-list-container');
    if (!container) return;
    container.innerHTML = '';

    if (list.length === 0) {
        container.innerHTML = `<div style="padding:15px; text-align:center; color:#999;">No se encontraron miembros.</div>`;
        return;
    }

    list.forEach(m => {
        const row = document.createElement('div');
        row.style.cssText = `display: flex; align-items: center; padding: 8px 12px; background: #fff; border: 1px solid #eee; border-radius: 8px; gap: 10px;`;
        
        const pfp = m.profile_picture || 'assets/uploads/profile_pictures/default/default.png';
        
        let roleBadge = '';
        if (m.role === 'admin') roleBadge = '<span style="font-size:10px; background:#e3f2fd; color:#1565c0; padding:2px 6px; border-radius:4px; margin-left:6px;">ADMIN</span>';
        if (m.role === 'moderator') roleBadge = '<span style="font-size:10px; background:#e8f5e9; color:#2e7d32; padding:2px 6px; border-radius:4px; margin-left:6px;">MOD</span>';

        row.innerHTML = `
            <img src="${pfp}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 14px; color: #333;">${m.username} ${roleBadge}</div>
                <div style="font-size: 11px; color: #888;">${m.email || 'Sin email'}</div>
            </div>
            <div style="display: flex; gap: 4px;">
                <button class="component-icon-button small" data-action="mute" data-user-id="${m.id}" title="Silenciar (Mute)" style="width: 28px; height: 28px; color: #f57c00; border: 1px solid #ffe0b2;">
                    <span class="material-symbols-rounded" style="font-size: 16px;">volume_off</span>
                </button>
                <button class="component-icon-button small" data-action="kick" data-user-id="${m.id}" title="Expulsar (Kick)" style="width: 28px; height: 28px; color: #d32f2f; border: 1px solid #ffcdd2;">
                    <span class="material-symbols-rounded" style="font-size: 16px;">person_remove</span>
                </button>
                <button class="component-icon-button small" data-action="ban" data-user-id="${m.id}" title="Banear" style="width: 28px; height: 28px; color: #fff; background-color: #d32f2f; border: none;">
                    <span class="material-symbols-rounded" style="font-size: 16px;">block</span>
                </button>
            </div>
        `;
        container.appendChild(row);
    });
}

// [NUEVO] Cargar lista de baneados
async function loadBannedUsers() {
    const container = document.getElementById('banned-list-container');
    if (!container) return;
    
    // Spinner
    if(container.children.length === 0) container.innerHTML = '<div class="small-spinner" style="margin: 20px auto;"></div>';

    const res = await AdminApi.getCommunityBannedUsers(currentId);
    if (res.success) {
        bannedUsers = res.banned_users;
        renderBannedUsers(bannedUsers);
    } else {
        container.innerHTML = `<div style="padding:15px; text-align:center; color:#999;">Error al cargar baneados.</div>`;
    }
}

function renderBannedUsers(list) {
    const container = document.getElementById('banned-list-container');
    if (!container) return;
    container.innerHTML = '';

    if (list.length === 0) {
        container.innerHTML = `<div style="padding:15px; text-align:center; color:#999;">No hay usuarios baneados.</div>`;
        return;
    }

    list.forEach(u => {
        const row = document.createElement('div');
        row.style.cssText = `display: flex; align-items: center; padding: 8px 12px; background: #fff0f0; border: 1px solid #ffcdd2; border-radius: 8px; gap: 10px;`;
        
        const pfp = u.profile_picture || 'assets/uploads/profile_pictures/default/default.png';

        row.innerHTML = `
            <img src="${pfp}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; opacity: 0.7;">
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 14px; color: #c62828;">${u.username}</div>
                <div style="font-size: 11px; color: #d32f2f;">Razón: ${u.reason || 'Sin razón'}</div>
            </div>
            <button class="component-button small" data-action="unban" data-user-id="${u.id}" style="font-size: 11px; padding: 4px 8px; height: auto;">
                Desbanear
            </button>
        `;
        container.appendChild(row);
    });
}

function renderChannels() {
    const container = document.getElementById('channels-list-container');
    if (!container) return;

    container.innerHTML = '';

    currentChannels.forEach((ch, index) => {
        // [MODIFICADO] Siempre icono de texto, sin lógica de voz
        const icon = 'tag';
        const isDefault = ch.is_default === true;
        
        const extraInfo = ''; 
        const typeLabel = 'Texto';

        const defaultIcon = isDefault ? 'radio_button_checked' : 'radio_button_unchecked';
        const defaultColor = isDefault ? '#1976d2' : '#999';
        const activeClass = isDefault ? 'active-default-channel' : '';
        const titleDefault = isDefault ? 'Canal Principal' : 'Marcar como principal';

        // [NUEVO] Lógica visual para el estado
        const isMaintenance = (ch.status === 'maintenance');
        const statusIcon = isMaintenance ? 'engineering' : 'check_circle';
        const statusColor = isMaintenance ? '#f57c00' : '#2e7d32';
        const statusTitle = isMaintenance ? 'En Mantenimiento (Click para activar)' : 'Activo (Click para mantenimiento)';
        const bgRow = isMaintenance ? '#fff3e0' : (isDefault ? '#e3f2fd' : '#fff');

        const row = document.createElement('div');
        row.className = `channel-edit-row ${activeClass}`;
        row.style.cssText = `display: flex; align-items: center; padding: 8px 12px; background: ${bgRow}; border: 1px solid ${isDefault ? '#90caf9' : '#e0e0e0'}; border-radius: 8px; gap: 10px; transition: all 0.2s;`;
        
        row.innerHTML = `
            <div class="component-icon-button small" data-action="set-default-channel" data-index="${index}" title="${titleDefault}" style="width: 24px; height: 24px; border:none; cursor:pointer;">
                <span class="material-symbols-rounded" style="color: ${defaultColor}; font-size: 20px;">${defaultIcon}</span>
            </div>
            
            <span class="material-symbols-rounded" style="color: #666; font-size: 20px;">${icon}</span>
            
            <div style="flex: 1; display: flex; flex-direction: column;">
                <span style="font-size: 14px; font-weight: 600; color: #333;">${ch.name}</span>
                <span style="font-size: 11px; color: #888; text-transform: capitalize;">${typeLabel}${extraInfo}</span>
            </div>

            <div class="component-icon-button small" data-action="toggle-channel-status" data-index="${index}" title="${statusTitle}" style="width: 24px; height: 24px; border:none; cursor:pointer;">
                <span class="material-symbols-rounded" style="color: ${statusColor}; font-size: 20px;">${statusIcon}</span>
            </div>
            
            <button class="component-icon-button small" data-action="remove-channel" data-index="${index}" style="width: 32px; height: 32px; border-color: transparent; color: #d32f2f;">
                <span class="material-symbols-rounded" style="font-size: 18px;">delete</span>
            </button>
        `;
        container.appendChild(row);
    });
}

function generateCode() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 12; i++) {
        if (i > 0 && i % 4 === 0) code += '-';
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('input-comm-code').value = code;
}

function updatePreviews() {
    const pfpUrl = document.getElementById('input-comm-pfp').value.trim();
    const bannerUrl = document.getElementById('input-comm-banner').value.trim();

    const imgPfp = document.getElementById('preview-avatar');
    const phPfp = document.getElementById('placeholder-avatar');
    
    if (pfpUrl) {
        imgPfp.src = pfpUrl; imgPfp.style.display = 'block'; phPfp.style.display = 'none';
    } else {
        imgPfp.style.display = 'none'; phPfp.style.display = 'flex';
    }

    const imgBan = document.getElementById('preview-banner');
    const phBan = document.getElementById('placeholder-banner');

    if (bannerUrl) {
        imgBan.src = bannerUrl; imgBan.style.display = 'block'; phBan.style.display = 'none';
    } else {
        imgBan.style.display = 'none'; phBan.style.display = 'flex';
    }
}

async function saveCommunity() {
    const btn = document.getElementById('btn-save-community');
    
    const name = document.getElementById('input-comm-name').value.trim();
    const code = document.getElementById('input-comm-code').value.trim();

    if (!name) return alert("El nombre es obligatorio.");
    if (!code) return alert("El código de acceso es obligatorio.");
    if (currentChannels.length === 0) return alert("Debes agregar al menos un canal.");
    
    if (!currentChannels.some(ch => ch.is_default)) {
        currentChannels[0].is_default = true;
    }

    if (window.setButtonLoading) {
        window.setButtonLoading(btn, true);
    } else {
        btn.disabled = true;
        btn.innerHTML = '<div class="small-spinner"></div>';
    }

    const isVerified = document.getElementById('toggle-comm-verified').checked;
    
    // [NUEVO] Capturar estado de mantenimiento y max members
    const isMaintenance = document.getElementById('toggle-comm-status').checked;
    const maxMembers = parseInt(document.getElementById('input-comm-max-members').value) || 0;

    const payload = {
        id: currentId,
        name: name,
        community_type: document.getElementById('input-comm-type').value,
        privacy: document.getElementById('input-comm-privacy').value,
        access_code: code,
        profile_picture: document.getElementById('input-comm-pfp').value,
        banner_picture: document.getElementById('input-comm-banner').value,
        is_verified: isVerified,
        status: isMaintenance ? 'maintenance' : 'active', // Enviar estado
        max_members: maxMembers, // Enviar límite
        channels: currentChannels // Los canales ya llevan su status interno
    };

    const res = await AdminApi.saveCommunity(payload);
    
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        if (currentId === 0) {
            setTimeout(() => window.navigateTo('admin/communities'), 1000);
        } else {
            loadData();
        }
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
    
    if (window.setButtonLoading) {
        window.setButtonLoading(btn, false, '<span class="material-symbols-rounded">save</span>');
    } else {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded">save</span>';
    }
}

async function deleteCommunity() {
    if (!confirm("¿Seguro que quieres eliminar esta comunidad? Esta acción es irreversible.")) return;
    
    const btn = document.getElementById('btn-delete-community');
    if (window.setButtonLoading) window.setButtonLoading(btn, true);

    const res = await AdminApi.deleteCommunity(currentId);

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
        window.navigateTo('admin/communities');
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        if (window.setButtonLoading) window.setButtonLoading(btn, false, '<span class="material-symbols-rounded">delete</span>');
    }
}