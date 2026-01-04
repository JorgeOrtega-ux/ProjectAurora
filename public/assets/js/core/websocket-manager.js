/**
 * public/assets/js/core/websocket-manager.js
 * Gestor Singleton para la conexión WebSocket con autenticación por Token
 */

import { ApiService } from './api-service.js';

export const WebSocketManager = {
    socket: null,
    reconnectInterval: 5000,
    shouldReconnect: true,

    init: () => {
        if (!window.WS_URL) {
            console.error("WebSocketManager: WS_URL no definida.");
            return;
        }
        console.log("WebSocketManager: Inicializando...");
        WebSocketManager.connect();
    },

    /**
     * Inicia el proceso de conexión seguro:
     * 1. Solicita un token de un solo uso a la API PHP.
     * 2. Si lo obtiene, abre el socket pasando el token en la URL.
     */
    connect: async () => {
        try {
            console.log("WebSocketManager: Solicitando token de acceso...");
            
            // 1. Obtener Token de la API (AuthService)
            const formData = new FormData();
            formData.append('action', 'get_ws_token');
            
            const res = await ApiService.post('auth-handler.php', formData);

            if (!res.success || !res.token) {
                console.warn("WebSocketManager: No se pudo obtener token de autenticación.", res.message);
                // Si falla la autenticación, quizás no deberíamos reintentar inmediatamente para no saturar
                return; 
            }

            const token = res.token;
            console.log("WebSocketManager: Token recibido. Conectando al socket...");

            // 2. Construir URL con el token como Query Parameter
            // Ejemplo: ws://localhost:8765?token=abc123...
            const wsUrl = new URL(window.WS_URL);
            wsUrl.searchParams.append('token', token);

            WebSocketManager.socket = new WebSocket(wsUrl.toString());
            WebSocketManager.bindEvents();

        } catch (error) {
            console.error("WebSocketManager: Error crítico al iniciar conexión.", error);
        }
    },

    bindEvents: () => {
        if (!WebSocketManager.socket) return;

        WebSocketManager.socket.onopen = (event) => {
            console.log("WebSocketManager: ✅ Conexión establecida y autenticada.");
        };

        WebSocketManager.socket.onclose = (event) => {
            console.warn(`WebSocketManager: ⚠️ Conexión cerrada (Código: ${event.code}).`, event.reason);
            
            // Código 4000-4999 suelen ser cierres lógicos/aplicación. 
            // Si el servidor nos rechaza el token (ej. expirado), cerrará con un código específico.
            if (WebSocketManager.shouldReconnect) {
                console.log(`WebSocketManager: Reintentando en ${WebSocketManager.reconnectInterval / 1000}s...`);
                setTimeout(WebSocketManager.connect, WebSocketManager.reconnectInterval);
            }
        };

        WebSocketManager.socket.onerror = (error) => {
            console.error("WebSocketManager: ❌ Error en el socket.", error);
        };

        WebSocketManager.socket.onmessage = (event) => {
            // Manejo de mensajes entrantes
            try {
                const data = JSON.parse(event.data);
                console.log("WebSocketManager: Mensaje recibido:", data);
                // Aquí podrías despachar eventos al DOM: document.dispatchEvent(...)
            } catch (e) {
                console.log("WebSocketManager: Mensaje crudo recibido:", event.data);
            }
        };
    }
};