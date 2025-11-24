// public/assets/js/modules/admin-users.js

import { t } from '../core/i18n-manager.js';

const BASE_PATH = window.BASE_PATH || '/ProjectAurora/';
let selectedUserId = null;
let timeUpdateInterval = null;
let isInitialized = false;

/**
 * Inicializa el módulo de administración de usuarios.
 * Configura listeners y presencia en tiempo real.
 */
export function initAdminUsers() {
    if (isInitialized) {
        // Si volvemos a cargar la sección (SPA), reiniciamos estado limpio
        deselectAllUsers();
        initLivePresence();
        startTimeUpdater();
        return;
    }

    initGlobalListeners();
    initLivePresence();
    startTimeUpdater();
    isInitialized = true;
}

function initGlobalListeners() {
    document.body.addEventListener('click', (e) => {
        // Selección de fila
        const row = e.target.closest('[data-action="select-user-row"]');
        if (row) {
            handleRowSelection(e, row);
            return;
        }

        // Paginación
        const pageBtn = e.target.closest('[data-action="paginate-users"]');
        if (pageBtn && !pageBtn.classList.contains('disabled')) {
            e.preventDefault();
            const page = pageBtn.dataset.page;
            const query = pageBtn.dataset.query;
            loadUsersTable(page, query);
            return;
        }

        // Deseleccionar
        const deselectBtn = e.target.closest('[data-action="deselect-users"]');
        if (deselectBtn) {
            e.preventDefault();
            deselectAllUsers();
            return;
        }

        // Clic fuera para deseleccionar
        if (selectedUserId) {
            const clickedRow = e.target.closest('[data-selectable="true"]');
            const clickedToolbar = e.target.closest('#toolbar-selected');
            if (!clickedRow && !clickedToolbar) {
                deselectAllUsers();
            }
        }
    });

    document.body.addEventListener('keydown', (e) => {
        // Búsqueda
        if (e.target.matches('[data-action="admin-search-input"]') && e.key === 'Enter') {
            e.preventDefault();
            loadUsersTable(1, e.target.value);
        }

        // Escape para deseleccionar
        if (e.key === 'Escape' && selectedUserId) {
            deselectAllUsers();
        }
    });
}

// --- LÓGICA DE UI ---

function handleRowSelection(event, clickedRow) {
    if (clickedRow.classList.contains('selected')) {
        deselectAllUsers();
        return;
    }

    const userId = clickedRow.dataset.uid;

    const allRows = document.querySelectorAll('[data-selectable].selected');
    allRows.forEach(r => r.classList.remove('selected'));

    clickedRow.classList.add('selected');
    selectedUserId = userId;

    toggleToolbars(true);
    setupActionButtons(userId);
}

function deselectAllUsers() {
    const allRows = document.querySelectorAll('[data-selectable].selected');
    allRows.forEach(r => r.classList.remove('selected'));
    
    selectedUserId = null;
    toggleToolbars(false);
}

function toggleToolbars(isSelectionActive) {
    const tbDefault = document.getElementById('toolbar-default');
    const tbSelected = document.getElementById('toolbar-selected');

    if (!tbDefault || !tbSelected) return;

    if (isSelectionActive) {
        tbDefault.style.display = 'none';
        tbSelected.style.display = 'flex';
        tbSelected.classList.remove('d-none');
    } else {
        tbSelected.style.display = 'none';
        tbSelected.classList.add('d-none');
        tbDefault.style.display = 'flex';
    }
}

function setupActionButtons(uid) {
    const btnSanctions = document.getElementById('btn-manage-sanctions');
    const btnGeneral = document.getElementById('btn-manage-general');
    const btnRole = document.getElementById('btn-manage-role');
    // [NUEVO] Botón de notificaciones
    const btnNotifs = document.getElementById('btn-manage-notifications');

    // Usamos window.navigateTo asumido de url-manager.js
    if (btnSanctions) btnSanctions.onclick = () => uid && window.navigateTo('admin/user-status?uid=' + uid);
    if (btnGeneral) btnGeneral.onclick = () => uid && window.navigateTo('admin/user-manage?uid=' + uid);
    if (btnRole) btnRole.onclick = () => uid && window.navigateTo('admin/user-role?uid=' + uid);
    // [NUEVO] Listener
    if (btnNotifs) btnNotifs.onclick = () => uid && window.navigateTo('admin/user-notification?uid=' + uid);
}

async function loadUsersTable(page, query) {
    const tbody = document.getElementById('admin-users-table-body');
    const pagination = document.getElementById('admin-users-pagination');
    
    if (tbody) tbody.classList.add('table-loading');

    const fetchUrl = `${BASE_PATH}public/loader.php?section=admin/users&page=${page}&q=${encodeURIComponent(query)}&ajax_partial=1`;

    try {
        const response = await fetch(fetchUrl);
        const data = await response.json();

        if (data.html_rows !== undefined) {
            if (tbody) tbody.innerHTML = data.html_rows;
            if (pagination) pagination.innerHTML = data.html_pagination;
            
            const newUrl = `${BASE_PATH}admin/users?page=${page}` + (query ? `&q=${encodeURIComponent(query)}` : '');
            window.history.pushState({path: newUrl}, '', newUrl);
            
            deselectAllUsers();
            initLivePresence();
        }
    } catch (error) {
        console.error('Error cargando usuarios:', error);
    } finally {
        if (tbody) tbody.classList.remove('table-loading');
    }
}

// --- LÓGICA DE PRESENCIA ---

function initLivePresence() {
    const socket = window.socketService ? window.socketService.socket : null;
    
    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ type: 'get_online_users' }));
    } else {
        if (!window._livePresenceRetry) {
            window._livePresenceRetry = setTimeout(() => {
                window._livePresenceRetry = null;
                initLivePresence();
            }, 1000);
        }
    }

    document.removeEventListener('socket-message', handlePresenceEvents);
    document.addEventListener('socket-message', handlePresenceEvents);
}

function handlePresenceEvents(e) {
    const { type, payload } = e.detail;

    if (type === 'online_users_list') {
        payload.forEach(uid => updateOnlineStatus(uid, true));
    }

    if (type === 'user_status_change') {
        const { user_id, status, timestamp } = payload;
        updateOnlineStatus(user_id, (status === 'online'), timestamp);
    }
}

function updateOnlineStatus(userId, isOnline, offlineTimestamp = null) {
    const cell = document.getElementById(`presence-${userId}`);
    if (!cell) return; 

    const dot = cell.querySelector('.status-indicator-dot');
    const text = cell.querySelector('.status-text');

    if (isOnline) {
        if (dot) { dot.classList.remove('offline'); dot.classList.add('online'); }
        if (text) {
            text.textContent = t('global.active') || 'En línea'; 
            text.style.fontWeight = '700';
            text.style.color = '#2e7d32';
        }
        cell.dataset.online = "true";
    } else {
        if (dot) { dot.classList.remove('online'); dot.classList.add('offline'); }
        cell.dataset.online = "false";
        if (text) {
            text.style.fontWeight = '400';
            text.style.color = '#666';
        }
        if (offlineTimestamp) {
            cell.dataset.timestamp = new Date(offlineTimestamp).getTime();
            if (text) text.textContent = t('global.time.just_now');
        }
    }
}

function startTimeUpdater() {
    if (timeUpdateInterval) clearInterval(timeUpdateInterval);
    
    timeUpdateInterval = setInterval(() => {
        const cells = document.querySelectorAll('.user-presence-cell');
        const now = Date.now();

        cells.forEach(cell => {
            if (cell.dataset.online === "true") return;

            const ts = parseInt(cell.dataset.timestamp);
            const txt = cell.querySelector('.status-text');
            
            if (!ts || ts === 0) {
                if(txt && txt.textContent !== t('global.time.never')) txt.textContent = t('global.time.never');
                return;
            }

            const diffSeconds = Math.floor((now - ts) / 1000);
            let timeString = '';

            if (diffSeconds < 60) timeString = t('global.time.just_now');
            else if (diffSeconds < 3600) timeString = t('global.time.minutes_ago', { count: Math.floor(diffSeconds / 60) });
            else if (diffSeconds < 86400) timeString = t('global.time.hours_ago', { count: Math.floor(diffSeconds / 3600) });
            else timeString = new Date(ts).toLocaleDateString();

            if (txt) txt.textContent = timeString;
        });
    }, 60000);
}