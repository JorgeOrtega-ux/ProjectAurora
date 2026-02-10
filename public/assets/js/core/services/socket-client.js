import { ApiService } from './api-service.js';

const SocketClient = {
    socket: null,
    reconnectInterval: 5000,
    reconnectTimer: null,
    requestId: null, // ID único para rastrear esta sesión de conexión específica

    get baseUrl() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = 8765;
        return `${protocol}//${host}:${port}`;
    },

    /**
     * Genera un ID hexadecimal aleatorio (similar al de Canva/Sentry)
     */
    _generateRequestId: () => {
        return Math.random().toString(16).slice(2) + Math.random().toString(16).slice(2);
    },

    /**
     * Sistema de log personalizado para imitar el estilo de depuración profesional
     */
    _log: (message, details = null) => {
        const timestamp = Date.now();
        const prefix = `websocket_client:`;
        
        if (details) {
            console.log(`${prefix} ${timestamp} ${message}`, details);
        } else {
            console.log(`${prefix} ${timestamp} ${message}`);
        }
    },

    init: () => {
        SocketClient.connect();
    },

    connect: async () => {
        // Evitar reconexiones si ya estamos conectados o conectando
        if (SocketClient.socket && (SocketClient.socket.readyState === WebSocket.OPEN || SocketClient.socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        // Generamos un nuevo Request ID para este intento de conexión
        SocketClient.requestId = SocketClient._generateRequestId();
        
        SocketClient._log(`connecting...`);
        SocketClient._log(`request id ${SocketClient.requestId}`);

        try {
            let urlToConnect = '';
            const wsUrl = SocketClient.baseUrl;

            if (window.IS_LOGGED_IN) {
                // Obtener token efímero de Redis
                const res = await ApiService.post(
                    ApiService.Routes.Auth.GetWsToken, 
                    new FormData(), 
                    { signal: window.PAGE_SIGNAL } 
                );
                
                if (!res.success || !res.ws_token) {
                    SocketClient._log(`auth token fetch failed`, res);
                    return;
                }
                urlToConnect = `${wsUrl}?token=${res.ws_token}&req_id=${SocketClient.requestId}`;
            } else {
                urlToConnect = `${wsUrl}?type=guest&req_id=${SocketClient.requestId}`;
            }

            SocketClient.socket = new WebSocket(urlToConnect);

            SocketClient.socket.onopen = () => {
                if (SocketClient.reconnectTimer) clearTimeout(SocketClient.reconnectTimer);
                
                SocketClient._log(`connected`);
                SocketClient._log(`status CONNECTED`);
                
                document.dispatchEvent(new CustomEvent('socket:connected'));
            };

            SocketClient.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    // Log detallado de mensajes entrantes (opcional, útil para depurar)
                    // SocketClient._log(`received event: ${data.type}`, data);

                    if (data.type) {
                        document.dispatchEvent(new CustomEvent(`socket:${data.type}`, { detail: data }));
                    }
                } catch (e) {
                    SocketClient._log(`error parsing message`, e);
                }
            };

            SocketClient.socket.onclose = (e) => {
                SocketClient._log(`closed`, { code: e.code, reason: e.reason, wasClean: e.wasClean });
                SocketClient._log(`status DISCONNECTED`);

                // Códigos 1000 (Normal) y 1001 (Going Away) no suelen requerir reconexión inmediata agresiva,
                // pero aquí reconectamos por seguridad a menos que sea cierre explícito.
                if (e.code !== 1000 && e.code !== 1008) { // 1008 = Policy Violation (Auth fallida)
                    SocketClient._log(`attempting reconnect in ${SocketClient.reconnectInterval}ms...`);
                    SocketClient.reconnectTimer = setTimeout(() => SocketClient.connect(), SocketClient.reconnectInterval);
                }
            };

            SocketClient.socket.onerror = (err) => {
                // WebSocket onerror no da muchos detalles por seguridad del navegador, pero lo registramos.
                SocketClient._log(`error ocurred`, err);
            };

        } catch (e) { 
            if (e.isAborted) return;
            SocketClient._log(`connection setup failed`, e);
            SocketClient.reconnectTimer = setTimeout(() => SocketClient.connect(), SocketClient.reconnectInterval);
        }
    },

    disconnect: () => {
        if (SocketClient.reconnectTimer) clearTimeout(SocketClient.reconnectTimer);
        if (SocketClient.socket) {
            SocketClient._log(`disconnecting manually...`);
            SocketClient.socket.close(1000, "Cierre voluntario por el cliente");
            SocketClient.socket = null;
        }
    },

    send: (type, payload = {}) => {
        if (SocketClient.socket?.readyState === WebSocket.OPEN) {
            const message = JSON.stringify({ type, ...payload });
            SocketClient.socket.send(message);
            // SocketClient._log(`sent message`, { type });
        } else {
            SocketClient._log(`send failed: socket not open`);
        }
    }
};

export { SocketClient };