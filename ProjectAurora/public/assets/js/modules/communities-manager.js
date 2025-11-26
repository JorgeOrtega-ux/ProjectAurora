// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';

// --- UTILIDADES ---
function renderCommunityCard(comm, isMyList) {
    const isPrivate = comm.privacy === 'private';
    const icon = isPrivate ? 'lock' : 'public';
    const memberText = comm.member_count + (comm.member_count === 1 ? ' miembro' : ' miembros');
    
    let buttonHtml = '';
    
    if (isMyList) {
        // Si es mi lista, botón de abandonar
        buttonHtml = `
            <button class="component-button" style="color: #d32f2f; border-color: #ffcdd2;" 
                    data-action="leave-community" data-id="${comm.id}">
                Abandonar
            </button>
            <button class="component-button primary">Entrar</button>
        `;
    } else {
        // Si es explorer (público), botón de unirse
        buttonHtml = `
            <button class="component-button primary" 
                    data-action="join-public-community" data-id="${comm.id}">
                Unirse
            </button>
        `;
    }

    const imgHtml = comm.profile_picture 
        ? `<img src="${comm.profile_picture}" style="width:100%; height:100%; object-fit:cover;">` 
        : `<div style="width:100%; height:100%; background:#eee; display:flex; align-items:center; justify-content:center;">
             <span class="material-symbols-rounded" style="font-size:24px; color:#999;">groups</span>
           </div>`;

    return `
    <div class="component-card" style="padding: 16px;">
        <div style="display:flex; gap:16px; align-items:center; margin-bottom:12px;">
            <div style="width:48px; height:48px; border-radius:8px; overflow:hidden; flex-shrink:0;">
                ${imgHtml}
            </div>
            <div style="flex:1; overflow:hidden;">
                <h3 style="margin:0; font-size:16px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${comm.community_name}</h3>
                <div style="display:flex; align-items:center; gap:4px; font-size:12px; color:#666; margin-top:2px;">
                    <span class="material-symbols-rounded" style="font-size:14px;">${icon}</span>
                    <span>${memberText}</span>
                </div>
            </div>
        </div>
        <p style="font-size:13px; color:#444; margin:0 0 16px 0; line-height:1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
            ${comm.description || 'Sin descripción.'}
        </p>
        <div style="display:flex; gap:8px; justify-content:flex-end;">
            ${buttonHtml}
        </div>
    </div>`;
}

// --- LÓGICA DE JOIN BY CODE ---
function initJoinByCode() {
    const input = document.querySelector('[data-input="community-code"]');
    if (!input) return;

    // Formateador XXXX-XXXX-XXXX automático
    input.addEventListener('input', (e) => {
        let v = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        if (v.length > 12) v = v.slice(0, 12);
        
        const parts = [];
        if (v.length > 0) parts.push(v.slice(0, 4));
        if (v.length > 4) parts.push(v.slice(4, 8));
        if (v.length > 8) parts.push(v.slice(8, 12));
        
        e.target.value = parts.join('-');
    });

    const btn = document.querySelector('[data-action="submit-join-community"]');
    btn.addEventListener('click', async () => {
        if (input.value.length < 14) return alert('Código incompleto. Debe ser XXXX-XXXX-XXXX');
        
        setButtonLoading(btn, true);
        
        const res = await postJson('api/communities_handler.php', { 
            action: 'join_by_code', 
            access_code: input.value 
        });

        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
            window.navigateTo('main');
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false, 'Unirse');
        }
    });
}

// --- CARGADORES ---
async function loadMyCommunities() {
    const container = document.getElementById('my-communities-list');
    if (!container) return;

    const res = await postJson('api/communities_handler.php', { action: 'get_my_communities' });
    
    if (res.success && res.communities.length > 0) {
        container.innerHTML = res.communities.map(c => renderCommunityCard(c, true)).join('');
    } else {
        container.innerHTML = `
            <div class="search-empty-state">
                <span class="material-symbols-rounded">diversity_3</span>
                <p>No estás en ninguna comunidad aún.</p>
            </div>`;
    }
}

async function loadPublicCommunities() {
    const container = document.getElementById('public-communities-list');
    if (!container) return;

    const res = await postJson('api/communities_handler.php', { action: 'get_public_communities' });
    
    if (res.success && res.communities.length > 0) {
        container.innerHTML = res.communities.map(c => renderCommunityCard(c, false)).join('');
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; grid-column:1/-1;">No hay comunidades públicas disponibles.</p>`;
    }
}

// --- LISTENERS GLOBALES ---
function initListeners() {
    document.body.addEventListener('click', async (e) => {
        
        // Unirse a pública
        const joinBtn = e.target.closest('[data-action="join-public-community"]');
        if (joinBtn) {
            const id = joinBtn.dataset.id;
            setButtonLoading(joinBtn, true);
            const res = await postJson('api/communities_handler.php', { action: 'join_public', community_id: id });
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                // Eliminar tarjeta de explorer visualmente para feedback inmediato
                joinBtn.closest('.component-card').remove();
            } else {
                alert(res.message);
                setButtonLoading(joinBtn, false);
            }
        }

        // Salir de comunidad
        const leaveBtn = e.target.closest('[data-action="leave-community"]');
        if (leaveBtn) {
            if (!confirm('¿Seguro que quieres salir de este grupo?')) return;
            const id = leaveBtn.dataset.id;
            setButtonLoading(leaveBtn, true);
            const res = await postJson('api/communities_handler.php', { action: 'leave_community', community_id: id });
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
                leaveBtn.closest('.component-card').remove();
            } else {
                alert(res.message);
                setButtonLoading(leaveBtn, false);
            }
        }
    });
}

export function initCommunitiesManager() {
    initJoinByCode();
    loadMyCommunities();
    loadPublicCommunities();
    
    // Evitar duplicar listeners globales si se llama múltiples veces
    if (!window.communitiesListenersInit) {
        initListeners();
        window.communitiesListenersInit = true;
    }
}