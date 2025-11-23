// public/assets/js/modules/admin-users.js

(function() {
    let selectedUserId = null;
    let timeUpdateInterval = null;

    // --- UI Logic for Selection ---

    window.selectSingleRow = function(event, clickedRow, userId) {
        if (event) event.stopPropagation();

        // Si clicamos el mismo que ya está seleccionado, deseleccionamos (toggle)
        if (clickedRow.classList.contains('selected')) {
            window.deselectAllUsers();
            return;
        }

        // 1. Limpiar selección previa
        // [CORRECCIÓN] Usamos atributo data para encontrar filas seleccionables
        const allRows = document.querySelectorAll('[data-selectable].selected');
        allRows.forEach(r => r.classList.remove('selected'));

        // 2. Seleccionar nuevo
        clickedRow.classList.add('selected');
        selectedUserId = userId;

        // 3. Actualizar Toolbars
        toggleToolbars(true);
        setupActionButtons(userId);
    };

    window.deselectAllUsers = function() {
        // [CORRECCIÓN] Limpieza robusta basada en data attributes
        const allRows = document.querySelectorAll('[data-selectable].selected');
        allRows.forEach(r => r.classList.remove('selected'));
        
        selectedUserId = null;
        toggleToolbars(false);
    };

    function toggleToolbars(isSelectionActive) {
        const tbDefault = document.getElementById('toolbar-default');
        const tbSelected = document.getElementById('toolbar-selected');

        if (!tbDefault || !tbSelected) return;

        if (isSelectionActive) {
            tbDefault.style.display = 'none';
            tbSelected.style.display = 'flex';
        } else {
            tbSelected.style.display = 'none';
            tbDefault.style.display = 'flex';
        }
    }

    function setupActionButtons(uid) {
        const btnSanctions = document.getElementById('btn-manage-sanctions');
        const btnGeneral = document.getElementById('btn-manage-general');

        if (btnSanctions) {
            btnSanctions.onclick = () => {
                if(uid) window.navigateTo('admin/user-status?uid=' + uid);
            };
        }
        
        if (btnGeneral) {
            btnGeneral.onclick = () => {
                if(uid) window.navigateTo('admin/user-manage?uid=' + uid);
            };
        }
    }

    // --- Global Listeners (Deselection) ---

    // 1. Clic fuera para deseleccionar
    document.addEventListener('click', (e) => {
        // Solo actuar si hay algo seleccionado
        if (!selectedUserId) return;

        // [CORRECCIÓN] El clic debe ser en un elemento con data-selectable="true"
        const clickedRow = e.target.closest('[data-selectable="true"]');
        const clickedToolbar = e.target.closest('#toolbar-selected');

        if (!clickedRow && !clickedToolbar) {
            window.deselectAllUsers();
        }
    });

    // 2. Tecla ESC para deseleccionar
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && selectedUserId) {
            window.deselectAllUsers();
        }
    });


    // --- Data Loading & Pagination ---

    window.loadUsersTable = async function(page, query) {
        const tbody = document.getElementById('admin-users-table-body');
        const pagination = document.getElementById('admin-users-pagination');
        
        if (tbody) tbody.classList.add('table-loading');

        const basePath = window.BASE_PATH || '/ProjectAurora/';
        const fetchUrl = `${basePath}public/loader.php?section=admin/users&page=${page}&q=${encodeURIComponent(query)}&ajax_partial=1`;

        try {
            const response = await fetch(fetchUrl);
            const data = await response.json();

            if (data.html_rows !== undefined) {
                if (tbody) tbody.innerHTML = data.html_rows;
                if (pagination) pagination.innerHTML = data.html_pagination;
                
                const newUrl = `${basePath}admin/users?page=${page}` + (query ? `&q=${encodeURIComponent(query)}` : '');
                window.history.pushState({path: newUrl}, '', newUrl);
                
                // Resetear selección al cambiar de página
                window.deselectAllUsers();
                
                // IMPORTANTE: Reinicializar el estado en vivo para las nuevas filas cargadas
                initLivePresence();
            }
        } catch (error) {
            console.error('Error cargando usuarios:', error);
        } finally {
            if (tbody) tbody.classList.remove('table-loading');
        }
    };

    // --- AUTO-INICIO AL CARGAR EL SCRIPT (Socket Presence) ---
    
    function initLivePresence() {
        const socket = window.socketService ? window.socketService.socket : null;
        
        if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({ type: 'get_online_users' }));
        } else {
            if (!window._livePresenceRetry) {
                window._livePresenceRetry = setTimeout(() => {
                    window._livePresenceRetry = null;
                    initLivePresence();
                }, 1000);
            }
        }

        document.removeEventListener('socket-message', handlePresenceEvents);
        document.addEventListener('socket-message', handlePresenceEvents);
    }

    function handlePresenceEvents(e) {
        const { type, payload } = e.detail;

        if (type === 'online_users_list') {
            const onlineIds = payload;
            onlineIds.forEach(uid => updateOnlineStatus(uid, true));
        }

        if (type === 'user_status_change') {
            const { user_id, status, timestamp } = payload;
            const isOnline = (status === 'online');
            updateOnlineStatus(user_id, isOnline, timestamp);
        }
    }

    function updateOnlineStatus(userId, isOnline, offlineTimestamp = null) {
        const cell = document.getElementById(`presence-${userId}`);
        if (!cell) return; 

        const dot = cell.querySelector('.status-indicator-dot');
        const text = cell.querySelector('.status-text');

        if (isOnline) {
            if (dot) {
                dot.classList.remove('offline');
                dot.classList.add('online');
            }
            if (text) {
                text.textContent = 'En línea';
                text.style.fontWeight = '700';
                text.style.color = '#2e7d32';
            }
            cell.dataset.online = "true";
        } else {
            if (dot) {
                dot.classList.remove('online');
                dot.classList.add('offline');
            }
            
            cell.dataset.online = "false";
            if (text) {
                text.style.fontWeight = '400';
                text.style.color = '#666';
            }
            
            if (offlineTimestamp) {
                const ts = new Date(offlineTimestamp).getTime();
                cell.dataset.timestamp = ts;
                if (text) text.textContent = 'Hace un momento';
            }
        }
    }

    function startTimeUpdater() {
        if (timeUpdateInterval) clearInterval(timeUpdateInterval);
        
        timeUpdateInterval = setInterval(() => {
            const cells = document.querySelectorAll('.user-presence-cell');
            const now = Date.now();

            cells.forEach(cell => {
                if (cell.dataset.online === "true") return;

                const ts = parseInt(cell.dataset.timestamp);
                const txt = cell.querySelector('.status-text');
                
                if (!ts || ts === 0) {
                    if(txt && txt.textContent !== 'Nunca') txt.textContent = 'Nunca';
                    return;
                }

                const diffSeconds = Math.floor((now - ts) / 1000);
                let timeString = '';

                if (diffSeconds < 60) {
                    timeString = 'Hace un momento';
                } else if (diffSeconds < 3600) {
                    timeString = `Hace ${Math.floor(diffSeconds / 60)} min`;
                } else if (diffSeconds < 86400) { 
                    timeString = `Hace ${Math.floor(diffSeconds / 3600)} h`;
                } else {
                    const date = new Date(ts);
                    timeString = date.toLocaleDateString();
                }

                if (txt) txt.textContent = timeString;
            });
        }, 60000);
    }

    initLivePresence();
    startTimeUpdater();

})();