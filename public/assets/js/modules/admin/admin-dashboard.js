// public/assets/js/modules/admin/admin-dashboard.js

import { AdminApi } from '../../services/api-service.js';
import { TimerManager } from '../../core/timer-manager.js'; // [NUEVO IMPORT]

// Variable para guardar la referencia al socket listener y poder borrarlo
let socketListener = null;

export function initAdminDashboard() {
    // 1. Limpieza preventiva (Unmount previo si existiera)
    destroy(); 

    // 2. Iniciar lógica (Mount)
    fetchStats();
    initSocketListener();

    // [MODIFICADO] Usar TimerManager en lugar de setInterval nativo
    // Esto asegura que si el Router llama a TimerManager.clearAll(), este intervalo muere.
    TimerManager.setInterval(fetchStats, 30000);

    // 3. Registrarse para morir cuando la navegación cambie
    document.addEventListener('app:navigation-start', destroy, { once: true });
}

function fetchStats() {
    // Verificar si el elemento DOM aún existe antes de llamar a la API
    // Esto evita errores si el intervalo se ejecuta justo mientras cambiamos de página
    if (!document.getElementById('stat-total-users')) return;

    AdminApi.getDashboardStats().then(res => {
        if (res.success && res.stats) {
            updateUI(res.stats);
        }
    });
}

function updateUI(stats) {
    // Referencias a elementos existentes
    const elTotal = document.getElementById('stat-total-users');
    const elOnline = document.getElementById('stat-online-users');
    const elNew = document.getElementById('stat-new-users');
    const elSessions = document.getElementById('stat-active-sessions');
    
    // Referencias a elementos NUEVOS
    const elCommunities = document.getElementById('stat-total-communities');
    const elMessages = document.getElementById('stat-messages-today');
    const elReports = document.getElementById('stat-pending-reports');
    const elFiles = document.getElementById('stat-total-files');

    // Actualizar texto
    if (elTotal) elTotal.textContent = stats.total_users;
    if (elOnline && elOnline.textContent === '...') elOnline.textContent = stats.online_users;
    if (elNew) elNew.textContent = '+' + stats.new_users_today;
    if (elSessions) elSessions.textContent = stats.active_sessions;

    // Actualizar nuevos elementos
    if (elCommunities) elCommunities.textContent = stats.total_communities;
    if (elMessages) elMessages.textContent = stats.messages_today;
    if (elReports) elReports.textContent = stats.pending_reports;
    if (elFiles) elFiles.textContent = stats.total_files;
}

function initSocketListener() {
    const socket = window.socketService ? window.socketService.socket : null;
    
    // Definimos la función listener con nombre para poder removerla después
    socketListener = (e) => {
        const { type, payload } = e.detail;
        const elOnline = document.getElementById('stat-online-users');

        if (type === 'online_users_list' && elOnline) {
            elOnline.textContent = payload.length;
        }

        if (type === 'user_status_change' && socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({ type: 'get_online_users' }));
        }
    };

    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ type: 'get_online_users' }));
    }

    document.addEventListener('socket-message', socketListener);
}

// [NUEVO] Función de limpieza profesional (Unmount)
function destroy() {
    // Remover listener de sockets
    if (socketListener) {
        document.removeEventListener('socket-message', socketListener);
        socketListener = null;
    }
    
    // Nota: No necesitamos limpiar el intervalo manualmente aquí porque 
    // TimerManager.clearAll() en el Router ya se encargó de eso.
    // Pero si quisiéramos ser explícitos, podríamos guardar el ID y limpiarlo.
    
    console.log("[AdminDashboard] Recursos liberados.");
}