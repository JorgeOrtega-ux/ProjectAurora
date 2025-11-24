// public/assets/js/services/socket-service.js

const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
const host = window.location.hostname;
const WS_URL = `${protocol}${host}:8080`;
const reconnectInterval = 5000;
let socket = null;

function connect() {
    if (!window.USER_ID) return;
    if (!window.WS_TOKEN) {
        console.warn('websocket_client: error - missing WS_TOKEN');
        return;
    }

    console.log('websocket_client:', Date.now(), `connecting to ${WS_URL}...`);
    socket = new WebSocket(WS_URL);

    // Actualizar la referencia global para que otros módulos (como admin-users) puedan acceder
    if (window.socketService) {
        window.socketService.socket = socket;
    }

    socket.onopen = () => {
        console.log('websocket_client: connected');
        const requestId = Math.random().toString(16).substring(2, 10) + Math.random().toString(16).substring(2, 6);
        socket.send(JSON.stringify({
            type: 'auth',
            token: window.WS_TOKEN,
            request_id: requestId 
        }));
    };

    socket.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            document.dispatchEvent(new CustomEvent('socket-message', { detail: data }));
            if (data.type === 'error') {
                console.error('websocket_client: error', data.msg);
            }
        } catch (e) {
            console.error('websocket_client: error processing message', e);
        }
    };

    socket.onclose = () => {
        if (window.USER_ID) {
            console.log('websocket_client: disconnected. retrying...');
            setTimeout(connect, reconnectInterval);
        }
    };

    socket.onerror = (error) => {
        console.error('websocket_client: connection error');
    };
}

export function initSocketService() {
    // Inicializar contenedor global
    window.socketService = { socket: null }; 
    connect();
}