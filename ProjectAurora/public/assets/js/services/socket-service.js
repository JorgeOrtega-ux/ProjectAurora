// public/assets/js/services/socket-service.js

const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
const host = window.location.hostname;
const WS_URL = `${protocol}${host}:8080`;
const reconnectInterval = 5000;
let socket = null;

function connect() {
    if (!window.USER_ID) return;
    
    const timestamp = Date.now();
    console.log(`websocket_client: ${timestamp} connecting...`); // [LOG RESTAURADO]
    
    socket = new WebSocket(WS_URL);

    if (window.socketService) {
        window.socketService.socket = socket;
    }

    socket.onopen = () => {
        console.log('websocket_client: connected');
        console.log('websocket_client: status CONNECTED'); // [LOG RESTAURADO]
        
        if (window.WS_TOKEN) {
            const requestId = Math.random().toString(16).substring(2, 10);
            console.log(`websocket_client: request id ${requestId}`); // [LOG RESTAURADO]

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
            
            // Log extra para ver qué llega (opcional)
            // console.log('websocket_client: message received', data.type); 

            document.dispatchEvent(new CustomEvent('socket-message', { detail: data }));
            
            if (data.type === 'system_status_update') {
                console.log('websocket_client: system status update received, reloading...');
                window.location.reload();
            }

        } catch (e) {
            console.error('websocket_client: error processing message', e);
        }
    };

    socket.onclose = (e) => {
        console.log('websocket_client: disconnected', e.reason); // [LOG RESTAURADO]
        if (window.USER_ID) {
            setTimeout(connect, reconnectInterval);
        }
    };
    
    socket.onerror = (err) => {
        console.error('websocket_client: error', err);
    };
}

export function initSocketService() {
    window.socketService = { socket: null }; 
    connect();
}