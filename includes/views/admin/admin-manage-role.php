<?php
// includes/views/admin/admin-manage-role.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad de la vista: solo administradores
$userRoleSession = $_SESSION['user_role'] ?? 'user';
if (!in_array($userRoleSession, ['administrator', 'founder'])) {
    http_response_code(403);
    echo "<div class='view-content'><div class='component-wrapper'><h1 style='color: var(--color-error); text-align: center;'>Acceso denegado</h1></div></div>";
    exit;
}

$targetUuid = $_GET['uuid'] ?? '';
$targetUser = null;

if (!empty($targetUuid)) {
    global $dbConnection;
    if (isset($dbConnection)) {
        try {
            $stmt = $dbConnection->prepare("SELECT id, uuid, username, email, role FROM users WHERE uuid = :uuid LIMIT 1");
            $stmt->execute([':uuid' => $targetUuid]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Error silencioso
        }
    }
}
?>

<div class="view-content" id="admin-manage-role-view">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_admin" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <?php if (!$targetUser): ?>
            <div class="component-header-card">
                <h1 class="component-page-title" style="color: var(--color-error);">Usuario no encontrado</h1>
                <p class="component-page-description">El identificador proporcionado no coincide con ningún usuario registrado.</p>
                <div class="component-actions" style="margin-top: 16px; justify-content: center;">
                    <button class="component-button component-button--black" data-nav="/ProjectAurora/admin/users">Volver a la lista</button>
                </div>
            </div>
        <?php else: 
            $roles = [
                'user' => t('admin.filter.role.user') ?? 'Usuario',
                'moderator' => t('admin.filter.role.moderator') ?? 'Moderador',
                'administrator' => t('admin.filter.role.administrator') ?? 'Administrador',
                'founder' => t('admin.filter.role.founder') ?? 'Fundador'
            ];
            $currentRole = $targetUser['role'];
            $currentRoleLabel = $roles[$currentRole] ?? $roles['user'];
        ?>
            <input type="hidden" id="admin-target-uuid" value="<?= htmlspecialchars($targetUser['uuid']) ?>">

            <div class="component-header-card">
                <h1 class="component-page-title">Gestionar Rol: <?= htmlspecialchars($targetUser['username']) ?></h1>
                <p class="component-page-description">Cambia el nivel de acceso y permisos de este usuario. Los cambios se aplican al instante.</p>
            </div>

            <div class="component-card--grouped">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__icon-container component-card__icon-container--bordered">
                            <span class="material-symbols-rounded">admin_panel_settings</span>
                        </div>
                        <div class="component-card__text">
                            <h2 class="component-card__title">Rol de la cuenta</h2>
                            <p class="component-card__description">Selecciona el nuevo rol para <b><?= htmlspecialchars($targetUser['email']) ?></b></p>
                        </div>
                    </div>
                    
                    <div class="component-card__actions" style="width: 100%;">
                        <div class="component-dropdown" style="max-width: 100%;">
                            <div class="component-dropdown-trigger" data-action="admin-toggle-dropdown">
                                <span class="material-symbols-rounded trigger-select-icon">shield_person</span>
                                <span class="component-dropdown-text"><?= htmlspecialchars($currentRoleLabel) ?></span>
                                <span class="material-symbols-rounded">expand_more</span>
                            </div>
                            
                            <div class="component-module component-module--display-overlay component-module--dropdown-selector disabled">
                                <div class="component-module-panel">
                                    <div class="pill-container"><div class="drag-handle"></div></div>
                                    <div class="component-module-panel-body component-module-panel-body--padded">
                                        <div class="component-menu-list overflow-y component-menu-list--dropdown">
                                            
                                            <?php foreach ($roles as $roleKey => $roleLabel): ?>
                                                <div class="component-menu-link <?= $currentRole === $roleKey ? 'active' : '' ?>" data-action="admin-select-role" data-value="<?= $roleKey ?>" data-label="<?= htmlspecialchars($roleLabel) ?>">
                                                    <div class="component-menu-link-icon"><span class="material-symbols-rounded">shield_person</span></div>
                                                    <div class="component-menu-link-text"><span><?= htmlspecialchars($roleLabel) ?></span></div>
                                                </div>
                                            <?php endforeach; ?>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>