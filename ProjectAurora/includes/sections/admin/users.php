<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SEGURIDAD
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator'])) {
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

function formatTimeAgo($datetime) {
    if (!$datetime) return 'Nunca';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
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
            $rawTime = $u['last_seen']; 
            $initialText = formatTimeAgo($rawTime);
            $userId = $u['id'];
            $jsTimestamp = $rawTime ? strtotime($rawTime) * 1000 : 0;
        ?>
        <tr class="admin-row-selectable" data-uid="<?php echo $userId; ?>" onclick="selectSingleRow(event, this, '<?php echo $userId; ?>')">
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
                    <span class="status-text" style="color:#666; font-size:13px;"><?php echo $initialText; ?></span>
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

try {
    $sqlCount = "SELECT COUNT(*) FROM users u $whereClause";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalUsers = $stmtCount->fetchColumn();
    
    $totalPages = ceil($totalUsers / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $limit; }

    $sqlUsers = "SELECT u.id, u.username, u.email, u.avatar, u.role, u.account_status, u.created_at, u.is_2fa_enabled,
                 (SELECT MAX(last_activity) FROM user_sessions WHERE user_id = u.id) as last_seen
                 FROM users u $whereClause ORDER BY u.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sqlUsers);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $users = []; $totalUsers = 0; $totalPages = 1; }

if (isset($_GET['ajax_partial']) && $_GET['ajax_partial'] === '1') {
    header('Content-Type: application/json');
    echo json_encode(['html_rows' => renderUserRows($users), 'html_pagination' => renderPagination($page, $totalPages, $q)]);
    exit;
}
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/users">
    <div class="section-center-wrapper section-with-toolbar" style="flex-direction: column; justify-content: flex-start; width: 98%; max-width: none; margin: 0 auto;">
        
        <div class="toolbar-stack">
            
            <div class="content-toolbar" id="toolbar-default">
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button class="toolbar-action-btn" data-action="toggle-admin-user-search" data-tooltip="Buscar">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                    <div style="width: 1px; height: 24px; background: #ddd; margin: 0 4px;"></div>
                    <button class="toolbar-action-btn" data-tooltip="Filtrar">
                        <span class="material-symbols-rounded">filter_list</span>
                    </button>
                </div>
                
                <div id="admin-users-pagination" class="toolbar-pagination" style="margin-left: auto;">
                    <?php echo renderPagination($page, $totalPages, $q); ?>
                </div>

                <div class="content-toolbar search-toolbar-panel disabled" id="admin-users-search-bar">
                    <div class="search-container" style="width: 100%; max-width: 100%;">
                        <span class="material-symbols-rounded search-icon">search</span>
                        <input type="text" id="admin-users-search-input" class="search-input" 
                               placeholder="Buscar por nombre, correo o ID (Presiona Enter)..." 
                               value="<?php echo htmlspecialchars($q); ?>"
                               onkeydown="if(event.key === 'Enter') loadUsersTable(1, this.value)">
                    </div>
                </div>
            </div>

            <div class="content-toolbar" id="toolbar-selected" style="display: none;">
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button class="toolbar-action-btn" id="btn-manage-general" data-tooltip="Gestionar Usuario">
                        <span class="material-symbols-rounded">manage_accounts</span>
                    </button>
                    <button class="toolbar-action-btn" id="btn-manage-sanctions" data-tooltip="Gestionar Sanciones">
                        <span class="material-symbols-rounded">gavel</span>
                    </button>
                </div>
                <div style="margin-left: auto;">
                    <button class="toolbar-action-btn" onclick="window.deselectAllUsers()" data-tooltip="Deseleccionar">
                        <span class="material-symbols-rounded">close</span>
                    </button>
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
<script src="<?php echo $basePath; ?>assets/js/modules/admin-users.js"></script>