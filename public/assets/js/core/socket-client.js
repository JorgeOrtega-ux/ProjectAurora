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

    // Elementos internos
    // Asegúrate de que tu HTML tenga un div hijo con clase .system-alert-box
    let alertBox = container.querySelector('.system-alert-box');
    
    // Si no existe el hijo (estructura antigua), lo creamos al vuelo o lo seleccionamos si ya actualizaste el HTML
    if (!alertBox) {
        // Fallback simple por si el HTML no se ha actualizado manualmente
        alertBox = document.createElement('div');
        alertBox.className = 'system-alert-box';
        while (container.firstChild) alertBox.appendChild(container.firstChild);
        container.appendChild(alertBox);
    }

    const icon = document.getElementById('sys-alert-icon');
    const msg = document.getElementById('sys-alert-msg');
    const closeBtn = document.getElementById('sys-alert-close');

    if (!icon || !msg) return; 

    // Reset de clases de color
    alertBox.classList.remove('alert-bg-critical', 'alert-bg-warning', 'alert-bg-info');

    // Configuración Simplificada
    let bgClass = 'alert-bg-info';
    let iconName = 'info';
    
    if (alertData.severity === 'critical') {
        bgClass = 'alert-bg-critical';
        iconName = 'report';
    } else if (alertData.severity === 'warning') {
        bgClass = 'alert-bg-warning';
        iconName = 'warning';
    }

    // Aplicar estilos
    alertBox.classList.add(bgClass);
    icon.textContent = iconName;
    // Ya no cambiamos icon.style.color porque por CSS ahora es blanco fijo

    // Configurar Texto (Una sola línea: Título + Mensaje)
    let fullText = alertData.message;
    
    // Si hay metadatos específicos, construimos una frase corta
    if (alertData.type === 'maintenance' && alertData.meta?.subtype === 'scheduled') {
        const time = new Date(alertData.meta.start).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        fullText = `Mantenimiento programado: ${time} (${alertData.meta.duration} min)`;
    } else if (alertData.type === 'policy') {
        fullText = `Actualización legal: ${alertData.meta.doc || 'Documentos'}`;
    }

    // Insertar texto
    msg.textContent = fullText;
    msg.title = fullText; // Tooltip nativo por si se corta con "..."

    // Mostrar contenedor padre
    container.style.display = 'block'; // Usamos block porque el hijo ya tiene flex

    // Evento Cerrar
    closeBtn.onclick = () => {
        container.style.display = 'none';
        localStorage.setItem('hidden_alert_id', alertData.id);
    };
}