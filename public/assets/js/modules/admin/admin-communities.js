// public/assets/js/modules/admin/admin-communities.js

import { AdminApi } from '../../services/api-service.js';

let searchTimer = null;

export function initAdminCommunities() {
    loadCommunities();
    initListeners();
}

function initListeners() {
    const searchInput = document.getElementById('admin-communities-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                loadCommunities(e.target.value);
            }, 400);
        });
    }

    document.getElementById('communities-list-container')?.addEventListener('click', (e) => {
        const row = e.target.closest('.user-capsule-row');
        if (row && row.dataset.id) {
            if (window.navigateTo) window.navigateTo('admin/community-edit?id=' + row.dataset.id);
        }
    });

    // Listeners para Modal de Solicitudes
    const btnRequests = document.getElementById('btn-view-requests');
    const modal = document.getElementById('requests-modal');
    const closeBtn = document.getElementById('close-requests-modal');

    if (btnRequests && modal) {
        btnRequests.addEventListener('click', () => {
            modal.style.display = 'flex';
            loadJoinRequests();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });

        // Event delegation para botones de Aceptar/Rechazar
        const listContainer = document.getElementById('requests-list-container');
        if (listContainer) {
            listContainer.addEventListener('click', async (e) => {
                const actionBtn = e.target.closest('[data-action]');
                if (!actionBtn) return;

                const action = actionBtn.dataset.action;
                const reqId = actionBtn.dataset.id;

                if (action === 'accept-request') {
                    await resolveRequest(reqId, 'accept');
                } else if (action === 'reject-request') {
                    if (confirm('¿Rechazar solicitud?')) {
                        await resolveRequest(reqId, 'reject');
                    }
                }
            });
        }
    }
}

async function loadCommunities(query = '') {
    const container = document.getElementById('communities-list-container');
    if (!container) return;

    container.innerHTML = `<div class="small-spinner" style="margin: 20px auto;"></div>`;

    const res = await AdminApi.listCommunities(query);

    if (res.success) {
        renderList(res.communities);
    } else {
        container.innerHTML = `<div class="empty-capsule-state" style="color: #d32f2f;"><p>${res.message}</p></div>`;
    }
}

function renderList(list) {
    const container = document.getElementById('communities-list-container');
    if (!list || list.length === 0) {
        container.innerHTML = `
            <div class="empty-capsule-state">
                <span class="material-symbols-rounded icon">groups</span>
                <p>No se encontraron comunidades.</p>
            </div>`;
        return;
    }

    let html = '';
    list.forEach(c => {
        const isPrivate = c.privacy === 'private';
        const icon = isPrivate ? 'lock' : 'public';
        const avatar = c.profile_picture || `https://ui-avatars.com/api/?name=${encodeURIComponent(c.community_name)}`;
        
        const verifiedPill = (parseInt(c.is_verified) === 1) 
            ? `<div class="info-pill" style="border-color: #90caf9; color: #1976d2; background-color: #e3f2fd;">
                 <span class="pill-label material-symbols-rounded" style="color: #1976d2;">verified</span>
                 <span class="pill-content">Oficial</span>
               </div>`
            : '';

        html += `
            <div class="user-capsule-row" data-id="${c.id}" style="cursor: pointer;">
                
                <div class="capsule-avatar">
                    <img src="${avatar}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" data-img-type="community">
                </div>

                <div class="info-pill primary-pill">
                    <span class="pill-content strong">${c.community_name}</span>
                </div>

                ${verifiedPill}

                <div class="info-pill">
                    <span class="pill-label material-symbols-rounded">${icon}</span>
                    <span class="pill-content" style="text-transform: capitalize;">${c.privacy}</span>
                </div>

                <div class="info-pill">
                    <span class="pill-label material-symbols-rounded">group</span>
                    <span class="pill-content">${c.member_count}</span>
                </div>
                
                <div class="info-pill" style="margin-left: auto; border:none; background:transparent;">
                    <span class="material-symbols-rounded" style="color:#999;">chevron_right</span>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

// [NUEVO] Funciones para gestión de Solicitudes

async function loadJoinRequests() {
    const container = document.getElementById('requests-list-container');
    if (!container) return;
    
    container.innerHTML = '<div class="small-spinner" style="margin: 20px auto;"></div>';

    try {
        // [CORREGIDO] Usar AdminApi para asegurar la ruta correcta
        const res = await AdminApi.getJoinRequests();

        if (res.success) {
            renderRequestsTable(res.requests);
        } else {
            container.innerHTML = `<p style="text-align:center; color:#d32f2f;">${res.message}</p>`;
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = `<p style="text-align:center; color:#d32f2f;">Error de conexión.</p>`;
    }
}

function renderRequestsTable(requests) {
    const container = document.getElementById('requests-list-container');
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding: 40px; color: var(--text-secondary);">
                <span class="material-symbols-rounded" style="font-size: 48px; opacity: 0.5;">inbox</span>
                <p>No hay solicitudes pendientes.</p>
            </div>`;
        return;
    }

    let html = `
    <table class="data-table" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="border-bottom: 2px solid var(--border-color); text-align:left;">
                <th style="padding:10px;">Usuario</th>
                <th style="padding:10px;">Comunidad</th>
                <th style="padding:10px;">Fecha</th>
                <th style="padding:10px; text-align:right;">Acciones</th>
            </tr>
        </thead>
        <tbody>`;

    requests.forEach(req => {
        const userPic = req.user_picture || `https://ui-avatars.com/api/?name=${encodeURIComponent(req.username)}`;
        const commPic = req.community_picture || `https://ui-avatars.com/api/?name=${encodeURIComponent(req.community_name)}`;
        const date = new Date(req.created_at).toLocaleDateString();

        html += `
        <tr style="border-bottom: 1px solid var(--border-color);">
            <td style="padding:12px 10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="${userPic}" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                    <span style="font-weight:500;">${req.username}</span>
                </div>
            </td>
            <td style="padding:12px 10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <img src="${commPic}" style="width:24px; height:24px; border-radius:4px; object-fit:cover;">
                    <span>${req.community_name}</span>
                </div>
            </td>
            <td style="padding:12px 10px; font-size:0.9rem; color:#666;">${date}</td>
            <td style="padding:12px 10px; text-align:right;">
                <button class="component-icon-button" data-action="accept-request" data-id="${req.id}" 
                        style="color: #2e7d32; background: #e8f5e9; width:32px; height:32px;" title="Aceptar">
                    <span class="material-symbols-rounded" style="font-size:18px;">check</span>
                </button>
                <button class="component-icon-button" data-action="reject-request" data-id="${req.id}" 
                        style="color: #c62828; background: #ffebee; width:32px; height:32px; margin-left:5px;" title="Rechazar">
                    <span class="material-symbols-rounded" style="font-size:18px;">close</span>
                </button>
            </td>
        </tr>`;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;
}

async function resolveRequest(reqId, decision) {
    // Optimista: Eliminar fila visualmente mientras procesa
    const btn = document.querySelector(`button[data-id="${reqId}"]`);
    const row = btn?.closest('tr');
    if (row) row.style.opacity = '0.5';

    try {
        // [CORREGIDO] Usar AdminApi
        const res = await AdminApi.resolveJoinRequest(reqId, decision);

        if (res.success) {
            if (row) row.remove();
            // Comprobar si queda vacía la tabla
            const remainingRows = document.querySelectorAll('#requests-list-container tbody tr');
            if (remainingRows.length === 0) {
                renderRequestsTable([]); 
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