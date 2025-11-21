// public/assets/js/socket-service.js

const WS_URL = 'ws://localhost:8080';

export class SocketService {
    constructor() {
        this.socket = null;
        this.reconnectInterval = 5000;
        this.initWebSocket();
    }

    initWebSocket() {
        // [SEGURIDAD] Verificamos que existan ID y Token antes de conectar
        if (!window.USER_ID || !window.WS_TOKEN) {
            console.warn('[SocketService] Falta USER_ID o WS_TOKEN. No se iniciará el socket.');
            return;
        }

        this.socket = new WebSocket(WS_URL);

        this.socket.onopen = () => {
            console.log('[WS] Conectado. Autenticando...');
            // Enviamos el token para validación
            this.socket.send(JSON.stringify({ 
                type: 'auth', 
                token: window.WS_TOKEN 
            }));
        };

        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                
                // Despachamos el evento para que otros módulos lo escuchen
                const customEvent = new CustomEvent('socket-message', { detail: data });
                document.dispatchEvent(customEvent);

                // Log simple para depuración
                if (data.type === 'connected') {
                    console.log('[WS] Autenticación exitosa.');
                } else if (data.type === 'error') {
                    console.error('[WS] Error del servidor:', data.msg);
                }

            } catch (e) {
                console.error('[WS] Error procesando mensaje:', e);
            }
        };

        this.socket.onclose = () => {
            console.log('[WS] Desconectado. Reintentando en breve...');
            setTimeout(() => this.initWebSocket(), this.reconnectInterval);
        };

        this.socket.onerror = (error) => {
            console.error('[WS] Error de conexión:', error);
            this.socket.close();
        };
    }
}