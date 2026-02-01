/**
 * public/assets/js/core/socket-client.js
 */

import { ApiService } from './api-service.js';
import { I18n } from './i18n-manager.js';

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
        console.log("SocketClient: Inicializando...");
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
                        const container = document.getElementById('system-alert-container');
                        if (container) container.style.display = 'none';
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

// === Helper para mostrar Alertas con TRADUCCIÓN ===
function showSystemAlert(alertData) {
    const container = document.getElementById('system-alert-container');
    if (!container) return;

    const hiddenId = localStorage.getItem('hidden_alert_id');
    if (hiddenId === alertData.id) return;

    let alertBox = container.querySelector('.system-alert-box');
    if (!alertBox) {
        alertBox = document.createElement('div');
        alertBox.className = 'system-alert-box';
        while (container.firstChild) alertBox.appendChild(container.firstChild);
        container.appendChild(alertBox);
    }

    const icon = document.getElementById('sys-alert-icon');
    const msg = document.getElementById('sys-alert-msg');
    const closeBtn = document.getElementById('sys-alert-close');

    if (!icon || !msg) return; 

    // Estilos
    alertBox.classList.remove('alert-bg-critical', 'alert-bg-warning', 'alert-bg-info');
    let bgClass = 'alert-bg-info';
    let iconName = 'info';
    
    if (alertData.severity === 'critical') {
        bgClass = 'alert-bg-critical';
        iconName = 'report';
    } else if (alertData.severity === 'warning') {
        bgClass = 'alert-bg-warning';
        iconName = 'warning';
    }

    alertBox.classList.add(bgClass);
    icon.textContent = iconName;

    // === LÓGICA DE TRADUCCIÓN ===
    const translationKey = alertData.message;
    const meta = alertData.meta || {};
    let params = [];

    // Preparamos los parámetros
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
        const docName = I18n.t(docKey); 

        if (meta.update_type === 'future') {
            const dateStr = meta.date ? new Date(meta.date + 'T00:00:00').toLocaleDateString() : '--/--';
            // Usamos <strong> para resaltar fechas y nombres ya que usaremos innerHTML
            params.push(`<strong>${dateStr}</strong>`);
            params.push(`<strong>${docName}</strong>`);
        } else {
            params.push(`<strong>${docName}</strong>`);
        }
    }

    // 1. Traducción base
    let fullText = I18n.t(translationKey, params);

    // 2. [CORRECCIÓN] Agregar el Link si existe en la metadata
    if (meta.link) {
        const textVerMas = I18n.t('js.core.view_more') || 'Ver más';
        // Agregamos el enlace con estilos inline para asegurar que se vea blanco y subrayado
        fullText += ` <a href="${meta.link}" target="_blank" style="color: inherit; text-decoration: underline; font-weight: bold; margin-left: 6px;">${textVerMas}</a>`;
    }

    // 3. [CORRECCIÓN] Usar innerHTML para que funcionen los enlaces y negritas
    msg.innerHTML = fullText;
    msg.title = msg.textContent; // Tooltip con texto plano
    
    container.style.display = 'block';

    closeBtn.onclick = () => {
        container.style.display = 'none';
        localStorage.setItem('hidden_alert_id', alertData.id);
    };
}