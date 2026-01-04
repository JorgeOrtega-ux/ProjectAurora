/**
 * public/assets/js/core/websocket-manager.js
 * Gestor Singleton para la conexión WebSocket
 */

export const WebSocketManager = {
    socket: null,
    reconnectInterval: 5000, // Reintentar cada 5 segundos si se cae
    shouldReconnect: true,

    init: () => {
        // window.WS_URL se inyecta desde public/index.php
        if (!window.WS_URL) {
            console.error("WebSocketManager: WS_URL no definida en la configuración global.");
            return;
        }
        console.log("WebSocketManager: Inicializando...");
        WebSocketManager.connect();
    },

    connect: () => {
        try {
            console.log(`WebSocketManager: Intentando conectar a ${window.WS_URL}...`);
            WebSocketManager.socket = new WebSocket(window.WS_URL);

            WebSocketManager.bindEvents();
        } catch (error) {
            console.error("WebSocketManager: Error crítico al iniciar conexión.", error);
        }
    },

    bindEvents: () => {
        if (!WebSocketManager.socket) return;

        WebSocketManager.socket.onopen = (event) => {
            console.log("WebSocketManager: ✅ Conexión establecida.");
            // Aquí podrías enviar un mensaje de autenticación inicial si fuera necesario en el futuro
        };

        WebSocketManager.socket.onclose = (event) => {
            console.warn("WebSocketManager: ⚠️ Conexión cerrada.", event.reason);
            
            if (WebSocketManager.shouldReconnect) {
                console.log(`WebSocketManager: Reintentando en ${WebSocketManager.reconnectInterval / 1000}s...`);
                setTimeout(WebSocketManager.connect, WebSocketManager.reconnectInterval);
            }
        };

        WebSocketManager.socket.onerror = (error) => {
            console.error("WebSocketManager: ❌ Error en el socket.", error);
            // El evento onerror suele venir seguido de onclose, donde manejamos la reconexión
        };

        WebSocketManager.socket.onmessage = (event) => {
            // Por el momento solo logueamos los mensajes entrantes (Etapa 1)
            console.log("WebSocketManager: Mensaje recibido:", event.data);
        };
    }
};