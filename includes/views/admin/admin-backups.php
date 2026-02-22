<?php
// includes/views/admin/admin-backups.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad de la vista: solo administradores o fundadores
$userRoleSession = $_SESSION['user_role'] ?? 'user';
if (!in_array($userRoleSession, ['administrator', 'founder'])) {
    http_response_code(403);
    echo "<div class='view-content'><div class='component-wrapper'><div class='component-header-card'><h1 class='component-page-title u-text-error'>Acceso denegado</h1></div></div></div>";
    exit;
}

// 1. Obtener la lista de backups generados desde la carpeta local fuera de public
$backups = [];
$backupDir = __DIR__ . '/../../../backups/';
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backupDir . $file;
            $backups[] = [
                'filename' => $file,
                'size' => round(filesize($filepath) / 1048576, 2), // MB
                'created_at' => date('Y-m-d H:i:s', filectime($filepath)),
                'timestamp' => filectime($filepath)
            ];
        }
    }
    // Ordenar de más reciente a más antiguo
    usort($backups, function($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
}
?>

<style>
    /* Reutilización y ajuste de estilos para la barra de herramientas y la lista de cartas de backup */
    .component-toolbar-wrapper { position: sticky; top: 16px; width: 100%; max-width: 425px; margin: 0 auto 24px auto; z-index: 15; }
    .component-toolbar { display: flex; align-items: center; justify-content: space-between; width: 100%; height: 56px; padding: 8px; background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); position: relative; }
    .toolbar-group { width: 100%; height: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .component-toolbar--primary { position: relative; z-index: 2; }
    .component-toolbar--secondary { position: absolute; top: calc(100% + 5px); left: 0; width: 100%; height: 56px; padding: 8px; background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-card); z-index: 10; opacity: 0; pointer-events: none; visibility: hidden; transform: translateY(-5px); transition: all 0.2s ease; }
    .component-toolbar--secondary.active { opacity: 1; pointer-events: auto; visibility: visible; transform: translateY(0); }
    .component-toolbar__side { display: flex; align-items: center; gap: 8px; }
    .selection-info-text { font-size: 14px; font-weight: 500; color: var(--text-primary); margin-right: 4px; }
    
    .component-users-list { display: flex; flex-direction: column; gap: 12px; }
    .component-user-card { background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; transition: border-color 0.2s, box-shadow 0.2s; cursor: pointer; }
    .component-user-card:hover { border-color: var(--text-primary); }
    .component-user-card.selected { border-color: var(--action-primary); box-shadow: 0 0 0 1px var(--action-primary); }
    .component-badge { display: inline-flex; align-items: center; gap: 6px; padding: 0 12px; min-height: 30px; background-color: transparent; border: 1px solid var(--border-transparent-20); border-radius: 50px; font-size: 13px; font-weight: 500; color: var(--text-secondary); white-space: nowrap; }
    .component-badge .material-symbols-rounded { font-size: 16px; }
</style>

<div class="view-content" id="admin-backups-view">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_admin" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="component-toolbar-wrapper">
            <div class="component-toolbar">
                <div class="toolbar-group component-toolbar--primary" id="toolbar-primary-bkp">
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.backups.tooltip_search') ?? 'Buscar' ?>" data-action="admin-toggle-search-bkp">
                            <span class="material-symbols-rounded">search</span>
                        </button>
                    </div>
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button primary" data-action="admin-create-backup">
                            <span class="material-symbols-rounded" style="margin-right: 4px;">add</span>
                            <?= t('admin.backups.btn_create') ?? 'Crear copia' ?>
                        </button>
                    </div>
                </div>

                <div class="toolbar-group component-toolbar--secondary" id="toolbar-search-bkp">
                    <div class="component-search" style="width: 100%;">
                        <div class="component-search-icon"><span class="material-symbols-rounded">search</span></div>
                        <div class="component-search-input">
                            <input type="text" class="component-search-input-field" placeholder="<?= t('admin.backups.search_placeholder') ?? 'Buscar por nombre...' ?>" id="admin-bkp-search-input">
                        </div>
                        <button type="button" class="component-button component-button--square-40" style="border: none; background: transparent;" data-action="admin-toggle-search-bkp">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>
                </div>

                <div class="toolbar-group component-toolbar--secondary" id="toolbar-selection-bkp">
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.backups.tooltip_restore') ?? 'Restaurar' ?>" data-action="admin-restore-backup" style="color: var(--color-success); border-color: var(--color-success);">
                            <span class="material-symbols-rounded">restore</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="<?= t('admin.backups.tooltip_delete') ?? 'Eliminar' ?>" data-action="admin-delete-backup" style="color: var(--color-error); border-color: var(--color-error);">
                            <span class="material-symbols-rounded">delete_forever</span>
                        </button>
                    </div>
                    <div class="component-toolbar__side">
                        <span class="selection-info-text"><?= t('admin.backups.selected', ['count' => 1]) ?? '1 seleccionado' ?></span>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Desmarcar" data-action="admin-clear-selection-bkp">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('admin.backups.title') ?? 'Copias de Seguridad' ?></h1>
            <p class="component-page-description"><?= t('admin.backups.desc') ?? 'Genera, restaura y administra los respaldos de la base de datos.' ?></p>
        </div>

        <div class="component-users-list" id="admin-backups-list">
            <?php if (empty($backups)): ?>
                <div id="admin-empty-state-bkp" style="padding: 24px; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <?= t('admin.backups.empty') ?? 'No hay copias de seguridad generadas en el servidor.' ?>
                </div>
            <?php else: ?>
                <?php foreach ($backups as $bkp): ?>
                    <div class="component-user-card js-admin-backup-card" data-filename="<?= htmlspecialchars($bkp['filename']) ?>">
                        
                        <div class="component-avatar component-avatar--sm" style="margin-right: 4px; background: transparent; border: 1px solid var(--border-color);" data-role="user">
                            <span class="material-symbols-rounded" style="color: var(--text-secondary); font-size: 24px;">database</span>
                        </div>
                        
                        <span class="component-badge" title="Nombre del archivo">
                            <span class="material-symbols-rounded">description</span>
                            <?= htmlspecialchars($bkp['filename']) ?>
                        </span>
                        
                        <span class="component-badge" title="Tamaño en disco">
                            <span class="material-symbols-rounded">hard_drive</span>
                            <?= $bkp['size'] ?> MB
                        </span>
                        
                        <span class="component-badge" title="Fecha de creación">
                            <span class="material-symbols-rounded">calendar_today</span>
                            <?= $bkp['created_at'] ?>
                        </span>
                        
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>