// public/assets/js/modules/admin-users.js

(function() {
    // Función helper para traducir dentro del IIFE
    function t(key, params = {}) {
        if (window.t) return window.t(key, params);
        return key;
    }

    let selectedUserId = null;
    let timeUpdateInterval = null;

    document.body.addEventListener('click', (e) => {
        const row = e.target.closest('[data-action="select-user-row"]');
        if (row) {
            handleRowSelection(e, row);
            return;
        }

        const pageBtn = e.target.closest('[data-action="paginate-users"]');
        if (pageBtn && !pageBtn.classList.contains('disabled')) {
            e.preventDefault();
            const page = pageBtn.dataset.page;
            const query = pageBtn.dataset.query;
            window.loadUsersTable(page, query);
            return;
        }

        const deselectBtn = e.target.closest('[data-action="deselect-users"]');
        if (deselectBtn) {
            e.preventDefault();
            window.deselectAllUsers();
            return;
        }

        if (selectedUserId) {
            const clickedRow = e.target.closest('[data-selectable="true"]');
            const clickedToolbar = e.target.closest('#toolbar-selected');
            if (!clickedRow && !clickedToolbar) {
                window.deselectAllUsers();
            }
        }
    });

    document.body.addEventListener('keydown', (e) => {
        if (e.target.matches('[data-action="admin-search-input"]') && e.key === 'Enter') {
            e.preventDefault();
            window.loadUsersTable(1, e.target.value);
        }

        if (e.key === 'Escape' && selectedUserId) {
            window.deselectAllUsers();
        }
    });

    function handleRowSelection(event, clickedRow) {
        if (clickedRow.classList.contains('selected')) {
            window.deselectAllUsers();
            return;
        }

        const userId = clickedRow.dataset.uid;

        const allRows = document.querySelectorAll('[data-selectable].selected');
        allRows.forEach(r => r.classList.remove('selected'));

        clickedRow.classList.add('selected');
        selectedUserId = userId;

        toggleToolbars(true);
        setupActionButtons(userId);
    }

    window.deselectAllUsers = function() {
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
            tbSelected.classList.remove('d-none');
        } else {
            tbSelected.style.display = 'none';
            tbSelected.classList.add('d-none');
            tbDefault.style.display = 'flex';
        }
    }

    function setupActionButtons(uid) {
        const btnSanctions = document.getElementById('btn-manage-sanctions');
        const btnGeneral = document.getElementById('btn-manage-general');
        const btnRole = document.getElementById('btn-manage-role');

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

        if (btnRole) {
            btnRole.onclick = () => {
                if(uid) window.navigateTo('admin/user-role?uid=' + uid);
            };
        }
    }

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
                
                window.deselectAllUsers();
                initLivePresence();
            }
        } catch (error) {
            console.error('Error cargando usuarios:', error);
        } finally {
            if (tbody) tbody.classList.remove('table-loading');
        }
    };

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
                text.textContent = t('global.active') || 'En línea'; 
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
                if (text) text.textContent = t('global.time.just_now');
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
                    if(txt && txt.textContent !== t('global.time.never')) txt.textContent = t('global.time.never');
                    return;
                }

                const diffSeconds = Math.floor((now - ts) / 1000);
                let timeString = '';

                if (diffSeconds < 60) {
                    timeString = t('global.time.just_now');
                } else if (diffSeconds < 3600) {
                    timeString = t('global.time.minutes_ago', { count: Math.floor(diffSeconds / 60) });
                } else if (diffSeconds < 86400) { 
                    timeString = t('global.time.hours_ago', { count: Math.floor(diffSeconds / 3600) });
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