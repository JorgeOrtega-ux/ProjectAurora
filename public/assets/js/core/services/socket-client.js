/**
 * public/assets/js/core/services/socket-client.js
 * Versión Final: Arquitectura Signal, Sin UI Acoplada
 */

import { ApiService } from './api-service.js';

export const SocketClient = {
    socket: null,
    reconnectInterval: 5000,
    reconnectTimer: null,

    get baseUrl() {
        // Asegúrate que este puerto (8765) coincida con tu server.py
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
        // Evitar reconexiones si ya está abierto o conectando
        if (SocketClient.socket && (SocketClient.socket.readyState === WebSocket.OPEN || SocketClient.socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        try {
            let urlToConnect = '';
            const wsUrl = SocketClient.baseUrl;

            if (window.IS_LOGGED_IN) {
                // [CRÍTICO] Recuperamos el uso de signal para evitar errores en navegación rápida
                const res = await ApiService.post(
                    ApiService.Routes.Auth.GetWsToken, 
                    new FormData(), 
                    { signal: window.PAGE_SIGNAL } // <--- ESTO FALTABA
                );
                
                if (!res.success || !res.ws_token) return;
                urlToConnect = `${wsUrl}?token=${res.ws_token}`;
            } else {
                urlToConnect = `${wsUrl}?type=guest`;
            }

            SocketClient.socket = new WebSocket(urlToConnect);

            SocketClient.socket.onopen = () => {
                console.log("Socket: Conectado ✅");
                if (SocketClient.reconnectTimer) clearTimeout(SocketClient.reconnectTimer);
                document.dispatchEvent(new CustomEvent('socket:connected'));
            };

            SocketClient.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    // Solo despachamos eventos. La UI (MainController) se encargará de mostrar la alerta.
                    if (data.type) {
                        document.dispatchEvent(new CustomEvent(`socket:${data.type}`, { detail: data }));
                    }
                } catch (e) {
                    console.error("Socket: Error parseando mensaje", e);
                }
            };

            SocketClient.socket.onclose = (e) => {
                console.log("Socket: Desconectado ❌", e.code);
                // Evitamos bucles infinitos de reconexión si la página se está cerrando
                if (e.code !== 1000 && e.code !== 1001) {
                    SocketClient.reconnectTimer = setTimeout(() => SocketClient.connect(), SocketClient.reconnectInterval);
                }
            };

            SocketClient.socket.onerror = (err) => {
                console.error("Socket Error:", err);
                // El onclose manejará la reconexión
            };

        } catch (e) { 
            // Si el error es por abortar la petición (navegación), lo ignoramos
            if (e.isAborted) return;
            console.error("Socket: Error de conexión inicial", e);
            // Reintentar en caso de fallo de API
            SocketClient.reconnectTimer = setTimeout(() => SocketClient.connect(), SocketClient.reconnectInterval);
        }
    },

    disconnect: () => {
        if (SocketClient.reconnectTimer) clearTimeout(SocketClient.reconnectTimer);
        if (SocketClient.socket) {
            SocketClient.socket.close(1000, "Cierre voluntario");
            SocketClient.socket = null;
        }
    },

    send: (type, payload = {}) => {
        if (SocketClient.socket?.readyState === WebSocket.OPEN) {
            SocketClient.socket.send(JSON.stringify({ type, ...payload }));
        } else {
            console.warn("Socket: Intentando enviar mensaje sin conexión.");
        }
    }
};