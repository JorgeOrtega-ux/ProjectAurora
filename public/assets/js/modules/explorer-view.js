// public/assets/js/modules/explorer-view.js

import { CommunityApi } from '../services/api-service.js';
import { setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';

let areListenersInit = false;

export function initExplorerView() {
    loadPublicCommunities();
    initListeners();
}

async function loadPublicCommunities() {
    const container = document.getElementById('public-communities-list');
    if (!container) return;
    
    // Spinner inicial
    container.innerHTML = `<div class="small-spinner" style="margin: 40px auto; grid-column: 1 / -1;"></div>`;

    const res = await CommunityApi.getPublicCommunities();
    
    if (res.success && res.communities.length > 0) {
        container.innerHTML = res.communities.map(c => renderCommunityCard(c, false)).join('');
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; grid-column:1/-1;">No hay comunidades públicas disponibles.</p>`;
    }
}

function renderCommunityCard(comm, isJoined) {
    const escapeHtml = (text) => {
        if (!text) return '';
        return text.replace(/[&<>"']/g, function(m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]; });
    };

    const avatar = comm.profile_picture ? (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
    
    const bannerSrc = comm.banner_picture 
        ? (window.BASE_PATH || '/ProjectAurora/') + comm.banner_picture 
        : `https://placehold.co/600x200/555555/ffffff?text=${encodeURIComponent(comm.community_name)}`;

    let actionBtn = isJoined ? `<button class="component-button" disabled>Unido</button>` : `<button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">Unirse</button>`;
    
    const typeKey = comm.community_type || 'other';
    const descriptionText = t('communities.descriptions.' + typeKey);

    const verifiedBadge = (parseInt(comm.is_verified) === 1) 
        ? `<span class="material-symbols-rounded" style="font-size:18px; color:#1976d2; margin-left:6px;" title="Oficial">verified</span>` 
        : '';

    return `
    <div class="comm-card">
        <div class="comm-banner">
            <img src="${bannerSrc}" alt="${escapeHtml(comm.community_name)}" data-img-type="banner" loading="lazy">
        </div>
        <div class="comm-content">
            <div class="comm-header-row">
                <div class="comm-avatar-container">
                    <img src="${avatar}" class="comm-avatar-img" alt="${escapeHtml(comm.community_name)}" data-img-type="community">
                </div>
                <div class="comm-actions">${actionBtn}</div>
            </div>
            <div class="comm-info">
                <h3 class="comm-title" style="display:flex; align-items:center;">
                    ${escapeHtml(comm.community_name)}${verifiedBadge}
                </h3>
                <div class="comm-badges">
                    <span class="comm-badge"><span class="material-symbols-rounded" style="font-size:14px; margin-right:4px;">group</span>${comm.member_count} miembros</span>
                    <span class="comm-badge">Publico</span>
                </div>
                <p class="comm-desc" style="margin-top:8px;">${escapeHtml(descriptionText)}</p>
            </div>
        </div>
    </div>`;
}

function initListeners() {
    if (areListenersInit) return;

    document.body.addEventListener('click', async (e) => {
        const joinBtn = e.target.closest('[data-action="join-public-community"]');
        if (joinBtn) {
            e.preventDefault();
            const id = joinBtn.dataset.id;
            setButtonLoading(joinBtn, true);
            
            const res = await CommunityApi.joinPublic(id);
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                
                // Animación de salida de la tarjeta
                const card = joinBtn.closest('.comm-card');
                if (card) {
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => card.remove(), 300);
                }

                // Disparar evento para que communities-manager actualice la sidebar
                document.dispatchEvent(new CustomEvent('refresh-sidebar-request'));

            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false, 'Unirse');
            }
            return;
        }
    });

    areListenersInit = true;
}