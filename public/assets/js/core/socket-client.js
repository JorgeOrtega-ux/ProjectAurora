/**
 * public/assets/js/core/socket-client.js
 */

import { ApiService } from './api-service.js';
import { I18nManager } from './i18n-manager.js';
import { ToastManager } from './toast-manager.js';

export const SocketClient = {
    socket: null,
    reconnectInterval: 5000,
    
    get baseUrl() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = 8765;
        return `${protocol}//${host}:${port}`;
    },
    
    init: () => {
        console.log("SocketClient: Inicializado...");
        SocketClient.connect();
    },

    connect: async () => {
        if (SocketClient.socket && (SocketClient.socket.readyState === WebSocket.OPEN || SocketClient.socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        try {
            let urlToConnect = '';
            const wsUrl = SocketClient.baseUrl;

            if (window.IS_LOGGED_IN) {
                const res = await ApiService.post(ApiService.Routes.Auth.GetWsToken);
                if (!res.success || !res.ws_token) return;
                urlToConnect = `${wsUrl}?token=${res.ws_token}`;
            } else {
                urlToConnect = `${wsUrl}?type=guest`;
            }

            SocketClient.socket = new WebSocket(urlToConnect);

            SocketClient.socket.onopen = () => {
                console.log("Socket: Conectado ✅");
                document.dispatchEvent(new CustomEvent('socket:connected'));
            };

            SocketClient.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    if (data.type === 'system_alert') {
                        showSystemAlert(data.message);
                    }
                    else if (data.type === 'system_alert_clear') {
                        const wrapper = document.querySelector('[data-element="system-alert-wrapper"]');
                        if (wrapper) wrapper.style.display = 'none';
                        localStorage.removeItem('hidden_alert_id');
                    }
                    
                    if (data.type) {
                        document.dispatchEvent(new CustomEvent(`socket:${data.type}`, { detail: data }));
                    }
                } catch (e) {
                    console.error("Socket: Error leyendo mensaje", e);
                }
            };

            SocketClient.socket.onclose = () => {
                setTimeout(() => SocketClient.connect(), SocketClient.reconnectInterval);
            };

        } catch (e) { console.error(e); }
    },

    send: (type, payload = {}) => {
        if (SocketClient.socket?.readyState === WebSocket.OPEN) {
            SocketClient.socket.send(JSON.stringify({ type, ...payload }));
        }
    }
};

// === Helper para mostrar Alertas REFACTORIZADO (Sin IDs) ===
function showSystemAlert(alertData) {
    // 1. Seleccionar el Wrapper Global
    const wrapper = document.querySelector('[data-element="system-alert-wrapper"]');
    if (!wrapper) return;

    const hiddenId = localStorage.getItem('hidden_alert_id');
    if (hiddenId === alertData.id) return;

    // 2. Selectores internos usando Data Attributes
    const box = wrapper.querySelector('.component-system-alert-box');
    const icon = wrapper.querySelector('[data-element="alert-icon"]');
    const msg = wrapper.querySelector('[data-element="alert-message"]');
    const closeBtn = wrapper.querySelector('[data-action="close-system-alert"]');

    if (!box || !icon || !msg) return; 

    // 3. Estilos de Estado
    box.classList.remove('type--critical', 'type--warning', 'type--info');
    let modClass = 'type--info';
    let iconName = 'info';
    
    if (alertData.severity === 'critical') {
        modClass = 'type--critical';
        iconName = 'report';
    } else if (alertData.severity === 'warning') {
        modClass = 'type--warning';
        iconName = 'warning';
    }

    box.classList.add(modClass);
    icon.textContent = iconName;

    // 4. Lógica de Traducción
    const translationKey = alertData.message;
    const meta = alertData.meta || {};
    let params = [];

    if (alertData.type === 'maintenance') {
        if (meta.subtype === 'emergency') {
            params.push(meta.cutoff || '--:--');
        } else {
            const dateStr = meta.start ? new Date(meta.start).toLocaleString([], {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'}) : '--/--';
            params.push(dateStr);
            params.push(meta.duration || '60');
        }
    } 
    else if (alertData.type === 'policy') {
        const docKey = `system_alerts.policy.names.${meta.doc || 'terms'}`;
        const docName = I18nManager.t(docKey); 
        if (meta.update_type === 'future') {
            const dateStr = meta.date ? new Date(meta.date + 'T00:00:00').toLocaleDateString() : '--/--';
            params.push(`<strong>${dateStr}</strong>`);
            params.push(`<strong>${docName}</strong>`);
        } else {
            params.push(`<strong>${docName}</strong>`);
        }
    }

    let fullText = I18nManager.t(translationKey, params);

    if (meta.link) {
        const textVerMas = I18nManager.t('js.core.view_more') || 'Ver más';
        fullText += ` <a href="${meta.link}" target="_blank">${textVerMas}</a>`;
    }

    msg.innerHTML = fullText;
    msg.title = msg.textContent;
    
    wrapper.style.display = 'block';

    if (closeBtn) {
        closeBtn.onclick = () => {
            wrapper.style.display = 'none';
            localStorage.setItem('hidden_alert_id', alertData.id);
        };
    }
}