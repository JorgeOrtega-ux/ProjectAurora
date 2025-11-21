<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Seguridad
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}

// 2. Conexión BD
require_once __DIR__ . '/../../../config/database.php';

// 3. Query
try {
    $stmt = $pdo->query("SELECT id, username, email, role, account_status, avatar, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

$basePathForAvatar = '/ProjectAurora/'; 
?>

<div class="section-content overflow-y active" data-section="admin/users">
    <div class="section-center-wrapper" style="display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 40px; gap: 30px; height: auto; min-height: 100%;">
        
        <div class="content-toolbar">
            <div class="toolbar-group">
                <button class="toolbar-action-btn" title="Filtrar">
                    <span class="material-symbols-rounded">filter_list</span>
                </button>
                
                <button class="toolbar-action-btn" title="Gestionar Rol">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                </button>
                
                <button class="toolbar-action-btn" title="Estado de Cuenta">
                    <span class="material-symbols-rounded">toggle_on</span>
                </button>
            </div>
            
            <div class="toolbar-group text-muted">
                Total: <strong><?php echo count($users); ?></strong>
            </div>
        </div>

        <div class="custom-list-container">
            
            <div class="list-header-row">
                <div class="col-user">USUARIO</div>
                <div class="col-email">CORREO ELECTRÓNICO</div>
                <div class="col-role">ROL</div>
                <div class="col-status">ESTADO</div>
                <div class="col-date">FECHA DE REGISTRO</div>
            </div>

            <div class="list-body">
                <?php if (empty($users)): ?>
                    <div class="list-empty-state">
                        No hay usuarios registrados.
                    </div>
                <?php else: ?>
                    <?php foreach ($users as $user): 
                        $avatarUrl = !empty($user['avatar']) ? $basePathForAvatar . $user['avatar'] : null;
                        $dateFormatted = date('d/m/Y', strtotime($user['created_at']));
                    ?>
                    <div class="list-item-row">
                        
                        <div class="col-user">
                            <div class="item-avatar">
                                <?php if ($avatarUrl): ?>
                                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar">
                                <?php else: ?>
                                    <span class="material-symbols-rounded">person</span>
                                <?php endif; ?>
                            </div>
                            <span class="username-text"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>

                        <div class="col-email">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </div>

                        <div class="col-role">
                            <?php 
                            $roleClass = 'badge-user';
                            if ($user['role'] === 'founder') $roleClass = 'badge-founder';
                            elseif (in_array($user['role'], ['admin', 'administrator'])) $roleClass = 'badge-admin';
                            elseif ($user['role'] === 'moderator') $roleClass = 'badge-mod';
                            ?>
                            <span class="list-badge <?php echo $roleClass; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>

                        <div class="col-status">
                            <?php 
                            $icon = 'help'; $colorClass = '';
                            if ($user['account_status'] === 'active') { $icon = 'check_circle'; $colorClass = 'text-success'; }
                            elseif ($user['account_status'] === 'suspended') { $icon = 'block'; $colorClass = 'text-danger'; }
                            else { $icon = 'delete'; $colorClass = 'text-muted'; }
                            ?>
                            <div class="status-pill <?php echo $colorClass; ?>">
                                <span class="material-symbols-rounded"><?php echo $icon; ?></span>
                                <?php echo ucfirst($user['account_status']); ?>
                            </div>
                        </div>

                        <div class="col-date">
                            <?php echo $dateFormatted; ?>
                        </div>

                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>