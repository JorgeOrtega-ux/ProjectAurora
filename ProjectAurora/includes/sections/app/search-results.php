<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

// Parámetros
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$isAjaxPartial = isset($_GET['ajax_partial']) && $_GET['ajax_partial'] === '1';

// Configuración
$limit = 2; 
$queryLimit = $limit + 1; 
$currentUserId = $_SESSION['user_id'];
$results = [];
$hasMore = false;

if ($q !== '') {
    // Consulta SQL
    $sql = "SELECT u.id, u.username, u.avatar, u.role, 
                   f.status as friend_status, f.sender_id,
                   (
                       SELECT COUNT(*) 
                       FROM friendships fA 
                       JOIN friendships fB 
                       ON (CASE WHEN fA.sender_id = ? THEN fA.receiver_id ELSE fA.sender_id END) = 
                          (CASE WHEN fB.sender_id = u.id THEN fB.receiver_id ELSE fB.sender_id END)
                       WHERE (fA.sender_id = ? OR fA.receiver_id = ?) AND fA.status = 'accepted'
                       AND (fB.sender_id = u.id OR fB.receiver_id = u.id) AND fB.status = 'accepted'
                   ) as mutual_friends
            FROM users u
            LEFT JOIN friendships f 
            ON (f.sender_id = ? AND f.receiver_id = u.id) 
            OR (f.sender_id = u.id AND f.receiver_id = ?)
            WHERE u.username LIKE ? 
            AND u.id != ? 
            AND u.account_status = 'active'
            LIMIT $queryLimit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $currentUserId, $currentUserId, $currentUserId, 
        $currentUserId, $currentUserId, 
        '%' . $q . '%', 
        $currentUserId
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) > $limit) {
        $hasMore = true;
        array_pop($results); 
    }
}

// --- HELPER: FUNCIÓN PARA RENDERIZAR UNA TARJETA ---
// Usamos esto para no duplicar el código HTML en las dos secciones
$renderUserCard = function($user) use ($currentUserId) {
    $avatarPath = !empty($user['avatar']) ? '/ProjectAurora/' . $user['avatar'] : null;
    $uid = $user['id'];
    $role = $user['role'] ?? 'user';
    $mutualCount = $user['mutual_friends'];

    $actionsHtml = '';
    if ($user['friend_status'] === 'accepted') {
        $actionsHtml = '<button class="btn-add-friend btn-remove-friend" data-uid="' . $uid . '">Eliminar amigo</button>';
    } elseif ($user['friend_status'] === 'pending') {
        if ($user['sender_id'] == $currentUserId) {
            $actionsHtml = '<button class="btn-add-friend btn-cancel-request" data-uid="' . $uid . '">Cancelar solicitud</button>';
        } else {
            $actionsHtml = '<button class="btn-accept-request" data-uid="' . $uid . '">Aceptar</button>
                            <button class="btn-decline-request" data-uid="' . $uid . '">Rechazar</button>';
        }
    } else {
        $actionsHtml = '<button class="btn-add-friend" data-uid="' . $uid . '">Agregar a amigos</button>';
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
                    <?php echo $mutualCount; ?> amigos en común
                </span>
            </div>
        </div>
        <div class="user-action-group" id="actions-<?php echo $uid; ?>">
            <?php echo $actionsHtml; ?>
        </div>
    </div>
    <?php
};

// =========================================================
// 1. SALIDA AJAX (CARGA PARCIAL)
// =========================================================
if ($isAjaxPartial) {
    // Si es una petición AJAX, solo imprimimos las tarjetas nuevas y salimos.
    foreach ($results as $user) {
        $renderUserCard($user);
    }
    if ($hasMore) {
        echo '<div id="ajax-has-more-flag" style="display:none;"></div>';
    }
    exit; // <--- IMPORTANTE: Detenemos el script aquí para no cargar el resto de la página
}

// =========================================================
// 2. SALIDA PÁGINA COMPLETA (ESTRUCTURA NORMAL)
// =========================================================
?>
<div class="section-content overflow-y active" data-section="search">
    <div class="section-center-wrapper" style="justify-content: flex-start; align-items: center; flex-direction: column;">

        <div class="content-toolbar">
            <button class="toolbar-action-btn" title="Filtrar resultados">
                <span class="material-symbols-rounded">filter_list</span>
            </button>
        </div>

        <div class="search-results-card">
            <?php if (empty($q)): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">search</span>
                    <p>Escribe algo para buscar usuarios.</p>
                </div>
            <?php elseif (count($results) === 0 && $offset === 0): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">person_off</span>
                    <p>No encontramos a nadie llamado "<strong><?php echo htmlspecialchars($q); ?></strong>".</p>
                </div>
            <?php else: ?>
                
                <div class="results-list" id="search-results-list">
                    <?php 
                    // Renderizamos los primeros resultados
                    foreach ($results as $user) {
                        $renderUserCard($user);
                    } 
                    ?>
                </div>

                <?php if ($hasMore): ?>
                    <div class="load-more-container" style="text-align: center; padding: 20px;">
                        <button class="btn-add-friend btn-load-more" 
                                data-query="<?php echo htmlspecialchars($q); ?>" 
                                data-offset="<?php echo $limit; ?>">
                            Mostrar más resultados
                        </button>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>