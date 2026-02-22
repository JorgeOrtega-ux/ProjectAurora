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

<div class="view-content" id="admin-backups-view">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_admin" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="component-toolbar-wrapper">
            <div class="component-toolbar">
                <div class="toolbar-group component-toolbar--primary" id="toolbar-primary-bkp">
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Cambiar Vista" data-action="admin-toggle-view">
                            <span class="material-symbols-rounded" id="view-toggle-icon-bkp">table_rows</span>
                        </button>
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
                        <span class="selection-info-text" id="selection-count-bkp"><?= t('admin.backups.selected', ['count' => 1]) ?? '1 seleccionado' ?></span>
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

        <div class="component-data-list" id="admin-data-list-backups">
            <div class="component-data-list-header">
                <div>Tipo</div>
                <div>Archivo</div>
                <div>Tamaño</div>
                <div>Fecha de Creación</div>
            </div>

            <?php if (empty($backups)): ?>
                <div id="admin-empty-state-bkp" style="padding: 24px; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <?= t('admin.backups.empty') ?? 'No hay copias de seguridad generadas en el servidor.' ?>
                </div>
            <?php else: ?>
                <?php foreach ($backups as $bkp): ?>
                    <div class="component-data-card js-admin-backup-card" data-filename="<?= htmlspecialchars($bkp['filename']) ?>">
                        
                        <div class="component-data-column">
                            <div class="component-avatar component-avatar--sm" style="background: transparent; border: 1px solid var(--border-color);" data-role="user">
                                <span class="material-symbols-rounded" style="color: var(--text-secondary); font-size: 24px;">database</span>
                            </div>
                        </div>
                        
                        <div class="component-data-column">
                            <span class="component-badge" title="Nombre del archivo">
                                <span class="material-symbols-rounded">description</span>
                                <span><?= htmlspecialchars($bkp['filename']) ?></span>
                            </span>
                        </div>
                        
                        <div class="component-data-column">
                            <span class="component-badge" title="Tamaño en disco">
                                <span class="material-symbols-rounded">hard_drive</span>
                                <span><?= $bkp['size'] ?> MB</span>
                            </span>
                        </div>
                        
                        <div class="component-data-column">
                            <span class="component-badge" title="Fecha de creación">
                                <span class="material-symbols-rounded">calendar_today</span>
                                <span><?= $bkp['created_at'] ?></span>
                            </span>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>