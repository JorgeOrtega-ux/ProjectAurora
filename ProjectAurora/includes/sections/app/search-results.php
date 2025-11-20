<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

require_once __DIR__ . '/../../../config/database.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
$currentUserId = $_SESSION['user_id'];

if ($q !== '') {
    // --- CONSULTA SQL MEJORADA ---
    // Hemos añadido una subconsulta (SELECT COUNT...) para calcular los amigos en común.
    // Esta lógica cruza tus amigos (fA) con los amigos del usuario encontrado (fB).
    
    $sql = "SELECT u.id, u.username, u.avatar, 
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
            LIMIT 20";
            
    $stmt = $pdo->prepare($sql);
    
    // Pasamos los parámetros en el orden exacto en que aparecen los '?' en la consulta
    $stmt->execute([
        $currentUserId, $currentUserId, $currentUserId, // Para la subconsulta de amigos en común
        $currentUserId, $currentUserId,                 // Para el LEFT JOIN de estado de amistad
        '%' . $q . '%', $currentUserId                  // Para el WHERE (búsqueda y excluir mi usuario)
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="section-content overflow-y active" data-section="search">
    <div class="section-center-wrapper" style="align-items: flex-start; padding-top: 40px;"> 
        <div class="search-results-card">
            <?php if (empty($q)): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">search</span>
                    <p>Escribe algo para buscar usuarios.</p>
                </div>
            <?php elseif (count($results) === 0): ?>
                <div class="search-empty-state">
                    <span class="material-symbols-rounded">person_off</span>
                    <p>No encontramos a nadie llamado "<strong><?php echo htmlspecialchars($q); ?></strong>".</p>
                </div>
            <?php else: ?>
                
                <div class="results-list">
                    <?php foreach ($results as $user): ?>
                        <?php 
                            $avatarPath = !empty($user['avatar']) ? '/ProjectAurora/' . $user['avatar'] : null; 
                            $uid = $user['id'];
                            $mutualCount = $user['mutual_friends']; // Dato calculado por SQL
                            
                            // LÓGICA DE BOTONES
                            $actionsHtml = '';

                            if ($user['friend_status'] === 'accepted') {
                                $actionsHtml = '<button class="btn-add-friend btn-remove-friend" data-uid="'.$uid.'">Eliminar amigo</button>';
                            } elseif ($user['friend_status'] === 'pending') {
                                if ($user['sender_id'] == $currentUserId) {
                                    $actionsHtml = '<button class="btn-add-friend btn-cancel-request" data-uid="'.$uid.'">Cancelar solicitud</button>';
                                } else {
                                    $actionsHtml = '
                                        <button class="btn-accept-request" data-uid="'.$uid.'">Aceptar</button>
                                        <button class="btn-decline-request" data-uid="'.$uid.'">Rechazar</button>
                                    ';
                                }
                            } else {
                                $actionsHtml = '<button class="btn-add-friend" data-uid="'.$uid.'">Agregar a amigos</button>';
                            }
                        ?>
                        <div class="user-card-item">
                            <div class="user-info-group">
                                <div class="user-avatar-container">
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
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>