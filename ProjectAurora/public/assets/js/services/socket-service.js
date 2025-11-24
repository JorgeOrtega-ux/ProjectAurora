// public/assets/js/services/socket-service.js

const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
const host = window.location.hostname;
const WS_URL = `${protocol}${host}:8080`;
const reconnectInterval = 5000;
let socket = null;

function connect() {
    // Permitir conexión incluso sin USER_ID si estamos en cola pública
    const isQueuePage = window.location.href.includes('status=server_full');
    
    if (!window.USER_ID && !isQueuePage) return;
    
    console.log('websocket_client:', Date.now(), `connecting to ${WS_URL}...`);
    socket = new WebSocket(WS_URL);

    if (window.socketService) {
        window.socketService.socket = socket;
    }

    socket.onopen = () => {
        console.log('websocket_client: connected');
        
        // 1. Autenticación Normal (si hay sesión)
        if (window.WS_TOKEN) {
            const requestId = Math.random().toString(16).substring(2, 10);
            socket.send(JSON.stringify({
                type: 'auth',
                token: window.WS_TOKEN,
                request_id: requestId 
            }));
        }

        // 2. Unirse a la Cola (si estamos en pantalla de servidor lleno)
        if (isQueuePage) {
            console.log('Uniendo a la cola de espera...');
            socket.send(JSON.stringify({ type: 'join_queue' }));
        }
    };

    socket.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            document.dispatchEvent(new CustomEvent('socket-message', { detail: data }));
            
            // --- EVENTOS DE COLA ---
            if (data.type === 'queue_update') {
                updateQueueUI(data.position);
            }

            if (data.type === 'access_granted') {
                console.log('Acceso concedido! Redirigiendo...');
                // Redirigir a la raíz. El router.php verá que hay espacio y dejará pasar.
                window.location.href = (window.BASE_PATH || '/ProjectAurora/');
            }

            // --- EVENTOS DEL SISTEMA ---
            if (data.type === 'system_status_update') {
                window.location.reload();
            }

        } catch (e) {
            console.error('websocket_client: error processing message', e);
        }
    };

    socket.onclose = () => {
        // Reintentar conexión siempre si estamos en cola o logueados
        if (window.USER_ID || isQueuePage) {
            setTimeout(connect, reconnectInterval);
        }
    };
}

function updateQueueUI(pos) {
    const title = document.getElementById('status-title-text');
    const msg = document.getElementById('status-message-text');
    const icon = document.querySelector('.status-icon');
    const spinner = document.getElementById('queue-spinner');

    if (title) {
        // Texto dinámico con la posición
        title.textContent = `En Cola: Posición ${pos}`;
        title.style.color = '#1976d2';
    }
    if (msg) msg.textContent = 'El servidor está lleno. Entrarás automáticamente cuando se libere un espacio.';
    if (icon) icon.textContent = 'hourglass_top'; // Icono de reloj
    
    // Mostrar spinner de carga si existe
    if (spinner) spinner.style.display = 'block';
}

export function initSocketService() {
    window.socketService = { socket: null }; 
    connect();
}