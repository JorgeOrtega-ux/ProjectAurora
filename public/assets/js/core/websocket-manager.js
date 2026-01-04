/**
 * public/assets/js/core/websocket-manager.js
 * Gestor Singleton para la conexión WebSocket con autenticación por Token
 */

import { ApiService } from './api-service.js';

export const WebSocketManager = {
    socket: null,
    reconnectInterval: 5000,
    shouldReconnect: true,
    subscribers: [], // Lista de callbacks suscritos

    init: () => {
        if (!window.WS_URL) {
            console.error("WebSocketManager: WS_URL no definida.");
            return;
        }
        console.log("WebSocketManager: Inicializando...");
        // No conectamos automáticamente en init, esperamos a que el controlador del whiteboard lo pida con el UUID
    },

    /**
     * Inicia el proceso de conexión seguro:
     * 1. Solicita un token de un solo uso a la API PHP.
     * 2. Si lo obtiene, abre el socket pasando el token Y el UUID en la URL.
     */
    connect: async (whiteboardUuid) => {
        if (!whiteboardUuid) {
            console.error("WebSocketManager: UUID de whiteboard requerido para conectar.");
            return;
        }

        try {
            console.log(`WebSocketManager: Solicitando token de acceso para ${whiteboardUuid}...`);
            
            // 1. Obtener Token de la API (AuthService)
            const formData = new FormData();
            formData.append('action', 'get_ws_token');
            
            const res = await ApiService.post('auth-handler.php', formData);

            if (!res.success || !res.token) {
                console.warn("WebSocketManager: No se pudo obtener token de autenticación.", res.message);
                return; 
            }

            const token = res.token;
            console.log("WebSocketManager: Token recibido. Conectando al socket...");

            // 2. Construir URL con token y UUID
            const wsUrl = new URL(window.WS_URL);
            wsUrl.searchParams.append('token', token);
            wsUrl.searchParams.append('uuid', whiteboardUuid);

            WebSocketManager.socket = new WebSocket(wsUrl.toString());
            WebSocketManager.bindEvents(whiteboardUuid); // Pasamos UUID para reconexión si fuera necesario

        } catch (error) {
            console.error("WebSocketManager: Error crítico al iniciar conexión.", error);
        }
    },

    bindEvents: (whiteboardUuid) => {
        if (!WebSocketManager.socket) return;

        WebSocketManager.socket.onopen = (event) => {
            console.log("WebSocketManager: ✅ Conexión establecida y autenticada.");
        };

        WebSocketManager.socket.onclose = (event) => {
            console.warn(`WebSocketManager: ⚠️ Conexión cerrada (Código: ${event.code}).`, event.reason);
            
            if (WebSocketManager.shouldReconnect && event.code !== 1000 && event.code !== 4001 && event.code !== 4003) {
                console.log(`WebSocketManager: Reintentando en ${WebSocketManager.reconnectInterval / 1000}s...`);
                setTimeout(() => WebSocketManager.connect(whiteboardUuid), WebSocketManager.reconnectInterval);
            }
        };

        WebSocketManager.socket.onerror = (error) => {
            console.error("WebSocketManager: ❌ Error en el socket.", error);
        };

        WebSocketManager.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                // Notificar a todos los suscriptores
                WebSocketManager.subscribers.forEach(callback => callback(data));
            } catch (e) {
                console.log("WebSocketManager: Mensaje no-JSON recibido:", event.data);
            }
        };
    },

    /**
     * Envía datos al servidor WebSocket
     */
    send: (data) => {
        if (WebSocketManager.socket && WebSocketManager.socket.readyState === WebSocket.OPEN) {
            WebSocketManager.socket.send(JSON.stringify(data));
        } else {
            console.warn("WebSocketManager: No se puede enviar, socket no conectado.");
        }
    },

    /**
     * Permite a otros módulos escuchar mensajes entrantes
     */
    subscribe: (callback) => {
        if (typeof callback === 'function') {
            WebSocketManager.subscribers.push(callback);
        }
    },

    disconnect: () => {
        WebSocketManager.shouldReconnect = false;
        if (WebSocketManager.socket) {
            WebSocketManager.socket.close();
        }
        WebSocketManager.subscribers = [];
    }
};