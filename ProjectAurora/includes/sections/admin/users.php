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
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest'; // Default sort

$whereClause = "";
$params = [];

if (!empty($q)) {
    $whereClause = "WHERE u.username LIKE ? OR u.email LIKE ?";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// Lógica de Ordenamiento
$orderBy = "ORDER BY u.created_at DESC"; // Default
switch ($sort) {
    case 'name_asc':
        $orderBy = "ORDER BY u.username ASC";
        break;
    case 'name_desc':
        $orderBy = "ORDER BY u.username DESC";
        break;
    case 'date_oldest':
        $orderBy = "ORDER BY u.created_at ASC";
        break;
    case 'date_newest':
    default:
        $orderBy = "ORDER BY u.created_at DESC";
        break;
}

// --- HELPERS ---
function getStatusClass($status)
{
    return match ($status) {
        'active' => 'component-badge--success',
        'suspended' => 'component-badge--danger',
        'deleted' => 'component-badge--neutral',
        default => 'component-badge--neutral'
    };
}

function formatTimeAgo($datetime)
{
    if (!$datetime) return trans('global.time.never');
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return trans('global.time.just_now');
    if ($diff < 3600) return trans('global.time.minutes_ago', ['count' => floor($diff / 60)]);
    if ($diff < 86400) return trans('global.time.hours_ago', ['count' => floor($diff / 3600)]);
    return date('d/m/Y', $time);
}

// Función para renderizar filas (HTML)
function renderUserRows($users)
{
    ob_start();
    if (count($users) > 0):
        foreach ($users as $u):
            $pfpUrl = !empty($u['profile_picture']) ? '/ProjectAurora/' . $u['profile_picture'] : null;
            $statusClass = getStatusClass($u['account_status']);
            $is2FA = ((int)$u['is_2fa_enabled'] === 1);
            $rawTime = $u['last_seen'];
            $initialText = formatTimeAgo($rawTime);
            $userId = $u['id'];
            $jsTimestamp = $rawTime ? strtotime($rawTime) * 1000 : 0;
            $userRole = $u['role'] ?? 'user';
?>
            <tr class="component-table-row" 
                data-selectable="true" 
                data-action="select-user-row" 
                data-uid="<?php echo $userId; ?>">
                <td class="col-id"><?php echo $userId; ?></td>
                <td>
                    <div class="user-info-cell">
                        <div class="user-table-pfp" data-role="<?php echo htmlspecialchars($userRole); ?>">
                            <?php if ($pfpUrl): ?>
                                <img src="<?php echo htmlspecialchars($pfpUrl); ?>" alt="Foto de perfil">
                            <?php else: ?>
                                <div class="user-avatar-placeholder">
                                    <span class="material-symbols-rounded avatar-icon">person</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-details-wrapper">
                            <span class="user-username"><?php echo htmlspecialchars($u['username']); ?></span>
                            <span class="user-created">
                                <span data-i18n="admin.users.created_at"><?php echo trans('admin.users.created_at'); ?></span>
                                <?php echo date('d/m/Y', strtotime($u['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </td>
                <td class="col-email"><?php echo htmlspecialchars($u['email']); ?></td>
                <td class="col-status">
                    <span class="component-badge <?php echo $statusClass; ?>">
                        <?php echo ucfirst($u['account_status']); ?>
                    </span>
                </td>
                <td class="col-role">
                    <span class="component-badge component-badge--neutral role-badge">
                        <?php echo ucfirst($u['role']); ?>
                    </span>
                </td>
                <td class="col-2fa">
                    <?php if ($is2FA): ?>
                        <span class="material-symbols-rounded icon-2fa-on" 
                              data-i18n-title="admin.users.tooltip.protected"
                              title="<?php echo trans('admin.users.tooltip.protected'); ?>">shield_lock</span>
                    <?php else: ?>
                        <span class="material-symbols-rounded icon-2fa-off" 
                              data-i18n-title="admin.users.tooltip.unprotected"
                              title="<?php echo trans('admin.users.tooltip.unprotected'); ?>">no_encryption</span>
                    <?php endif; ?>
                </td>
                <td class="user-presence-cell col-presence"
                    id="presence-<?php echo $userId; ?>"
                    data-uid="<?php echo $userId; ?>"
                    data-timestamp="<?php echo $jsTimestamp; ?>">
                    <div class="presence-wrapper">
                        <div class="component-status-dot offline"></div>
                        <span class="status-text presence-text-default"><?php echo $initialText; ?></span>
                    </div>
                </td>
            </tr>
        <?php endforeach;
    else: ?>
        <tr>
            <td colspan="7" class="component-table-empty">
                <span class="material-symbols-rounded component-table-empty-icon">person_off</span>
                <p data-i18n="admin.users.empty_state"><?php echo trans('admin.users.empty_state'); ?></p>
            </td>
        </tr>
    <?php endif;
    return ob_get_clean();
}

function renderPagination($page, $totalPages, $q, $sort)
{
    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);
    $qEncoded = htmlspecialchars($q, ENT_QUOTES);
    $sortEncoded = htmlspecialchars($sort, ENT_QUOTES);

    $prevDisabled = ($page <= 1) ? 'disabled' : '';
    $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';

    ob_start();
    ?>
    <button class="component-pagination__btn <?php echo $prevDisabled; ?>"
        data-action="paginate-users" 
        data-page="<?php echo $prevPage; ?>" 
        data-query="<?php echo $qEncoded; ?>"
        data-sort="<?php echo $sortEncoded; ?>">
        <span class="material-symbols-rounded">chevron_left</span>
    </button>
    <span class="component-pagination__text"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
    <button class="component-pagination__btn <?php echo $nextDisabled; ?>"
        data-action="paginate-users" 
        data-page="<?php echo $nextPage; ?>" 
        data-query="<?php echo $qEncoded; ?>"
        data-sort="<?php echo $sortEncoded; ?>">
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
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $sqlUsers = "SELECT u.id, u.username, u.email, u.profile_picture, u.role, u.account_status, u.created_at, u.is_2fa_enabled,
                 (SELECT MAX(last_activity) FROM user_sessions WHERE user_id = u.id) as last_seen
                 FROM users u $whereClause $orderBy LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sqlUsers);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
    $totalUsers = 0;
    $totalPages = 1;
}

if (isset($_GET['ajax_partial']) && $_GET['ajax_partial'] === '1') {
    header('Content-Type: application/json');
    echo json_encode(['html_rows' => renderUserRows($users), 'html_pagination' => renderPagination($page, $totalPages, $q, $sort)]);
    exit;
}
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/users">
    <div class="section-center-wrapper section-with-toolbar admin-users-wrapper">

        <div class="toolbar-stack">

            <div class="component-toolbar" id="toolbar-default">
                <div class="component-toolbar__group">
                    <button class="component-icon-button" data-action="toggle-admin-user-search" 
                            data-i18n-tooltip="global.search" 
                            data-tooltip="<?php echo trans('global.search'); ?>">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                    
                    <div class="component-toolbar__separator"></div>
                    
                    <div style="position: relative;">
                        <button class="component-icon-button <?php echo ($sort !== 'date_newest') ? 'active' : ''; ?>" 
                                data-action="toggle-dropdown" 
                                data-target="dropdown-admin-filters"
                                data-i18n-tooltip="global.filter" 
                                data-tooltip="<?php echo trans('global.filter'); ?>">
                            <span class="material-symbols-rounded">filter_list</span>
                        </button>

                        <div class="popover-module popover-module--anchor-left disabled" id="dropdown-admin-filters" style="width: 220px; left: 0; top: calc(100% + 8px);">
                            <div class="menu-content">
                                <div class="menu-list">
                                    <div class="menu-link <?php echo ($sort === 'name_asc') ? 'active' : ''; ?>" 
                                         data-action="filter-users" data-sort="name_asc">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">sort_by_alpha</span></div>
                                        <div class="menu-link-text" data-i18n="admin.users.filter.name_asc"><?php echo trans('admin.users.filter.name_asc'); ?></div>
                                        <?php if($sort === 'name_asc'): ?><div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div><?php endif; ?>
                                    </div>
                                    
                                    <div class="menu-link <?php echo ($sort === 'name_desc') ? 'active' : ''; ?>" 
                                         data-action="filter-users" data-sort="name_desc">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">sort_by_alpha</span></div>
                                        <div class="menu-link-text" data-i18n="admin.users.filter.name_desc"><?php echo trans('admin.users.filter.name_desc'); ?></div>
                                        <?php if($sort === 'name_desc'): ?><div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div><?php endif; ?>
                                    </div>

                                    <div class="component-divider" style="margin: 4px 0;"></div>

                                    <div class="menu-link <?php echo ($sort === 'date_newest') ? 'active' : ''; ?>" 
                                         data-action="filter-users" data-sort="date_newest">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">calendar_today</span></div>
                                        <div class="menu-link-text" data-i18n="admin.users.filter.date_newest"><?php echo trans('admin.users.filter.date_newest'); ?></div>
                                        <?php if($sort === 'date_newest'): ?><div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div><?php endif; ?>
                                    </div>

                                    <div class="menu-link <?php echo ($sort === 'date_oldest') ? 'active' : ''; ?>" 
                                         data-action="filter-users" data-sort="date_oldest">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">history</span></div>
                                        <div class="menu-link-text" data-i18n="admin.users.filter.date_oldest"><?php echo trans('admin.users.filter.date_oldest'); ?></div>
                                        <?php if($sort === 'date_oldest'): ?><div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="admin-users-pagination" class="component-pagination component-toolbar__right">
                    <?php echo renderPagination($page, $totalPages, $q, $sort); ?>
                </div>

                <div class="component-toolbar search-toolbar-panel disabled" id="admin-users-search-bar">
                    <div class="search-container full-width-search">
                        <span class="material-symbols-rounded search-icon">search</span>
                        <input type="text" id="admin-users-search-input" class="search-input"
                            data-i18n-placeholder="admin.users.search_placeholder"
                            placeholder="<?php echo trans('admin.users.search_placeholder'); ?>"
                            value="<?php echo htmlspecialchars($q); ?>"
                            data-action="admin-search-input"> </div>
                </div>
            </div>

            <div class="component-toolbar d-none" id="toolbar-selected">
                <div class="component-toolbar__group">
                    <button class="component-icon-button" id="btn-manage-general" 
                            data-i18n-tooltip="admin.users.actions.manage" 
                            data-tooltip="<?php echo trans('admin.users.actions.manage'); ?>">
                        <span class="material-symbols-rounded">manage_accounts</span>
                    </button>
                    <button class="component-icon-button" id="btn-manage-role" 
                            data-i18n-tooltip="admin.users.actions.role" 
                            data-tooltip="Rol de usuario">
                        <span class="material-symbols-rounded">shield_person</span>
                    </button>
                    <button class="component-icon-button" id="btn-manage-sanctions" 
                            data-i18n-tooltip="admin.users.actions.sanctions" 
                            data-tooltip="<?php echo trans('admin.users.actions.sanctions'); ?>">
                        <span class="material-symbols-rounded">gavel</span>
                    </button>
                </div>
                <div class="component-toolbar__right">
                    <button class="component-icon-button" data-action="deselect-users" 
                            data-i18n-tooltip="global.deselect" 
                            data-tooltip="<?php echo trans('global.deselect'); ?>">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
            </div>

        </div>

        <div class="component-table-container">
            <table class="component-table">
                <thead>
                    <tr>
                        <th class="col-id" data-i18n="admin.users.table.id"><?php echo trans('admin.users.table.id'); ?></th>
                        <th data-i18n="admin.users.table.username"><?php echo trans('admin.users.table.username'); ?></th>
                        <th data-i18n="admin.users.table.email"><?php echo trans('admin.users.table.email'); ?></th>
                        <th class="col-status" data-i18n="admin.users.table.status"><?php echo trans('admin.users.table.status'); ?></th>
                        <th class="col-role" data-i18n="admin.users.table.role"><?php echo trans('admin.users.table.role'); ?></th>
                        <th class="col-2fa" data-i18n="admin.users.table.2fa"><?php echo trans('admin.users.table.2fa'); ?></th>
                        <th class="col-presence" data-i18n="admin.users.table.presence"><?php echo trans('admin.users.table.presence'); ?></th>
                    </tr>
                </thead>
                <tbody id="admin-users-table-body">
                    <?php echo renderUserRows($users); ?>
                </tbody>
            </table>
        </div>
    </div>
</div>