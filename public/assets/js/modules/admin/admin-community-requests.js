// public/assets/js/modules/admin/admin-community-requests.js

import { AdminApi } from '../../services/api-service.js';

export function initAdminCommunityRequests() {
    loadRequests();
    initListeners();
}

function initListeners() {
    // Event delegation para botones de Aceptar/Rechazar dentro de las cápsulas
    const listContainer = document.getElementById('requests-list-container');
    if (listContainer) {
        listContainer.addEventListener('click', async (e) => {
            const actionBtn = e.target.closest('[data-action]');
            if (!actionBtn) return;

            const action = actionBtn.dataset.action;
            const reqId = actionBtn.dataset.id;
            // Prevenir navegación si la row tuviera click (aunque aquí no hay navegación en la row)
            e.stopPropagation(); 

            if (action === 'accept-request') {
                await resolveRequest(reqId, 'accept');
            } else if (action === 'reject-request') {
                if (confirm('¿Estás seguro de rechazar esta solicitud?')) {
                    await resolveRequest(reqId, 'reject');
                }
            }
        });
    }
}

async function loadRequests() {
    const container = document.getElementById('requests-list-container');
    if (!container) return;

    container.innerHTML = `<div class="small-spinner" style="margin: 20px auto;"></div>`;

    try {
        const res = await AdminApi.getJoinRequests();

        if (res.success) {
            renderList(res.requests);
        } else {
            container.innerHTML = `<div class="empty-capsule-state" style="color: #d32f2f;"><p>${res.message}</p></div>`;
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = `<div class="empty-capsule-state" style="color: #d32f2f;"><p>Error de conexión al cargar solicitudes.</p></div>`;
    }
}

function renderList(list) {
    const container = document.getElementById('requests-list-container');
    
    if (!list || list.length === 0) {
        container.innerHTML = `
            <div class="empty-capsule-state">
                <span class="material-symbols-rounded icon">inbox</span>
                <p>No hay solicitudes pendientes.</p>
            </div>`;
        return;
    }

    let html = '';
    list.forEach(req => {
        const userAvatar = req.user_picture || `https://ui-avatars.com/api/?name=${encodeURIComponent(req.username)}`;
        const dateStr = new Date(req.created_at).toLocaleDateString();

        html += `
            <div class="user-capsule-row" style="cursor: default;">
                
                <div class="capsule-avatar">
                    <img src="${userAvatar}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" alt="User">
                </div>

                <div class="info-pill primary-pill">
                    <span class="pill-content strong">${req.username}</span>
                </div>

                <div class="info-pill" style="background-color: #f5f5f5; border-color: #e0e0e0;">
                    <span class="pill-label material-symbols-rounded" style="color: #666;">groups</span>
                    <span class="pill-content">${req.community_name}</span>
                </div>

                <div class="info-pill">
                    <span class="pill-label material-symbols-rounded">calendar_today</span>
                    <span class="pill-content">${dateStr}</span>
                </div>
                
                <div style="margin-left: auto; display: flex; gap: 8px;">
                    <button class="component-icon-button" data-action="accept-request" data-id="${req.id}" 
                            style="color: #2e7d32; background: #e8f5e9; width:36px; height:36px; border: 1px solid #c8e6c9;" 
                            data-tooltip="Aceptar">
                        <span class="material-symbols-rounded">check</span>
                    </button>
                    
                    <button class="component-icon-button" data-action="reject-request" data-id="${req.id}" 
                            style="color: #c62828; background: #ffebee; width:36px; height:36px; border: 1px solid #ffcdd2;" 
                            data-tooltip="Rechazar">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

async function resolveRequest(reqId, decision) {
    // Feedback visual optimista: desvanecer la fila
    const btn = document.querySelector(`button[data-id="${reqId}"]`);
    const row = btn?.closest('.user-capsule-row');
    if (row) row.style.opacity = '0.5';

    try {
        const res = await AdminApi.resolveJoinRequest(reqId, decision);

        if (res.success) {
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.transform = 'translateX(20px)';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    // Verificar si quedan elementos
                    const remaining = document.querySelectorAll('#requests-list-container .user-capsule-row');
                    if (remaining.length === 0) renderList([]);
                }, 300);
            }
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
        } else {
            if (row) row.style.opacity = '1';
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
        }
    } catch (e) {
        console.error(e);
        if (row) row.style.opacity = '1';
        alert('Error de conexión');
    }
}