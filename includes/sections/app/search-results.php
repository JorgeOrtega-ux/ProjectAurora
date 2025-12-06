<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/../../../config/core/database.php';
// Asegurarnos de tener las utilidades para checkActionRateLimit
require_once __DIR__ . '/../../../config/helpers/utilities.php'; 
require_once __DIR__ . '/../../logic/search_fetcher.php'; 
require_once __DIR__ . '/../../logic/i18n_server.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$isAjaxPartial = isset($_GET['ajax_partial']) && $_GET['ajax_partial'] === '1';
$currentUserId = $_SESSION['user_id'];
$limit = 5; 

if ($isAjaxPartial) {
    $lang = $_SESSION['user_lang'] ?? 'es-latam';
    I18n::load($lang);
}

// --- [SEGURIDAD] RATE LIMITING ---
$searchLimit = 15;
$timeWindow = 1; // Minutos

$rateLimitExceeded = checkActionRateLimit($pdo, $currentUserId, 'search_users', $searchLimit, $timeWindow);

$results = [];
$communityResults = []; 
$hasMore = false;

if ($rateLimitExceeded) {
    if ($isAjaxPartial) {
        echo '<div class="search-empty-state" style="padding: 20px; color: #d32f2f;">
                <span class="material-symbols-rounded" style="font-size:32px; margin-bottom:8px;">warning</span>
                <p>' . translation('auth.errors.too_many_attempts') . '</p>
              </div>';
        exit;
    }
} else {
    if (!empty($q)) {
        logSecurityAction($pdo, $currentUserId, 'search_users');
    }

    // 1. Buscar Usuarios
    $searchData = SearchFetcher::searchUsers($pdo, $currentUserId, $q, $offset, $limit);
    $results = $searchData['results'];
    $hasMore = $searchData['hasMore'];

    // 2. Buscar Comunidades
    if ($offset === 0 && !empty($q)) {
        $communityResults = SearchFetcher::searchCommunities($pdo, $q, $currentUserId, 3);
    }
}

// Renderizar tarjeta de comunidad
$renderCommunityCard = function ($community) {
    $avatarPath = !empty($community['profile_picture']) ? $community['profile_picture'] : null;
    $uuid = $community['uuid'];
    $members = $community['member_count'];
    $privacyIcon = ($community['privacy'] === 'private') ? 'lock' : 'public';
    $isJoined = isset($community['is_member']) && $community['is_member'] > 0;
    
    // Estilos base para el botón (ya que quitamos la clase global)
    $baseBtnStyle = "padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;";
    
    $actionsHtml = '';
    
    if ($isJoined) {
        // Botones: Ver y Salir
        $actionsHtml = '
        <button class="btn-community-action" data-action="view-community" data-uuid="' . $uuid . '" style="' . $baseBtnStyle . ' background-color: var(--primary-color); color: white; border: none; margin-right:4px;">
            Ver
        </button>
        <button class="btn-community-action" data-action="leave-community-search" data-id="' . $community['id'] . '" data-uuid="' . $uuid . '" title="Salir de la comunidad" style="' . $baseBtnStyle . ' background-color: #ffebee; color: #d32f2f; border: 1px solid #ffcdd2;">
            <span class="material-symbols-rounded" style="font-size:16px;">logout</span>
        </button>';
    } else {
        if ($community['privacy'] === 'public') {
            // Unirse Pública
            $actionsHtml = '
            <button class="btn-community-action" data-action="join-public-community-search" data-id="' . $community['id'] . '" style="' . $baseBtnStyle . ' background-color: var(--accent-color, #6c5ce7); color: white; border: none;">
                Unirse
            </button>';
        } else {
            // Unirse Privada
            $actionsHtml = '
            <button class="btn-community-action" data-action="join-private-community-search" data-name="' . htmlspecialchars($community['community_name']) . '" style="' . $baseBtnStyle . ' background-color: #555; color: white; border: none;">
                <span class="material-symbols-rounded" style="font-size:14px; margin-right:4px;">vpn_key</span> Unirse
            </button>';
        }
    }
?>
    <div class="user-card-item community-result-item" style="border-left: 3px solid var(--accent-color, #6c5ce7);">
        <div class="user-info-group">
            <div class="user-pfp-container">
                <?php if ($avatarPath): ?>
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Community" data-img-type="community">
                <?php else: ?>
                    <span class="material-symbols-rounded default-avatar" style="background-color: #f0f2f5; color: #666;">groups</span>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($community['community_name']); ?></span>
                <span class="user-meta-text" style="font-size: 12px; color: #888; display: flex; align-items: center; gap: 4px;">
                    <span class="material-symbols-rounded" style="font-size: 14px;"><?php echo $privacyIcon; ?></span>
                    <span><?php echo $members; ?> miembros</span>
                    <?php if ($isJoined): ?>
                        <span style="color: var(--success-color, #2ecc71); font-weight:600; margin-left:4px;">• Unido</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="user-action-group">
            <?php echo $actionsHtml; ?>
        </div>
    </div>
<?php
};

$renderUserCard = function ($user) use ($currentUserId) {
    // Lógica de usuarios original
    $avatarPath = !empty($user['profile_picture']) ? '/ProjectAurora/' . $user['profile_picture'] : null;
    $uid = $user['id'];
    $role = $user['role'] ?? 'user';
    $mutualCount = $user['mutual_friends'];
    $isBlocked = (isset($user['is_blocked_by_me']) && $user['is_blocked_by_me'] > 0);
    $privacy = $user['message_privacy'] ?? 'friends';
    $canMessage = false;
    if ($privacy === 'everyone') $canMessage = true;
    elseif ($privacy === 'friends' && $user['friend_status'] === 'accepted') $canMessage = true;
    elseif ($privacy === 'nobody') $canMessage = false;

    $actionsHtml = '';

    if ($isBlocked) {
        $actionsHtml = '
            <button class="btn-add-friend" data-action="unblock-user" data-uid="' . $uid . '" style="color:#d32f2f; border-color:#ffcdd2; background:#ffebee;">
                <span class="material-symbols-rounded" style="font-size:16px; margin-right:4px;">lock_open</span> Desbloquear
            </button>';
    } else {
        $blockBtnHtml = '
            <button class="btn-add-friend btn-remove-friend" data-action="block-user" data-uid="' . $uid . '" title="Bloquear usuario" style="margin-left:4px; padding: 8px 10px;">
                <span class="material-symbols-rounded" style="font-size:16px;">block</span>
            </button>';

        if ($user['friend_status'] === 'accepted') {
            $chatBtnHtml = '';
            if ($canMessage) {
                $chatBtnHtml = '
                <button class="btn-add-friend" data-action="send-dm" data-uid="' . $uid . '" style="margin-right:4px;">
                    <span class="material-symbols-rounded" style="font-size:16px;">chat</span>
                </button>';
            }
            $actionsHtml = $chatBtnHtml . '<button class="btn-add-friend btn-remove-friend" data-uid="' . $uid . '" data-i18n="search.actions.remove">' . translation('search.actions.remove') . '</button>' . $blockBtnHtml;

        } elseif ($user['friend_status'] === 'pending') {
            if ($user['sender_id'] == $currentUserId) {
                $actionsHtml = '<button class="btn-add-friend btn-cancel-request" data-uid="' . $uid . '" data-i18n="search.actions.cancel">' . translation('search.actions.cancel') . '</button>' . $blockBtnHtml;
            } else {
                $actionsHtml = '<button class="btn-accept-request" data-uid="' . $uid . '" data-i18n="search.actions.accept">' . translation('search.actions.accept') . '</button>
                                <button class="btn-decline-request" data-uid="' . $uid . '" data-i18n="search.actions.decline">' . translation('search.actions.decline') . '</button>' . $blockBtnHtml;
            }
        } else {
            $actionsHtml = '<button class="btn-add-friend" data-uid="' . $uid . '" data-i18n="search.actions.add">' . translation('search.actions.add') . '</button>' . $blockBtnHtml;
        }
    }
?>
    <div class="user-card-item">
        <div class="user-info-group">
            <div class="user-pfp-container" data-role="<?php echo htmlspecialchars($role); ?>">
                <?php if ($avatarPath): ?>
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo translation('global.alt_avatar'); ?>" data-img-type="user">
                <?php else: ?>
                    <span class="material-symbols-rounded default-avatar">person</span>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                <span class="user-meta-text" data-i18n="search.user_subtitle"><?php echo translation('search.user_subtitle'); ?></span>
                <span class="user-meta-text" style="font-size: 12px; color: #888; margin-top: 2px;">
                    <?php echo $mutualCount; ?> <span data-i18n="search.mutual_friends"><?php echo translation('search.mutual_friends'); ?></span>
                </span>
            </div>
        </div>
        <div class="user-action-group" id="actions-<?php echo $uid; ?>">
            <?php echo $actionsHtml; ?>
        </div>
    </div>
<?php
};

if ($isAjaxPartial) {
    // Renderizado para respuesta AJAX
    if (!empty($communityResults)) {
        echo '<div class="results-subtitle" style="padding: 10px 15px; font-weight: 600; font-size: 0.85em; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Comunidades</div>';
        foreach ($communityResults as $comm) {
            $renderCommunityCard($comm);
        }
        if (!empty($results)) {
             echo '<div class="results-subtitle" style="padding: 15px 15px 10px; font-weight: 600; font-size: 0.85em; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Personas</div>';
        }
    }

    if (!empty($results)) {
        foreach ($results as $user) {
            $renderUserCard($user);
        }
    }

    if ($hasMore) {
        echo '<div id="ajax-has-more-flag" style="display:none;"></div>';
    }
    // NOTA: Se ha eliminado el bloque <script> que no se ejecutaba correctamente.
    exit;
}
?>
<div class="section-content active" data-section="search">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="explorer" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span style="font-size: 14px; font-weight: 600; color: #666;">
                    <?php echo empty($q) ? translation('global.search') : '"' . htmlspecialchars($q) . '"'; ?>
                </span>
            </div>
            </div>
    </div>

    <div class="section-center-wrapper section-with-toolbar" style="justify-content: flex-start; align-items: center; flex-direction: column;">

        <div class="search-results-card">
            
            <?php if ($rateLimitExceeded): ?>
                <div class="search-empty-state" style="color: #d32f2f;">
                    <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 10px;">warning</span>
                    <p style="font-weight: 600;"><?php echo translation('auth.errors.too_many_attempts'); ?></p>
                    <p style="font-size: 13px; margin-top:5px;">Espera un momento antes de buscar de nuevo.</p>
                </div>
            
            <?php elseif (empty($q)): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">search</span>
                    <p data-i18n="search.empty_state"><?php echo translation('search.empty_state'); ?></p>
                </div>

            <?php elseif (count($results) === 0 && count($communityResults) === 0 && $offset === 0): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">search_off</span>
                    <p><span data-i18n="search.no_results"><?php echo translation('search.no_results'); ?></span> "<strong><?php echo htmlspecialchars($q); ?></strong>".</p>
                </div>

            <?php else: ?>

                <div class="results-list" id="search-results-list">
                    <?php
                    // Renderizado inicial HTML (no AJAX)
                    if (!empty($communityResults)) {
                        echo '<div class="results-subtitle" style="padding: 10px 15px; font-weight: 600; font-size: 0.85em; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Comunidades</div>';
                        foreach ($communityResults as $comm) {
                            $renderCommunityCard($comm);
                        }
                        if (!empty($results)) {
                            echo '<div class="results-subtitle" style="padding: 15px 15px 10px; font-weight: 600; font-size: 0.85em; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Personas</div>';
                        }
                    }

                    foreach ($results as $user) {
                        $renderUserCard($user);
                    }
                    ?>
                </div>

                <?php if ($hasMore): ?>
                    <div class="load-more-container" style="text-align: center; padding: 20px;">
                        <button class="btn-load-more"
                            data-query="<?php echo htmlspecialchars($q); ?>"
                            data-offset="<?php echo $limit; ?>"
                            data-i18n="search.load_more">
                            <?php echo translation('search.load_more'); ?>
                        </button>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>