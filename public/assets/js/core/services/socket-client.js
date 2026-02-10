import { ApiService } from './api-service.js';

const SocketClient = {
    socket: null,
    reconnectInterval: 5000,
    reconnectTimer: null,

    get baseUrl() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = 8765; 
        return `${protocol}//${host}:${port}`;
    },
    
    init: () => {
        SocketClient.connect();
    },

    connect: async () => {
        if (SocketClient.socket && (SocketClient.socket.readyState === WebSocket.OPEN || SocketClient.socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        try {
            let urlToConnect = '';
            const wsUrl = SocketClient.baseUrl;

            if (window.IS_LOGGED_IN) {
                const res = await ApiService.post(
                    ApiService.Routes.Auth.GetWsToken, 
                    new FormData(), 
                    { signal: window.PAGE_SIGNAL } 
                );
                
                if (!res.success || !res.ws_token) return;
                urlToConnect = `${wsUrl}?token=${res.ws_token}`;
            } else {
                urlToConnect = `${wsUrl}?type=guest`;
            }

            SocketClient.socket = new WebSocket(urlToConnect);

            SocketClient.socket.onopen = () => {
                if (SocketClient.reconnectTimer) clearTimeout(SocketClient.reconnectTimer);
                document.dispatchEvent(new CustomEvent('socket:connected'));
            };

            SocketClient.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    if (data.type) {
                        document.dispatchEvent(new CustomEvent(`socket:${data.type}`, { detail: data }));
                    }
                } catch (e) {
                }
            };

            SocketClient.socket.onclose = (e) => {
                if (e.code !== 1000 && e.code !== 1001) {
                    SocketClient.reconnectTimer = setTimeout(() => SocketClient.connect(), SocketClient.reconnectInterval);
                }
            };

            SocketClient.socket.onerror = (err) => {
            };

        } catch (e) { 
            if (e.isAborted) return;
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
        }
    }
};

export { SocketClient };