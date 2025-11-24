// public/assets/js/services/socket-service.js

const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
const host = window.location.hostname;
const WS_URL = `${protocol}${host}:8080`;
const reconnectInterval = 5000;
let socket = null;

function connect() {
    if (!window.USER_ID) return;
    
    console.log('websocket_client: connecting...');
    socket = new WebSocket(WS_URL);

    if (window.socketService) {
        window.socketService.socket = socket;
    }

    socket.onopen = () => {
        console.log('websocket_client: connected');
        
        if (window.WS_TOKEN) {
            const requestId = Math.random().toString(16).substring(2, 10);
            socket.send(JSON.stringify({
                type: 'auth',
                token: window.WS_TOKEN,
                request_id: requestId 
            }));
        }
    };

    socket.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            document.dispatchEvent(new CustomEvent('socket-message', { detail: data }));
            
            if (data.type === 'system_status_update') {
                window.location.reload();
            }

        } catch (e) {
            console.error('websocket_client: error processing message', e);
        }
    };

    socket.onclose = () => {
        if (window.USER_ID) {
            setTimeout(connect, reconnectInterval);
        }
    };
}

export function initSocketService() {
    window.socketService = { socket: null }; 
    connect();
}