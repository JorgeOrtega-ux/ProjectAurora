/**
 * public/assets/js/modules/admin/dashboard-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

let _container = null;

export const DashboardController = {
    init: () => {
        console.log("DashboardController: Inicializado");
        _container = document.querySelector('[data-section="admin-dashboard"]');
        if (!_container) return;

        initEvents();
        loadStats();

        // CORRECCIÓN APLICADA: Inicializar el controlador de alertas
        // Esto activa los listeners del botón y del modal
        AlertController.init();
    }
};

function initEvents() {
    const btnRefresh = _container.querySelector('[data-action="refresh-dashboard"]');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            btnRefresh.classList.add('rotate-anim'); // Clase para animar si quieres
            loadStats().then(() => {
                setTimeout(() => btnRefresh.classList.remove('rotate-anim'), 500);
            });
        });
    }

    // [NUEVO] Escuchar actualizaciones en tiempo real desde Python (Socket)
    // Esto corrige la "Paradoja del Refresh" actualizando la UI instantáneamente
    document.removeEventListener('socket:stats_update', handleRealtimeUpdate);
    document.addEventListener('socket:stats_update', handleRealtimeUpdate);
}

// Función separada para manejar la actualización en vivo
function handleRealtimeUpdate(e) {
    // Si el usuario ya no está en el dashboard (cambió de pestaña), no hacemos nada
    if (!_container || !document.body.contains(_container)) return;

    const stats = e.detail.message;
    console.log("⚡ Dashboard: Actualización en tiempo real recibida", stats);

    if (stats) {
        // Actualizamos solo los contadores de conexión
        // Usamos updateValue que ya existe abajo
        updateValue('online_total', stats.online_total);
        updateValue('online_users', stats.online_users);
        updateValue('online_guests', stats.online_guests);
        
        // Efecto visual opcional: parpadeo suave para indicar cambio
        const card = _container.querySelector('[data-stat="online_total"]')?.closest('.component-stat-card');
        if (card) {
            card.style.transition = 'background-color 0.3s';
            card.style.backgroundColor = 'var(--bg-hover-light)';
            setTimeout(() => { card.style.backgroundColor = ''; }, 300);
        }
    }
}

async function loadStats() {
    try {
        const res = await ApiService.post(ApiService.Routes.Admin.GetDashboardStats);
        if (res.success) {
            renderStats(res.stats);
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        console.error(e);
        Toast.show('Error cargando estadísticas', 'error');
    }
}

function renderStats(stats) {
    // Valores simples
    updateValue('total_users', stats.total_users);
    updateValue('new_users_today', stats.new_users_today);
    updateValue('online_total', stats.online_total);
    updateValue('online_users', stats.online_users);
    updateValue('online_guests', stats.online_guests);
    updateValue('system_activity', stats.system_activity);

    // Tendencias
    updateTrend('total_users', stats.total_users_trend);
    updateTrend('new_users_today', stats.new_users_trend);
}

function updateValue(key, value) {
    // Verificamos que el valor no sea undefined para no borrar datos si faltan en el payload
    if (value === undefined || value === null) return;

    const el = _container.querySelector(`[data-stat="${key}"]`);
    if (el) el.textContent = value;
}

function updateTrend(key, trendData) {
    const badge = _container.querySelector(`[data-trend="${key}"]`);
    if (!badge || !trendData) return;

    const { value, direction, infinite } = trendData;
    
    // Resetear clases
    badge.className = 'component-trend-badge';
    
    let icon = 'remove';
    let label = `${value}%`;

    if (infinite) {
        label = "Nuevo";
        badge.classList.add('positive');
        icon = 'keyboard_double_arrow_up';
    } else if (direction === 'up') {
        badge.classList.add('positive');
        icon = 'trending_up';
        label = `+${value}%`;
    } else if (direction === 'down') {
        badge.classList.add('negative');
        icon = 'trending_down';
        label = `-${value}%`;
    } else {
        badge.classList.add('neutral');
    }

    badge.innerHTML = `<span class="material-symbols-rounded" style="font-size:14px;">${icon}</span> ${label}`;
}