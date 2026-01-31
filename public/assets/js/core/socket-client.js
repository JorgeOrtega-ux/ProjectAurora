/**
 * public/assets/js/core/socket-client.js
 */

import { ApiService } from './api-service.js';

export const SocketClient = {
    socket: null,
    reconnectInterval: 5000,
    
    // Detección dinámica del host
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
        // Evitar duplicados
        if (SocketClient.socket && (SocketClient.socket.readyState === WebSocket.OPEN || SocketClient.socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        try {
            let urlToConnect = '';
            const wsUrl = SocketClient.baseUrl;

            // Lógica Híbrida: Usuario Registrado vs Invitado
            if (window.IS_LOGGED_IN) {
                console.log(`Socket: (User) Solicitando ticket para ${wsUrl}...`);
                
                // 1. Pedir Token al PHP (Solo usuarios)
                const res = await ApiService.post(ApiService.Routes.Auth.GetWsToken);

                if (!res.success || !res.ws_token) {
                    console.warn("Socket: No se pudo obtener autorización.", res.message);
                    return;
                }
                urlToConnect = `${wsUrl}?token=${res.ws_token}`;
            
            } else {
                console.log(`Socket: (Guest) Conectando modo invitado a ${wsUrl}...`);
                // Conexión anónima
                urlToConnect = `${wsUrl}?type=guest`;
            }

            // 2. Conectar
            console.log("Socket: Conectando...");
            
            SocketClient.socket = new WebSocket(urlToConnect);

            SocketClient.socket.onopen = () => {
                console.log("Socket: Conectado ✅");
                document.dispatchEvent(new CustomEvent('socket:connected'));
            };

            SocketClient.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    // === NUEVA LÓGICA DE ALERTAS ===
                    if (data.type === 'system_alert') {
                        showSystemAlert(data.message);
                    }
                    else if (data.type === 'system_alert_clear') {
                        const container = document.getElementById('system-alert-container');
                        if (container) container.style.display = 'none';
                        localStorage.removeItem('hidden_alert_id');
                    }
                    
                    // Despachar eventos globales (para el resto de la app)
                    if (data.type) {
                        const customEvent = new CustomEvent(`socket:${data.type}`, { detail: data });
                        document.dispatchEvent(customEvent);
                    }
                } catch (e) {
                    console.error("Socket: Error leyendo mensaje", e);
                }
            };

            SocketClient.socket.onclose = (event) => {
                console.log("Socket: Desconectado ❌", event.reason);
                
                // Reintentar conexión
                setTimeout(() => {
                    console.log("Socket: Reintentando...");
                    SocketClient.connect();
                }, SocketClient.reconnectInterval);
            };

            SocketClient.socket.onerror = (error) => {
                console.error("Socket: Error de conexión.", error);
                SocketClient.socket.close();
            };

        } catch (e) {
            console.error("Socket: Error en flujo de conexión", e);
        }
    },

    send: (type, payload = {}) => {
        if (SocketClient.socket && SocketClient.socket.readyState === WebSocket.OPEN) {
            const message = JSON.stringify({ type, ...payload });
            SocketClient.socket.send(message);
        } else {
            console.warn("Socket: No se puede enviar (Desconectado).");
        }
    }
};

// === Helper para mostrar Alertas ===
function showSystemAlert(alertData) {
    const container = document.getElementById('system-alert-container');
    if (!container) return;

    // Verificar si el usuario la cerró previamente
    const hiddenId = localStorage.getItem('hidden_alert_id');
    if (hiddenId === alertData.id) return;

    const icon = document.getElementById('sys-alert-icon');
    const title = document.getElementById('sys-alert-title');
    const msg = document.getElementById('sys-alert-msg');
    const link = document.getElementById('sys-alert-link');
    const closeBtn = document.getElementById('sys-alert-close');

    if (!icon || !title || !msg) return; 

    // Configurar Estilos según severidad
    let borderColor = '#333';
    let iconName = 'info';
    let iconColor = '#3b82f6'; // Default azul
    
    if (alertData.severity === 'critical') {
        borderColor = '#ef4444'; // Rojo
        iconName = 'report';
        iconColor = '#ef4444';
    } else if (alertData.severity === 'warning') {
        borderColor = '#f59e0b'; // Naranja
        iconName = 'warning';
        iconColor = '#f59e0b';
    } else {
        borderColor = '#3b82f6'; // Azul
        iconName = 'info';
        iconColor = '#3b82f6';
    }

    container.style.borderBottomColor = borderColor;
    icon.textContent = iconName;
    icon.style.color = iconColor;

    // Configurar Texto
    if (alertData.type === 'performance') {
        title.textContent = 'Aviso de Rendimiento';
        msg.textContent = alertData.message;
    } else if (alertData.type === 'maintenance') {
        title.textContent = 'Mantenimiento del Sistema';
        if (alertData.meta && alertData.meta.subtype === 'scheduled') {
            const date = new Date(alertData.meta.start).toLocaleString();
            msg.textContent = `Programado para el ${date} (${alertData.meta.duration} min).`;
        } else {
            msg.textContent = `Emergencia: ${alertData.message}`;
        }
    } else if (alertData.type === 'policy') {
        title.textContent = 'Actualización Legal';
        const docName = (alertData.meta.doc || 'Documento').toUpperCase();
        const dateStr = alertData.meta.status === 'future' ? 'Entra en vigor: ' + alertData.meta.date : 'Actualizado';
        msg.textContent = `${docName} - ${dateStr}`;
    } else {
        // Fallback genérico
        title.textContent = 'Aviso del Sistema';
        msg.textContent = alertData.message;
    }

    // Link
    if (alertData.meta && alertData.meta.link) {
        link.href = alertData.meta.link;
        link.style.display = 'inline';
    } else {
        link.style.display = 'none';
    }

    // Mostrar
    container.style.display = 'flex';

    // Evento Cerrar
    closeBtn.onclick = () => {
        container.style.display = 'none';
        localStorage.setItem('hidden_alert_id', alertData.id);
    };
}