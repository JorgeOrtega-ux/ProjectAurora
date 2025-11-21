// public/assets/js/services/socket-service.js

const WS_URL = 'ws://localhost:8080';

export class SocketService {
    constructor() {
        this.socket = null;
        this.reconnectInterval = 5000;
        this.initWebSocket();
    }

    initWebSocket() {
        // 1. Si no hay usuario (Visitante), salimos en silencio.
        if (!window.USER_ID) {
            return;
        }

        // 2. Validación de token (Error de configuración)
        if (!window.WS_TOKEN) {
            console.warn('websocket_client: error - missing WS_TOKEN');
            return;
        }

        // LOG 1: Connecting con Timestamp
        console.log('websocket_client:', Date.now(), 'connecting...');

        this.socket = new WebSocket(WS_URL);

        this.socket.onopen = () => {
            console.log('websocket_client: connected');
            console.log('websocket_client: status CONNECTED');

            // 1. Generamos el ID aquí
            const requestId = Math.random().toString(16).substring(2, 10) + Math.random().toString(16).substring(2, 6);
            console.log('websocket_client: request id', requestId);

            // 2. Lo enviamos al servidor Python
            this.socket.send(JSON.stringify({
                type: 'auth',
                token: window.WS_TOKEN,
                request_id: requestId // <--- ¡NUEVO CAMPO!
            }));
        };

        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                // Despachamos el evento
                const customEvent = new CustomEvent('socket-message', { detail: data });
                document.dispatchEvent(customEvent);

                // Eliminamos los logs antiguos de "[WS]..." para mantener el estilo limpio que pediste.
                if (data.type === 'error') {
                    console.error('websocket_client: error', data.msg);
                }

            } catch (e) {
                console.error('websocket_client: error processing message', e);
            }
        };

        this.socket.onclose = () => {
            if (window.USER_ID) {
                // Log discreto de desconexión
                console.log('websocket_client: disconnected. retrying...');
                setTimeout(() => this.initWebSocket(), this.reconnectInterval);
            }
        };

        this.socket.onerror = (error) => {
            console.error('websocket_client: connection error');
            this.socket.close();
        };
    }
}