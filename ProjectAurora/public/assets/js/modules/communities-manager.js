// public/assets/js/modules/communities-manager.js

import { postJson, setButtonLoading } from '../core/utilities.js';

let myCommunities = [];
let currentCommunityId = null;

// ==========================================
// 1. LÓGICA DE MAIN.PHP (ESTILO WHATSAPP)
// ==========================================

function renderChatListItem(comm) {
    const avatar = comm.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(comm.community_name);
        
    const isActive = (comm.uuid === window.ACTIVE_COMMUNITY_UUID) ? 'active' : '';
    
    // Preview simulado (a futuro vendrá del último mensaje)
    const preview = "Haz clic para ver el chat";
    const time = ""; 

    return `
    <div class="chat-item ${isActive}" data-action="select-chat" data-uuid="${comm.uuid}" data-id="${comm.id}">
        <img src="${avatar}" class="chat-item-avatar" alt="Avatar">
        <div class="chat-item-info">
            <div class="chat-item-top">
                <span class="chat-item-name">${comm.community_name}</span>
                <span class="chat-item-time">${time}</span>
            </div>
            <span class="chat-item-preview">${preview}</span>
        </div>
    </div>`;
}

function updateChatInterface(comm) {
    const mainPanel = document.getElementById('chat-main-panel');
    const placeholder = document.getElementById('chat-placeholder');
    const interfaceDiv = document.getElementById('chat-interface');
    
    // Elementos del Header
    const img = document.getElementById('chat-header-img');
    const title = document.getElementById('chat-header-title');
    const status = document.getElementById('chat-header-status');

    if (comm) {
        // Mostrar interfaz
        if (placeholder) placeholder.classList.add('d-none');
        if (interfaceDiv) interfaceDiv.classList.remove('d-none');
        
        // Llenar datos
        const avatarPath = comm.profile_picture ? 
            (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
            `https://ui-avatars.com/api/?name=${comm.community_name}`;

        if (img) img.src = avatarPath;
        if (title) title.textContent = comm.community_name;
        if (status) status.textContent = `${comm.member_count} miembros`;

        // Ajuste Responsive: Activar clase en contenedor
        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.add('chat-active');

    } else {
        // Mostrar placeholder
        if (placeholder) placeholder.classList.remove('d-none');
        if (interfaceDiv) interfaceDiv.classList.add('d-none');
        
        const layout = document.querySelector('.chat-layout-container');
        if (layout) layout.classList.remove('chat-active');
    }
}

async function selectCommunity(uuid) {
    // 1. Buscar datos en memoria local primero
    let comm = myCommunities.find(c => c.uuid === uuid);
    
    // 2. Si no está (ej. acceso directo URL y lista aún cargando o paginación), pedir a API
    if (!comm) {
        const res = await postJson('api/communities_handler.php', { action: 'get_community_by_uuid', uuid });
        if (res.success) {
            comm = res.community;
        } else {
            console.error("Comunidad no encontrada");
            // Limpiar URL si es inválida
            window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
            return;
        }
    }

    currentCommunityId = comm.id;
    window.ACTIVE_COMMUNITY_UUID = uuid;

    // 3. Actualizar UI de lista (Highlight)
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    const activeItem = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
    if (activeItem) activeItem.classList.add('active');

    // 4. Actualizar Panel Derecho
    updateChatInterface(comm);

    // 5. Actualizar URL sin recargar
    const newUrl = `${window.BASE_PATH}c/${uuid}`;
    if (window.location.pathname !== newUrl) {
        window.history.pushState({ section: 'c/'+uuid }, '', newUrl);
    }
}

function handleMobileBack() {
    const layout = document.querySelector('.chat-layout-container');
    if (layout) layout.classList.remove('chat-active');
    
    document.querySelectorAll('.chat-item').forEach(el => el.classList.remove('active'));
    window.ACTIVE_COMMUNITY_UUID = null;
    
    // Restaurar URL base
    window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
}

// Carga la lista lateral en main.php
async function loadMyCommunities() {
    const container = document.getElementById('my-communities-list');
    if (!container) return; // Si no estamos en main.php, salimos

    // Spinner ya está en el HTML, no lo borramos hasta tener datos
    const res = await postJson('api/communities_handler.php', { action: 'get_my_communities' });
    
    container.innerHTML = ''; // Limpiar spinner

    if (res.success && res.communities.length > 0) {
        myCommunities = res.communities;
        container.innerHTML = res.communities.map(c => renderChatListItem(c)).join('');
        
        // Si hay una comunidad activa en la URL/Variable global, seleccionarla visualmente
        if (window.ACTIVE_COMMUNITY_UUID) {
            selectCommunity(window.ACTIVE_COMMUNITY_UUID);
        }
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; padding:20px;">No te has unido a ninguna comunidad.</p>`;
    }
}

// ==========================================
// 2. LÓGICA DE EXPLORER (TARJETAS)
// ==========================================

function renderCommunityCard(comm, isMyList) {
    const isPrivate = comm.privacy === 'private';
    const privacyText = isPrivate ? 'Privado' : 'Público';
    const memberText = comm.member_count + (comm.member_count === 1 ? ' Miembro' : ' Miembros');
    
    // Botón solo para unirse (explorer) o salir (si fuera necesario en otra vista)
    let buttonHtml = `
        <button class="component-button primary comm-btn-primary" data-action="join-public-community" data-id="${comm.id}">
            Unirse
        </button>
    `;

    const bannerSrc = comm.banner_picture ? comm.banner_picture : 'https://picsum.photos/seed/generic/600/200';
    
    // Ruta corregida para avatar
    const avatarSrc = comm.profile_picture ? 
        (window.BASE_PATH || '/ProjectAurora/') + comm.profile_picture : 
        null;

    const avatarHtml = avatarSrc 
        ? `<img src="${avatarSrc}" class="comm-avatar-img" alt="${comm.community_name}">` 
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
                
                <p class="comm-desc">
                    ${comm.description || 'Sin descripción disponible.'}
                </p>

                <div class="comm-badges">
                    <span class="comm-badge">${memberText}</span>
                    <span class="comm-badge">${privacyText}</span>
                </div>
            </div>
        </div>
    </div>`;
}

// Carga las tarjetas en explorer.php
async function loadPublicCommunities() {
    const container = document.getElementById('public-communities-list');
    if (!container) return; // Si no estamos en explorer.php, salimos

    const res = await postJson('api/communities_handler.php', { action: 'get_public_communities' });
    
    if (res.success && res.communities.length > 0) {
        container.innerHTML = res.communities.map(c => renderCommunityCard(c, false)).join('');
    } else {
        container.innerHTML = `<p style="text-align:center; color:#999; grid-column:1/-1;">No hay comunidades públicas disponibles o ya te has unido a todas.</p>`;
    }
}

// ==========================================
// 3. LÓGICA DE JOIN COMMUNITY (CÓDIGO)
// ==========================================

function initJoinByCode() {
    const input = document.querySelector('[data-input="community-code"]');
    const btn = document.querySelector('[data-action="submit-join-community"]');
    
    if (!input || !btn) return; // Si no estamos en join-community.php, salimos

    // Formateo automático XXXX-XXXX-XXXX
    input.addEventListener('input', (e) => {
        let v = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        if (v.length > 12) v = v.slice(0, 12);
        
        const parts = [];
        if (v.length > 0) parts.push(v.slice(0, 4));
        if (v.length > 4) parts.push(v.slice(4, 8));
        if (v.length > 8) parts.push(v.slice(8, 12));
        
        e.target.value = parts.join('-');
    });

    btn.onclick = async () => {
        if (input.value.length < 14) {
            if(window.alertManager) window.alertManager.showAlert('Código incompleto. Debe ser XXXX-XXXX-XXXX', 'warning');
            return;
        }
        
        setButtonLoading(btn, true);
        
        const res = await postJson('api/communities_handler.php', { 
            action: 'join_by_code', 
            access_code: input.value 
        });

        if (res.success) {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'success');
            // Redirigir a main
            if (window.navigateTo) window.navigateTo('main');
            else window.location.href = (window.BASE_PATH || '/') + 'main';
        } else {
            if (window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false, 'Unirse');
        }
    };
}

// ==========================================
// 4. INICIALIZACIÓN GLOBAL
// ==========================================

function initListeners() {
    // A) Listeners para la lista de chat (Main)
    document.getElementById('my-communities-list')?.addEventListener('click', (e) => {
        const item = e.target.closest('.chat-item');
        if (item) {
            selectCommunity(item.dataset.uuid);
        }
    });

    document.getElementById('btn-back-to-list')?.addEventListener('click', () => {
        handleMobileBack();
    });

    // B) Listeners para Explorer (Unirse a pública)
    document.body.addEventListener('click', async (e) => {
        const joinBtn = e.target.closest('[data-action="join-public-community"]');
        if (joinBtn) {
            const id = joinBtn.dataset.id;
            setButtonLoading(joinBtn, true);
            const res = await postJson('api/communities_handler.php', { action: 'join_public', community_id: id });
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                // Eliminar tarjeta con animación
                const card = joinBtn.closest('.comm-card');
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
                setButtonLoading(joinBtn, false, 'Unirse');
            }
        }
    });
}

export function initCommunitiesManager() {
    // Detectar en qué sección estamos y cargar lo necesario
    loadMyCommunities(); // Para main.php
    loadPublicCommunities(); // Para explorer.php
    initJoinByCode(); // Para join-community.php
    
    // Evitar duplicar listeners globales si el módulo se recarga
    if (!window.communitiesListenersInit) {
        initListeners();
        window.communitiesListenersInit = true;
    }
}