<?php
// includes/views/admin/admin-users.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Obtener los usuarios de la base de datos
$users = [];
global $dbConnection;
if (isset($dbConnection)) {
    try {
        $stmt = $dbConnection->query("SELECT uuid, username, email, role, status, is_suspended, avatar_path, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        // En caso de error, el arreglo quedará vacío
    }
}
?>

<div class="view-content" id="admin-users-view">
    <div class="component-wrapper">
        
        <div class="component-toolbar-wrapper">
            <div class="component-toolbar">
                <div class="toolbar-group component-toolbar--primary" id="toolbar-primary">
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Cambiar Vista" data-action="admin-toggle-view">
                            <span class="material-symbols-rounded" id="view-toggle-icon">table_rows</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_search') ?>" data-action="admin-toggle-search">
                            <span class="material-symbols-rounded">search</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_filter') ?>" data-action="toggleModule" data-target="moduleAdminUsersFilters" id="btn-admin-filter">
                            <span class="material-symbols-rounded">filter_list</span>
                        </button>
                    </div>
                    <div class="component-toolbar__side">
                        <div class="component-pagination">
                            <button type="button" class="component-button component-button--square-40" id="admin-pag-prev" disabled>
                                <span class="material-symbols-rounded">chevron_left</span>
                            </button>
                            <div class="component-pagination-info" id="admin-pag-info">0 / 0</div>
                            <button type="button" class="component-button component-button--square-40" id="admin-pag-next" disabled>
                                <span class="material-symbols-rounded">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="toolbar-group component-toolbar--secondary" id="toolbar-search">
                    <div class="component-search" style="width: 100%;">
                        <div class="component-search-icon"><span class="material-symbols-rounded">search</span></div>
                        <div class="component-search-input">
                            <input type="text" class="component-search-input-field" placeholder="<?= t('admin.users.search_placeholder') ?>" id="admin-user-search-input">
                        </div>
                        <button type="button" class="component-button component-button--square-40" style="border: none; background: transparent;" data-action="admin-toggle-search">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>
                </div>

                <div class="toolbar-group component-toolbar--secondary" id="toolbar-selection">
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_manage') ?>" data-action="admin-manage-account">
                            <span class="material-symbols-rounded">manage_accounts</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_role') ?>" data-action="admin-manage-role">
                            <span class="material-symbols-rounded">admin_panel_settings</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_status') ?>" data-action="admin-manage-status">
                            <span class="material-symbols-rounded">gpp_maybe</span>
                        </button>
                    </div>
                    <div class="component-toolbar__side">
                        <span class="selection-info-text" id="selection-count"><?= t('admin.users.selected', ['count' => 1]) ?></span>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_clear') ?>" data-action="admin-clear-selection">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>
                </div>

                <div class="component-module component-module--display-overlay component-module--size-m disabled" data-module="moduleAdminUsersFilters" id="moduleAdminUsersFilters">
                    <div class="component-module-panel">
                        <div class="pill-container"><div class="drag-handle"></div></div>
                        
                        <div style="padding: 16px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="component-button component-button--square-40" id="filter-btn-back" style="display: none; border: none; background: transparent;">
                                    <span class="material-symbols-rounded">arrow_back</span>
                                </button>
                                <h3 style="margin: 0; font-size: 16px; color: #111;" id="filter-module-title"><?= t('admin.filter.title') ?></h3>
                            </div>
                            <button type="button" class="component-button" style="border: none; background: transparent; color: #007bff; font-weight: 600;" data-action="admin-filter-clear">
                                <?= t('admin.filter.clear') ?>
                            </button>
                        </div>

                        <div class="component-module-panel-body filter-module-body">
                            <div class="filter-view-container" id="filter-viewport">
                                
                                <div class="filter-view" id="filter-view-main">
                                    <div class="component-menu-list" style="padding: 8px 0;">
                                        <button class="component-menu-link" data-action="admin-filter-nav" data-target="view-roles">
                                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">shield_person</span></div>
                                            <div class="component-menu-link-text"><span><?= t('admin.filter.role') ?></span></div>
                                            <div class="filter-active-indicator" id="dot-roles"></div>
                                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">chevron_right</span></div>
                                        </button>
                                        <button class="component-menu-link" data-action="admin-filter-nav" data-target="view-status">
                                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">gpp_maybe</span></div>
                                            <div class="component-menu-link-text"><span><?= t('admin.filter.status') ?></span></div>
                                            <div class="filter-active-indicator" id="dot-status"></div>
                                            <div class="component-menu-link-icon"><span class="material-symbols-rounded">chevron_right</span></div>
                                        </button>
                                    </div>
                                </div>

                                <div class="filter-view" id="filter-view-options">
                                    <div id="options-container" style="padding: 8px 0; display: flex; flex-direction: column;">
                                        </div>
                                </div>

                            </div>
                        </div>

                        <div style="padding: 16px; border-top: 1px solid #e0e0e0;">
                            <button type="button" class="component-button primary" style="width: 100%;" data-action="admin-filter-apply">
                                <?= t('admin.filter.apply') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('admin.users.title') ?></h1>
            <p class="component-page-description"><?= t('admin.users.desc') ?></p>
        </div>

        <div class="table-responsive-wrapper">
            <table class="component-data-list" id="admin-data-list">
                <thead class="component-data-list-header">
                    <tr>
                        <th>Avatar</th>
                        <th>Usuario</th>
                        <th>Correo Electrónico</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Identificador (UUID)</th>
                    </tr>
                </thead>
                <tbody id="admin-data-list-body">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): 
                        $avatarPath = '/ProjectAurora/' . ltrim($user['avatar_path'], '/');
                        
                        $statusIcon = 'check_circle';
                        $statusLabel = t('admin.filter.status.active') ?? 'Activo';
                        $filterStatusValue = 'active';

                        if ($user['status'] === 'deleted') {
                            $statusIcon = 'delete_forever';
                            $statusLabel = t('admin.filter.status.deleted') ?? 'Eliminado';
                            $filterStatusValue = 'deleted';
                        } elseif ($user['is_suspended'] == 1) {
                            $statusIcon = 'block';
                            $statusLabel = t('admin.filter.status.suspended') ?? 'Suspendido';
                            $filterStatusValue = 'suspended';
                        }
                    ?>
                        <tr class="component-data-card js-admin-user-card" 
                            data-uuid="<?= htmlspecialchars($user['uuid']) ?>"
                            data-username="<?= strtolower(htmlspecialchars($user['username'])) ?>"
                            data-email="<?= strtolower(htmlspecialchars($user['email'])) ?>"
                            data-role="<?= htmlspecialchars($user['role']) ?>"
                            data-status="<?= $filterStatusValue ?>">
                            
                            <td class="component-data-column">
                                <div class="component-avatar component-avatar--sm" data-role="<?= htmlspecialchars($user['role']) ?>">
                                    <img src="<?= htmlspecialchars($avatarPath) ?>" class="component-avatar__image" alt="Avatar" loading="lazy">
                                </div>
                            </td>
                            
                            <td class="component-data-column">
                                <span class="component-badge" title="Nombre de usuario">
                                    <span class="material-symbols-rounded">person</span>
                                    <span><?= htmlspecialchars($user['username']) ?></span>
                                </span>
                            </td>
                            
                            <td class="component-data-column">
                                <span class="component-badge" title="Correo electrónico">
                                    <span class="material-symbols-rounded">mail</span>
                                    <span><?= htmlspecialchars($user['email']) ?></span>
                                </span>
                            </td>
                            
                            <td class="component-data-column">
                                <span class="component-badge" title="Rol del usuario">
                                    <span class="material-symbols-rounded">shield_person</span>
                                    <span><?= t('admin.filter.role.' . $user['role']) ?? htmlspecialchars($user['role']) ?></span>
                                </span>
                            </td>
                            
                            <td class="component-data-column">
                                <span class="component-badge" title="Estado de la cuenta">
                                    <span class="material-symbols-rounded"><?= $statusIcon ?></span>
                                    <span><?= $statusLabel ?></span>
                                </span>
                            </td>
                            
                            <td class="component-data-column">
                                <span class="component-badge" title="Identificador único (UUID)">
                                    <span class="material-symbols-rounded">fingerprint</span>
                                    <span style="font-family: monospace;"><?= htmlspecialchars($user['uuid']) ?></span>
                                </span>
                            </td>
                            
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($users)): ?>
            <div id="admin-empty-state-db" style="padding: 24px; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px; margin-top: 12px;">
                No hay usuarios registrados o no se pudo conectar a la base de datos.
            </div>
        <?php endif; ?>
        
        <div id="admin-empty-state" style="display: none; padding: 24px; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px; margin-top: 12px;">
            <?= t('admin.users.empty') ?>
        </div>

    </div>
</div>