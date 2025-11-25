// public/assets/js/modules/admin-dashboard.js

import { t } from '../core/i18n-manager.js';

const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
let isInitialized = false;
let refreshInterval = null;

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

async function fetchStats() {
    try {
        const res = await fetch(API_ADMIN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ action: 'get_dashboard_stats' })
        });
        const data = await res.json();

        if (data.success && data.stats) {
            updateUI(data.stats);
        }
    } catch (e) {
        console.error("Error fetching stats:", e);
    }
}

function updateUI(stats) {
    const elTotal = document.getElementById('stat-total-users');
    const elOnline = document.getElementById('stat-online-users');
    const elNew = document.getElementById('stat-new-users');
    const elSessions = document.getElementById('stat-active-sessions');

    if (elTotal) elTotal.textContent = stats.total_users;
    // Solo actualizamos online desde DB si no tenemos datos de socket aun
    if (elOnline && elOnline.textContent === '...') elOnline.textContent = stats.online_users;
    if (elNew) elNew.textContent = '+' + stats.new_users_today;
    if (elSessions) elSessions.textContent = stats.active_sessions;
}

// Escucha en tiempo real del socket
function initSocketListener() {
    const socket = window.socketService ? window.socketService.socket : null;
    
    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ type: 'get_online_users' }));
    }

    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        const elOnline = document.getElementById('stat-online-users');

        if (type === 'online_users_list' && elOnline) {
            elOnline.textContent = payload.length;
        }

        // Si cambia el estado de alguien, pedimos la lista completa para sincronizar
        if (type === 'user_status_change' && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({ type: 'get_online_users' }));
        }
    });
}

export function initAdminDashboard() {
    if (isInitialized) return;
    
    fetchStats();
    initSocketListener();

    // Refresco automático de estadísticas base (BD) cada 30s
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(fetchStats, 30000);

    isInitialized = true;
}