/**
 * public/assets/js/modules/admin/dashboard-controller.js
 * Versión Refactorizada: Arquitectura Signal & Interceptors
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';

let _container = null;

export const DashboardController = {
    init: () => {
        console.log("DashboardController: Inicializado");
        _container = document.querySelector('[data-section="admin-dashboard"]');
        if (!_container) return;

        initEvents();
        loadStats();
    }
};

function initEvents() {
    const btnRefresh = _container.querySelector('[data-action="refresh-dashboard"]');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            btnRefresh.classList.add('rotate-anim'); 
            loadStats().then(() => {
                setTimeout(() => btnRefresh.classList.remove('rotate-anim'), 500);
            });
        });
    }

    document.removeEventListener('socket:stats_update', handleRealtimeUpdate);
    document.addEventListener('socket:stats_update', handleRealtimeUpdate);
}

function handleRealtimeUpdate(e) {
    if (!_container || !document.body.contains(_container)) return;

    const stats = e.detail.message;
    
    if (stats) {
        updateValue('online_total', stats.online_total);
        updateValue('online_users', stats.online_users);
        updateValue('online_guests', stats.online_guests);
        
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
        // Signal added
        const res = await ApiService.post(
            ApiService.Routes.Admin.GetDashboardStats, 
            new FormData(), 
            { signal: window.PAGE_SIGNAL }
        );
        
        if (res.success) {
            renderStats(res.stats);
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        if (e.isAborted) return;
        console.error(e);
        ToastManager.show(I18nManager.t('admin.dashboard.load_error') || 'Error cargando estadísticas', 'error');
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
    if (value === undefined || value === null) return;

    const el = _container.querySelector(`[data-stat="${key}"]`);
    if (el) el.textContent = value;
}

function updateTrend(key, trendData) {
    const badge = _container.querySelector(`[data-trend="${key}"]`);
    if (!badge || !trendData) return;

    const { value, direction, infinite } = trendData;
    
    badge.className = 'component-trend-badge';
    
    let icon = 'remove';
    let label = `${value}%`;

    if (infinite) {
        label = I18nManager.t('admin.dashboard.trend_new') || "Nuevo";
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