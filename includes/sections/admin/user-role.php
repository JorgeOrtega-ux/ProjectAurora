<?php
// includes/sections/admin/user-role.php

require_once __DIR__ . '/../../libs/Utils.php';
require_once __DIR__ . '/../../../api/services/AdminService.php';

// Validar ID
$targetId = $_GET['id'] ?? null;

if (!$targetId) {
    echo "<script>window.location.href = '?page=admin/users';</script>";
    exit;
}

// Instanciar servicio
$currentAdminId = $_SESSION['user_id'] ?? 0; 
$adminService = new AdminService($pdo, $i18n, $currentAdminId);

// Obtener datos del usuario
$response = $adminService->getUserDetails($targetId);

if (!$response['success']) {
    echo "<script>window.location.href = '?page=admin/users&error=user_not_found';</script>";
    exit;
}

$user = $response['user'];
?>

<div class="component-wrapper" data-section="admin-user-role" data-user-id="<?php echo htmlspecialchars($user['id']); ?>">
    
    <script type="application/json" id="server-role-data">
        <?php echo json_encode(['current_role' => $user['role']]); ?>
    </script>

    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <?php echo sprintf($i18n->t('admin.user_role.toolbar_title'), htmlspecialchars($user['username'])); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" style="cursor: help; border-color: transparent;" data-tooltip="<?php echo $i18n->t('admin.audit_active_tooltip'); ?>">
                        <span class="material-symbols-rounded" style="color: var(--text-secondary);">history_edu</span>
                    </button>

                    <button class="component-button primary" data-action="save-role" disabled>
                        <?php echo $i18n->t('global.save'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('admin.user_role.card_title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('admin.user_role.card_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown" id="admin-role-selector">
                    
                    <?php 
                        // Definición de Roles permitidos
                        $roles = [
                            'user' => ['label' => $i18n->t('admin.role.user'), 'icon' => 'person'],
                            'moderator' => ['label' => $i18n->t('admin.role.moderator'), 'icon' => 'gpp_maybe'],
                            'administrator' => ['label' => $i18n->t('admin.role.administrator'), 'icon' => 'security']
                        ];

                        $currentRoleKey = $user['role'];
                        $currentRoleLabel = isset($roles[$currentRoleKey]) ? $roles[$currentRoleKey]['label'] : ucfirst($currentRoleKey);
                        $currentRoleIcon = isset($roles[$currentRoleKey]) ? $roles[$currentRoleKey]['icon'] : 'badge';
                    ?>

                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon" data-element="current-role-icon">
                            <?php echo $currentRoleIcon; ?>
                        </span>
                        <span class="trigger-select-text" data-element="current-role-label">
                            <?php echo $currentRoleLabel; ?>
                        </span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    
                    <div class="popover-module">
                        <div class="menu-list">
                            <?php foreach($roles as $key => $data): 
                                $isActive = ($key === $currentRoleKey) ? 'active' : '';
                            ?>
                                <div class="menu-link <?php echo $isActive; ?>" 
                                     data-action="select-option" 
                                     data-type="role" 
                                     data-value="<?php echo $key; ?>" 
                                     data-label="<?php echo $data['label']; ?>"
                                     style="display: flex;">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded"><?php echo $data['icon']; ?></span>
                                    </div>
                                    <div class="menu-link-text"><?php echo $data['label']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if($user['role'] === 'founder'): ?>
    <div class="state-empty mt-2">
        <p class="state-text" style="color: var(--warning-color);">
            <span class="material-symbols-rounded" style="vertical-align: middle;">warning</span>
            <?php echo $i18n->t('admin.user_role.founder_warn'); ?>
        </p>
    </div>
    <?php endif; ?>

</div>