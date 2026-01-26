/**
 * public/assets/js/core/socket-client.js
 */

import { ApiService } from './api-service.js';

export const SocketClient = {
    socket: null,
    reconnectInterval: 5000,
    // URL base (sin token). Asegúrate de usar tu IP local o dominio.
    baseUrl: 'ws://192.168.8.2:8765', 
    
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

            // Lógica Híbrida: Usuario Registrado vs Invitado
            if (window.IS_LOGGED_IN) {
                console.log("Socket: (User) Solicitando ticket de acceso...");
                
                // 1. Pedir Token al PHP (Solo usuarios)
                const res = await ApiService.post(ApiService.Routes.Auth.GetWsToken);

                if (!res.success || !res.ws_token) {
                    console.warn("Socket: No se pudo obtener autorización.", res.message);
                    return;
                }
                urlToConnect = `${SocketClient.baseUrl}?token=${res.ws_token}`;
            
            } else {
                console.log("Socket: (Guest) Conectando modo invitado...");
                // Conexión anónima
                urlToConnect = `${SocketClient.baseUrl}?type=guest`;
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
                    // Despachar eventos globales
                    if (data.type) {
                        const customEvent = new CustomEvent(`socket:${data.type}`, { detail: data });
                        document.dispatchEvent(customEvent);
                    }
                } catch (e) {
                    console.error("Socket: Error leyendo mensaje", e);
                }
            };

            SocketClient.socket.onclose = (event) => {
                // Código 1008 es Policy Violation (Fallo auth), no reintentar inmediatamente si es auth error
                console.log("Socket: Desconectado ❌", event.reason);
                
                // Reintentar conexión
                setTimeout(() => {
                    console.log("Socket: Reintentando...");
                    SocketClient.connect();
                }, SocketClient.reconnectInterval);
            };

            SocketClient.socket.onerror = (error) => {
                console.error("Socket: Error de conexión", error);
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