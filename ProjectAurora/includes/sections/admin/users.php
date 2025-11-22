<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SEGURIDAD
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

// 2. CONFIGURACIÓN Y DATOS
$limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$whereClause = "";
$params = [];

if (!empty($q)) {
    $whereClause = "WHERE u.username LIKE ? OR u.email LIKE ?";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// --- HELPERS ---
function getStatusClass($status) {
    return match ($status) {
        'active' => 'status-active',
        'suspended' => 'status-suspended',
        'deleted' => 'status-deleted',
        default => ''
    };
}

// Función inicial para renderizado servidor (fallback si no hay JS o datos socket)
function formatTimeAgo($datetime) {
    if (!$datetime) return 'Nunca';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    // Lógica básica PHP para render inicial
    if ($diff < 60) return 'Hace un momento';
    if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' h';
    return date('d/m/Y', $time);
}

// Función para renderizar filas (HTML)
function renderUserRows($users) {
    ob_start();
    if (count($users) > 0):
        foreach ($users as $u): 
            $avatarUrl = !empty($u['avatar']) ? '/ProjectAurora/' . $u['avatar'] : null;
            $statusClass = getStatusClass($u['account_status']);
            $is2FA = ((int)$u['is_2fa_enabled'] === 1);
            
            // Obtenemos timestamp SQL
            $rawTime = $u['last_seen']; 
            $initialText = formatTimeAgo($rawTime);
            $userId = $u['id'];
            
            // Preparamos el timestamp para JS (milisegundos)
            $jsTimestamp = $rawTime ? strtotime($rawTime) * 1000 : 0;
        ?>
        <tr class="admin-row-selectable" onclick="selectSingleRow(this, '<?php echo $userId; ?>')">
            <td style="padding-left: 20px; color: #888;"><?php echo $userId; ?></td>
            <td>
                <div style="display:flex; align-items:center; gap:10px;">
                    <?php if ($avatarUrl): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div class="user-avatar-small" style="background-color: #e0e0e0; width: 32px; height: 32px; border-radius: 50%; display:flex; align-items:center; justify-content:center;">
                            <span class="material-symbols-rounded" style="font-size:18px; color:#666;">person</span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex; flex-direction:column;">
                        <span style="font-weight: 600; font-size:14px; color: inherit;"><?php echo htmlspecialchars($u['username']); ?></span>
                        <span style="font-size:11px; color:#999;">Creado: <?php echo date('d/m/Y', strtotime($u['created_at'])); ?></span>
                    </div>
                </div>
            </td>
            <td style="font-size:13px;"><?php echo htmlspecialchars($u['email']); ?></td>
            <td>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo ucfirst($u['account_status']); ?>
                </span>
            </td>
            <td>
                <span style="font-size:12px; font-weight:500; background:#f5f5f5; padding:2px 6px; border-radius:4px; border:1px solid #e0e0e0;">
                    <?php echo ucfirst($u['role']); ?>
                </span>
            </td>
            <td style="text-align:center;">
                <?php if ($is2FA): ?>
                    <span class="material-symbols-rounded" style="color:#2e7d32; font-size:18px;" title="Protegido">shield_lock</span>
                <?php else: ?>
                    <span class="material-symbols-rounded" style="color:#bdbdbd; font-size:18px;" title="No protegido">no_encryption</span>
                <?php endif; ?>
            </td>
            
            <td class="user-presence-cell" 
                id="presence-<?php echo $userId; ?>" 
                data-uid="<?php echo $userId; ?>" 
                data-timestamp="<?php echo $jsTimestamp; ?>">
                
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div class="status-indicator-dot offline"></div>
                    <span class="status-text" style="color:#666; font-size:13px;">
                        <?php echo $initialText; ?>
                    </span>
                </div>
            </td>

        </tr>
        <?php endforeach;
    else: ?>
        <tr>
            <td colspan="7" style="text-align:center; padding: 40px; color: #888;">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #e0e0e0; margin-bottom: 10px;">person_off</span>
                <p>No se encontraron usuarios.</p>
            </td>
        </tr>
    <?php endif;
    return ob_get_clean();
}

// Función para renderizar paginación (HTML)
function renderPagination($page, $totalPages, $q) {
    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);
    $qEncoded = htmlspecialchars($q, ENT_QUOTES);
    
    ob_start();
    ?>
    <button class="pagination-btn" 
            onclick="loadUsersTable(<?php echo $prevPage; ?>, '<?php echo $qEncoded; ?>')"
            <?php echo ($page <= 1) ? 'disabled style="opacity:0.3; pointer-events:none;"' : ''; ?>>
        <span class="material-symbols-rounded">chevron_left</span>
    </button>
    
    <span class="pagination-number"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
    
    <button class="pagination-btn" 
            onclick="loadUsersTable(<?php echo $nextPage; ?>, '<?php echo $qEncoded; ?>')"
            <?php echo ($page >= $totalPages) ? 'disabled style="opacity:0.3; pointer-events:none;"' : ''; ?>>
        <span class="material-symbols-rounded">chevron_right</span>
    </button>
    <?php
    return ob_get_clean();
}

// --- OBTENCIÓN DE DATOS ---
try {
    $sqlCount = "SELECT COUNT(*) FROM users u $whereClause";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalUsers = $stmtCount->fetchColumn();
    
    $totalPages = ceil($totalUsers / $limit);
    if ($totalPages < 1) $totalPages = 1;
    
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $sqlUsers = "SELECT u.id, u.username, u.email, u.avatar, u.role, u.account_status, u.created_at, u.is_2fa_enabled,
                 (SELECT MAX(last_activity) FROM user_sessions WHERE user_id = u.id) as last_seen
                 FROM users u 
                 $whereClause 
                 ORDER BY u.id DESC 
                 LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sqlUsers);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = [];
    $totalUsers = 0;
    $totalPages = 1;
}

// --- RESPUESTA AJAX (JSON) ---
if (isset($_GET['ajax_partial']) && $_GET['ajax_partial'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'html_rows' => renderUserRows($users),
        'html_pagination' => renderPagination($page, $totalPages, $q)
    ]);
    exit;
}
?>

<style>
    .admin-row-selectable {
        cursor: pointer;
        transition: background-color 0.15s ease;
        border-left: 4px solid transparent;
    }
    .admin-row-selectable:hover {
        background-color: #fafafa;
    }
    .admin-row-selectable.selected {
        background-color: #F5F5FA !important;
        border-left-color: #000000;
    }
    .admin-row-selectable.selected td {
        color: #000; 
    }
    .table-loading {
        opacity: 0.5;
        pointer-events: none;
        transition: opacity 0.2s;
    }
    
    /* --- ESTILOS DEL INDICADOR DE ESTADO --- */
    .status-indicator-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #ccc; /* Offline por defecto */
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    
    .status-indicator-dot.online {
        background-color: #2e7d32; /* Verde Online */
        box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.15);
        transform: scale(1.1);
    }
    
    .status-indicator-dot.offline {
        background-color: #bdbdbd;
    }
</style>

<div class="section-content active" data-section="admin/users">
    <div class="section-center-wrapper" style="flex-direction: column; justify-content: flex-start; padding-top: 20px; width: 98%; max-width: none; margin: 0 auto;">
        
        <div class="toolbar-stack">
            <div class="content-toolbar" id="default-toolbar">
                <div style="display: flex; gap: 8px;">
                    <button class="toolbar-action-btn" data-action="toggle-admin-user-search" data-tooltip="Buscar">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                    <button class="toolbar-action-btn" data-tooltip="Filtrar">
                        <span class="material-symbols-rounded">filter_list</span>
                    </button>
                    <button class="toolbar-action-btn" data-tooltip="Nuevo Usuario">
                        <span class="material-symbols-rounded">person_add</span>
                    </button>
                </div>
                
                <div style="flex: 1;"></div>

                <div class="toolbar-pagination" id="admin-users-pagination">
                    <?php echo renderPagination($page, $totalPages, $q); ?>
                </div>
            </div>

            <div class="content-toolbar search-toolbar-panel disabled" id="admin-users-search-bar">
                <div class="search-container" style="width: 100%; max-width: 100%;">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" 
                           id="admin-users-search-input"
                           class="search-input" 
                           placeholder="Buscar por nombre, correo o ID (Presiona Enter)..." 
                           value="<?php echo htmlspecialchars($q); ?>"
                           onkeydown="if(event.key === 'Enter') loadUsersTable(1, this.value)">
                </div>
            </div>
        </div>

        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px; padding-left: 20px;">ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th style="width: 100px;">Estado</th>
                        <th style="width: 100px;">Rol</th>
                        <th style="width: 80px; text-align:center;">2FA</th>
                        <th style="width: 160px;">Estado / Última vez</th>
                    </tr>
                </thead>
                <tbody id="admin-users-table-body">
                    <?php echo renderUserRows($users); ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
    let selectedUserId = null;
    let timeUpdateInterval = null;

    // --- AUTO-INICIO AL CARGAR LA VISTA ---
    (function() {
        // Iniciamos la conexión en vivo y el cronómetro
        initLivePresence();
        startTimeUpdater();
    })();

    function initLivePresence() {
        // 1. Obtenemos el servicio socket global
        const socket = window.socketService ? window.socketService.socket : null;
        
        // Si está conectado, pedimos la lista inicial
        if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({ type: 'get_online_users' }));
        } else {
            // Si no está listo, reintentamos en 1 segundo
            setTimeout(initLivePresence, 1000);
        }

        // 2. Limpiamos listener previo para evitar duplicados si se recarga la tabla
        document.removeEventListener('socket-message', handlePresenceEvents);
        // 3. Añadimos el listener para recibir eventos
        document.addEventListener('socket-message', handlePresenceEvents);
    }

    function handlePresenceEvents(e) {
        const { type, payload } = e.detail;

        // A. Lista inicial de usuarios online (respuesta a get_online_users)
        if (type === 'online_users_list') {
            const onlineIds = payload; // Array de IDs ["1", "5", "12"]
            onlineIds.forEach(uid => updateOnlineStatus(uid, true));
        }

        // B. Cambio de estado individual (conexión/desconexión en tiempo real)
        if (type === 'user_status_change') {
            const { user_id, status, timestamp } = payload;
            const isOnline = (status === 'online');
            updateOnlineStatus(user_id, isOnline, timestamp);
        }
    }

    function updateOnlineStatus(userId, isOnline, offlineTimestamp = null) {
        const cell = document.getElementById(`presence-${userId}`);
        if (!cell) return; // El usuario no está visible en esta página de la tabla

        const dot = cell.querySelector('.status-indicator-dot');
        const text = cell.querySelector('.status-text');

        if (isOnline) {
            // ESTADO: ONLINE
            dot.classList.remove('offline');
            dot.classList.add('online');
            
            text.textContent = 'En línea';
            text.style.fontWeight = '700';
            text.style.color = '#2e7d32'; // Verde fuerte
            
            // Marcador para evitar que el timer sobrescriba el texto
            cell.dataset.online = "true";
        } else {
            // ESTADO: OFFLINE
            dot.classList.remove('online');
            dot.classList.add('offline');
            
            cell.dataset.online = "false";
            text.style.fontWeight = '400';
            text.style.color = '#666'; // Gris normal
            
            // Si el evento trae timestamp de desconexión, actualizamos el atributo data
            if (offlineTimestamp) {
                const ts = new Date(offlineTimestamp).getTime();
                cell.dataset.timestamp = ts;
                text.textContent = 'Hace un momento';
            }
        }
    }

    function startTimeUpdater() {
        if (timeUpdateInterval) clearInterval(timeUpdateInterval);
        
        // Ejecutar cada 60 segundos para actualizar los textos "Hace X min"
        timeUpdateInterval = setInterval(() => {
            const cells = document.querySelectorAll('.user-presence-cell');
            const now = Date.now();

            cells.forEach(cell => {
                // Si el usuario está online, ignoramos
                if (cell.dataset.online === "true") return;

                const ts = parseInt(cell.dataset.timestamp);
                if (!ts || ts === 0) {
                    // Si no hay fecha válida
                    const txt = cell.querySelector('.status-text');
                    if(txt && txt.textContent !== 'Nunca') txt.textContent = 'Nunca';
                    return;
                }

                const diffSeconds = Math.floor((now - ts) / 1000);
                let timeString = '';

                if (diffSeconds < 60) {
                    timeString = 'Hace un momento';
                } else if (diffSeconds < 3600) {
                    timeString = `Hace ${Math.floor(diffSeconds / 60)} min`;
                } else if (diffSeconds < 86400) { // Menos de 24h
                    timeString = `Hace ${Math.floor(diffSeconds / 3600)} h`;
                } else {
                    const date = new Date(ts);
                    // Formato corto de fecha dd/mm/yyyy
                    timeString = date.toLocaleDateString();
                }

                const txt = cell.querySelector('.status-text');
                if (txt) txt.textContent = timeString;
            });
        }, 60000); // 1 minuto
    }

    function selectSingleRow(clickedRow, userId) {
        if (clickedRow.classList.contains('selected')) {
            clickedRow.classList.remove('selected');
            selectedUserId = null;
            return;
        }
        const allRows = document.querySelectorAll('.admin-row-selectable.selected');
        allRows.forEach(r => r.classList.remove('selected'));
        clickedRow.classList.add('selected');
        selectedUserId = userId;
    }

    async function loadUsersTable(page, query) {
        const tbody = document.getElementById('admin-users-table-body');
        const pagination = document.getElementById('admin-users-pagination');
        
        tbody.classList.add('table-loading');

        const basePath = window.BASE_PATH || '/ProjectAurora/';
        const fetchUrl = `${basePath}public/loader.php?section=admin/users&page=${page}&q=${encodeURIComponent(query)}&ajax_partial=1`;

        try {
            const response = await fetch(fetchUrl);
            const data = await response.json();

            if (data.html_rows !== undefined) {
                tbody.innerHTML = data.html_rows;
                pagination.innerHTML = data.html_pagination;
                
                const newUrl = `${basePath}admin/users?page=${page}` + (query ? `&q=${encodeURIComponent(query)}` : '');
                window.history.pushState({path: newUrl}, '', newUrl);
                
                selectedUserId = null;
                
                // IMPORTANTE: Reinicializar el estado en vivo para las nuevas filas cargadas
                initLivePresence();
            }
        } catch (error) {
            console.error('Error cargando usuarios:', error);
        } finally {
            tbody.classList.remove('table-loading');
        }
    }
</script>