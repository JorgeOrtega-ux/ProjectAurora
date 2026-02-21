<?php
// includes/views/admin/admin-users.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Obtener los usuarios de la base de datos
$users = [];
global $dbConnection;
if (isset($dbConnection)) {
    try {
        $stmt = $dbConnection->query("SELECT uuid, username, email, role, status, avatar_path, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        // En caso de error, el arreglo quedará vacío
    }
}
?>

<style>
    /* Estilos propios de Admin Users */
    .component-toolbar-wrapper { position: sticky; top: 16px; width: 100%; max-width: 425px; margin: 0 auto 24px auto; z-index: 50; }
    .component-toolbar { display: flex; align-items: center; justify-content: space-between; width: 100%; height: 56px; padding: 8px; background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); position: relative; }
    .toolbar-group { width: 100%; height: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .component-toolbar--primary { position: relative; z-index: 2; }
    .component-toolbar--secondary { position: absolute; top: calc(100% + 5px); left: 0; width: 100%; height: 56px; padding: 8px; background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-card); z-index: 10; opacity: 0; pointer-events: none; visibility: hidden; transform: translateY(-5px); transition: all 0.2s ease; }
    .component-toolbar--secondary.active { opacity: 1; pointer-events: auto; visibility: visible; transform: translateY(0); }
    .component-toolbar__side { display: flex; align-items: center; gap: 8px; }
    .selection-info-text { font-size: 14px; font-weight: 500; color: var(--text-primary); margin-right: 4px; }
    
    .component-pagination { display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 8px; height: 40px; padding: 2px; gap: 4px; }
    .component-pagination .component-button { border: 1px solid var(--border-transparent-20); border-radius: 6px; background-color: transparent; }
    .component-pagination .component-button:hover:not(:disabled) { background-color: var(--bg-hover-light); }
    .component-pagination-info { padding: 0 8px; font-size: 14px; font-weight: 500; color: var(--text-primary); display: flex; align-items: center; height: 100%; }

    .component-users-list { display: flex; flex-direction: column; gap: 12px; }
    .component-user-card { background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; transition: border-color 0.2s, box-shadow 0.2s; cursor: pointer; }
    .component-user-card:hover { border-color: var(--text-primary); }
    .component-user-card.selected { border-color: var(--action-primary); box-shadow: 0 0 0 1px var(--action-primary); }

    .admin-user-avatar-box { width: 40px; height: 40px; border-radius: 50%; background-color: var(--bg-surface-alt); position: relative; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 4px; }
    .admin-user-avatar-box::before { content: ''; position: absolute; top: -3px; left: -3px; right: -3px; bottom: -3px; border-radius: 50%; border: 2px solid transparent; pointer-events: none; z-index: 2; }
    .admin-user-avatar-box[data-role="user"]::before { border-color: var(--role-user-border); }
    .admin-user-avatar-box[data-role="moderator"]::before { border-color: var(--role-moderator); }
    .admin-user-avatar-box[data-role="administrator"]::before { border-color: var(--role-administrator); }
    .admin-user-avatar-box[data-role="founder"]::before { border: none; background-image: conic-gradient(from 300deg, #D32029 0deg 90deg, #206BD3 90deg 210deg, #28A745 210deg 300deg, #FFC107 300deg 360deg); mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0); -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0); }
    .admin-user-avatar-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 2px solid var(--bg-surface); }

    .component-badge { display: inline-flex; align-items: center; gap: 6px; padding: 0 12px; min-height: 30px; background-color: transparent; border: 1px solid var(--border-transparent-20); border-radius: 50px; font-size: 13px; font-weight: 500; color: var(--text-secondary); white-space: nowrap; }
    .component-badge .material-symbols-rounded { font-size: 16px; }

    /* Estilos del Módulo de Filtros Dinámicos */
    .filter-view-container { display: flex; width: 200%; transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }
    .filter-view { width: 50%; flex-shrink: 0; padding: 0 8px; }
    .filter-module-body { overflow: hidden; padding: 0; }
    .filter-active-indicator { width: 8px; height: 8px; background-color: var(--action-primary); border-radius: 50%; display: none; }
    .has-active-filters .filter-active-indicator { display: block; }
    .filter-checkbox-wrapper { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px; cursor: pointer; transition: background-color 0.2s; }
    .filter-checkbox-wrapper:hover { background-color: var(--bg-hover-light); }
    .filter-checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--action-primary); cursor: pointer; }
</style>

<div class="view-content" id="admin-users-view">
    <div class="component-wrapper">
        
        <div class="component-toolbar-wrapper">
            <div class="component-toolbar">
                <div class="toolbar-group component-toolbar--primary" id="toolbar-primary">
                    <div class="component-toolbar__side">
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
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_manage') ?>">
                            <span class="material-symbols-rounded">manage_accounts</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_role') ?>">
                            <span class="material-symbols-rounded">admin_panel_settings</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.users.tooltip_status') ?>">
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
                        
                        <div style="padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" class="component-button component-button--square-40" id="filter-btn-back" style="display: none; border: none; background: transparent;">
                                    <span class="material-symbols-rounded">arrow_back</span>
                                </button>
                                <h3 style="margin: 0; font-size: 16px; color: var(--text-primary);" id="filter-module-title"><?= t('admin.filter.title') ?></h3>
                            </div>
                            <button type="button" class="component-button" style="border: none; background: transparent; color: var(--action-primary); font-weight: 600;" data-action="admin-filter-clear">
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

                        <div style="padding: 16px; border-top: 1px solid var(--border-color);">
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

        <div class="component-users-list" id="admin-users-list">
            <?php if (empty($users)): ?>
                <div style="padding: 24px; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px;">
                    No hay usuarios registrados o no se pudo conectar a la base de datos.
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): 
                    $avatarPath = '/ProjectAurora/' . ltrim($user['avatar_path'], '/');
                    $dateFormatted = date('d M, Y', strtotime($user['created_at']));
                    
                    $statusIcon = 'check_circle';
                    if ($user['status'] === 'suspended') $statusIcon = 'block';
                    if ($user['status'] === 'deleted') $statusIcon = 'delete_forever';
                ?>
                    <div class="component-user-card js-admin-user-card" 
                         data-uuid="<?= htmlspecialchars($user['uuid']) ?>"
                         data-username="<?= strtolower(htmlspecialchars($user['username'])) ?>"
                         data-email="<?= strtolower(htmlspecialchars($user['email'])) ?>"
                         data-role="<?= htmlspecialchars($user['role']) ?>"
                         data-status="<?= htmlspecialchars($user['status']) ?>">
                        
                        <div class="admin-user-avatar-box" data-role="<?= htmlspecialchars($user['role']) ?>">
                            <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" loading="lazy">
                        </div>
                        
                        <span class="component-badge" title="Nombre de usuario">
                            <span class="material-symbols-rounded">person</span>
                            <?= htmlspecialchars($user['username']) ?>
                        </span>
                        
                        <span class="component-badge" title="Correo electrónico">
                            <span class="material-symbols-rounded">mail</span>
                            <?= htmlspecialchars($user['email']) ?>
                        </span>
                        
                        <span class="component-badge" title="Rol del usuario">
                            <span class="material-symbols-rounded">shield_person</span>
                            <?= t('admin.filter.role.' . $user['role']) ?>
                        </span>
                        
                        <span class="component-badge" title="Estado de la cuenta">
                            <span class="material-symbols-rounded"><?= $statusIcon ?></span>
                            <?= t('admin.filter.status.' . $user['status']) ?>
                        </span>
                        
                        <span class="component-badge" title="Identificador único (UUID)">
                            <span class="material-symbols-rounded">fingerprint</span>
                            <?= htmlspecialchars($user['uuid']) ?>
                        </span>
                        
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div id="admin-empty-state" style="display: none; padding: 24px; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px;">
                <?= t('admin.users.empty') ?>
            </div>
        </div>
    </div>
</div>