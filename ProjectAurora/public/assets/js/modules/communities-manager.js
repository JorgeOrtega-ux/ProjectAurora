// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';

// --- UTILIDADES ---
function renderCommunityCard(comm, isMyList) {
    const isPrivate = comm.privacy === 'private';
    const privacyText = isPrivate ? 'Privado' : 'Público';
    const memberText = comm.member_count + (comm.member_count === 1 ? ' Miembro' : ' Miembros');
    
    let buttonHtml = '';
    
    if (isMyList) {
        buttonHtml = `
            <button class="component-button comm-btn-danger" data-action="leave-community" data-id="${comm.id}" title="Abandonar">
                <span class="material-symbols-rounded">logout</span>
            </button>
            <button class="component-button primary comm-btn-primary">Entrar</button>
        `;
    } else {
        buttonHtml = `
            <button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">
                Unirse
            </button>
        `;
    }

    const bannerSrc = comm.banner_picture ? comm.banner_picture : 'https://picsum.photos/seed/generic/600/200';
    
    const avatarHtml = comm.profile_picture 
        ? `<img src="${comm.profile_picture}" class="comm-avatar-img" alt="${comm.community_name}">` 
        : `<div class="comm-avatar-placeholder"><span class="material-symbols-rounded">groups</span></div>`;

    return `
    <div class="comm-card">
        <div class="comm-banner" style="background-image: url('${bannerSrc}');"></div>
        
        <div class="comm-content">
            
            <div class="comm-header-row">
                <div class="comm-avatar-container">
                    ${avatarHtml}
                </div>
                <div class="comm-actions">
                    ${buttonHtml}
                </div>
            </div>

            <div class="comm-info">
                <h3 class="comm-title">${comm.community_name}</h3>
                
                <div class="comm-badges">
                    <span class="comm-badge">${memberText}</span>
                    <span class="comm-badge">${privacyText}</span>
                </div>

                <p class="comm-desc">
                    ${comm.description || 'Sin descripción disponible.'}
                </p>
            </div>
        </div>
    </div>`;
}

// --- LÓGICA DE JOIN BY CODE ---
function initJoinByCode() {
    const input = document.querySelector('[data-input="community-code"]');
    if (!input) return;

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
    const emptyState = document.getElementById('my-communities-empty'); // Referencia al estado vacío externo
    if (!container) return;

    const res = await postJson('api/communities_handler.php', { action: 'get_my_communities' });
    
    // Limpiar el spinner
    container.innerHTML = '';

    if (res.success && res.communities.length > 0) {
        container.innerHTML = res.communities.map(c => renderCommunityCard(c, true)).join('');
        if (emptyState) emptyState.classList.add('d-none');
    } else {
        // Mostrar el estado vacío externo
        if (emptyState) emptyState.classList.remove('d-none');
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
        
        const joinBtn = e.target.closest('[data-action="join-public-community"]');
        if (joinBtn) {
            const id = joinBtn.dataset.id;
            setButtonLoading(joinBtn, true);
            const res = await postJson('api/communities_handler.php', { action: 'join_public', community_id: id });
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                joinBtn.closest('.comm-card').remove();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false);
            }
        }

        const leaveBtn = e.target.closest('[data-action="leave-community"]');
        if (leaveBtn) {
            if (!confirm('¿Seguro que quieres salir de este grupo?')) return;
            const id = leaveBtn.dataset.id;
            
            const originalHTML = leaveBtn.innerHTML;
            leaveBtn.disabled = true;
            leaveBtn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite">sync</span>';

            const res = await postJson('api/communities_handler.php', { action: 'leave_community', community_id: id });
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
                
                // Remover tarjeta y verificar si quedó vacío
                const card = leaveBtn.closest('.comm-card');
                const container = card.parentElement;
                card.remove();
                
                if (container && container.children.length === 0) {
                    const emptyState = document.getElementById('my-communities-empty');
                    if (emptyState) emptyState.classList.remove('d-none');
                }

            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                leaveBtn.innerHTML = originalHTML;
                leaveBtn.disabled = false;
            }
        }
    });
}

export function initCommunitiesManager() {
    initJoinByCode();
    loadMyCommunities();
    loadPublicCommunities();
    
    if (!window.communitiesListenersInit) {
        initListeners();
        window.communitiesListenersInit = true;
    }
}