<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/../../../config/core/database.php';
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

$searchData = SearchFetcher::searchUsers($pdo, $currentUserId, $q, $offset, $limit);
$results = $searchData['results'];
$hasMore = $searchData['hasMore'];

$renderUserCard = function ($user) use ($currentUserId) {
    $avatarPath = !empty($user['avatar']) ? '/ProjectAurora/' . $user['avatar'] : null;
    $uid = $user['id'];
    $role = $user['role'] ?? 'user';
    $mutualCount = $user['mutual_friends'];

    $actionsHtml = '';
    if ($user['friend_status'] === 'accepted') {
        $actionsHtml = '<button class="btn-add-friend btn-remove-friend" data-uid="' . $uid . '" data-i18n="search.actions.remove">' . trans('search.actions.remove') . '</button>';
    } elseif ($user['friend_status'] === 'pending') {
        if ($user['sender_id'] == $currentUserId) {
            $actionsHtml = '<button class="btn-add-friend btn-cancel-request" data-uid="' . $uid . '" data-i18n="search.actions.cancel">' . trans('search.actions.cancel') . '</button>';
        } else {
            $actionsHtml = '<button class="btn-accept-request" data-uid="' . $uid . '" data-i18n="search.actions.accept">' . trans('search.actions.accept') . '</button>
                            <button class="btn-decline-request" data-uid="' . $uid . '" data-i18n="search.actions.decline">' . trans('search.actions.decline') . '</button>';
        }
    } else {
        $actionsHtml = '<button class="btn-add-friend" data-uid="' . $uid . '" data-i18n="search.actions.add">' . trans('search.actions.add') . '</button>';
    }
?>
    <div class="user-card-item">
        <div class="user-info-group">
            <div class="user-avatar-container" data-role="<?php echo htmlspecialchars($role); ?>">
                <?php if ($avatarPath): ?>
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar">
                <?php else: ?>
                    <span class="material-symbols-rounded default-avatar">person</span>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                <span class="user-meta-text">Comunidad Aurora</span>
                <span class="user-meta-text" style="font-size: 12px; color: #888; margin-top: 2px;">
                    <?php echo $mutualCount; ?> <span data-i18n="search.mutual_friends"><?php echo trans('search.mutual_friends'); ?></span>
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
    foreach ($results as $user) {
        $renderUserCard($user);
    }
    if ($hasMore) {
        echo '<div id="ajax-has-more-flag" style="display:none;"></div>';
    }
    echo '<script>if(window.translateDocument) window.translateDocument(document.getElementById("search-results-list"));</script>';
    exit;
}
?>
<div class="section-content active" data-section="search">
    <div class="section-center-wrapper section-with-toolbar" style="justify-content: flex-start; align-items: center; flex-direction: column;">

        <div class="content-toolbar">
            <button class="toolbar-action-btn" 
                    data-i18n-tooltip="search.filter_tooltip" 
                    data-tooltip="<?php echo trans('search.filter_tooltip'); ?>">
                <span class="material-symbols-rounded">filter_list</span>
            </button>
        </div>

        <div class="search-results-card">
            <?php if (empty($q)): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">search</span>
                    <p data-i18n="search.empty_state"><?php echo trans('search.empty_state'); ?></p>
                </div>
            <?php elseif (count($results) === 0 && $offset === 0): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">person_off</span>
                    <p><span data-i18n="search.no_results"><?php echo trans('search.no_results'); ?></span> "<strong><?php echo htmlspecialchars($q); ?></strong>".</p>
                </div>
            <?php else: ?>

                <div class="results-list" id="search-results-list">
                    <?php
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
                            <?php echo trans('search.load_more'); ?>
                        </button>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>