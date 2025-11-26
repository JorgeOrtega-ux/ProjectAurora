// public/assets/js/modules/banner-manager.js

import { t } from '../core/i18n-manager.js';
import { postJson } from '../core/utilities.js';

const STORAGE_PREFIX = 'aurora_dismissed_alert_';

export function initBannerManager() {
    // 1. Chequeo inicial al cargar
    checkInitialStatus();

    // 2. Escuchar Socket
    document.addEventListener('socket-message', (e) => {
        const { type, payload } = e.detail;
        if (type === 'system_alert_update') {
            if (payload.status === 'active') {
                renderBanner(payload.type, payload.instance_id);
            } else {
                removeBanner();
            }
        }
    });
}

async function checkInitialStatus() {
    // Consultamos el estado actual sin molestar al socket si no es necesario
    const res = await postJson('api/admin_handler.php', { action: 'get_alert_status' });
    if (res.success && res.active_alert) {
        renderBanner(res.active_alert.type, res.active_alert.instance_id);
    }
}

function renderBanner(type, instanceId) {
    // Verificar si el usuario ya cerró ESTA instancia específica
    if (localStorage.getItem(STORAGE_PREFIX + instanceId)) {
        return;
    }

    // Si ya existe uno, actualizarlo
    let existingBanner = document.getElementById('global-system-banner');
    if (existingBanner) {
        existingBanner.remove();
    }

    const banner = document.createElement('div');
    banner.id = 'global-system-banner';
    banner.className = `system-banner banner-${type}`;
    
    // Mapeo de iconos/colores básicos (aunque CSS manejará colores)
    const icons = {
        'maintenance_warning': 'engineering',
        'high_traffic': 'dns',
        'critical_issue': 'report',
        'update_info': 'info'
    };

    const icon = icons[type] || 'info';
    const text = t(`admin.alerts.templates.${type}.text`);

    banner.innerHTML = `
        <div class="banner-content">
            <span class="material-symbols-rounded banner-icon">${icon}</span>
            <span class="banner-text">${text}</span>
        </div>
        <button class="banner-close" aria-label="Cerrar">
            <span class="material-symbols-rounded">close</span>
        </button>
    `;

    // Insertar al principio del body o antes del header
    document.body.prepend(banner);

    // Lógica de cierre
    banner.querySelector('.banner-close').addEventListener('click', () => {
        localStorage.setItem(STORAGE_PREFIX + instanceId, 'true');
        banner.classList.add('fade-out-up');
        setTimeout(() => banner.remove(), 300);
    });
}

function removeBanner() {
    const banner = document.getElementById('global-system-banner');
    if (banner) {
        banner.classList.add('fade-out-up');
        setTimeout(() => banner.remove(), 300);
    }
}