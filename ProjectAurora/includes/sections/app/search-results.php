<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

require_once __DIR__ . '/../../../config/database.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($q !== '') {
    $sql = "SELECT id, username, avatar, role 
            FROM users 
            WHERE username LIKE ? 
            AND id != ? 
            AND account_status = 'active'
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $q . '%', $_SESSION['user_id']]);
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
                                    <span class="user-meta-text">0 comunidades en común</span>
                                    <span class="user-meta-text">166 amigos en común</span>
                                </div>
                            </div>

                            <div class="user-action-group">
                                <button class="btn-add-friend">
                                    Agregar a amigos
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>