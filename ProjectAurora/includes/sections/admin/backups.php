<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/backups">
    
    <div class="toolbar-stack">
        
        <div class="component-toolbar" id="backup-toolbar-default">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions" data-i18n="admin.backups_title"><?php echo translation('admin.backups_title'); ?></span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" data-action="create-backup" 
                        data-i18n-tooltip="admin.backups.create_btn" 
                        data-tooltip="<?php echo translation('admin.backups.create_btn'); ?>">
                    <span class="material-symbols-rounded">add_circle</span>
                </button>
            </div>
        </div>

        <div class="component-toolbar d-none" id="backup-toolbar-selected">
            <div class="component-toolbar__group">
                <button class="component-icon-button" id="btn-restore-backup"
                        data-i18n-tooltip="admin.backups.restore_btn" 
                        data-tooltip="<?php echo translation('admin.backups.restore_btn'); ?>" 
                        style="color: #d32f2f; border-color: #ffcdd2;">
                    <span class="material-symbols-rounded">restore</span>
                </button>
                <div class="component-toolbar__separator"></div>
                <span style="font-size: 14px; font-weight: 600; color: #666;" id="selected-backup-name">...</span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" data-action="delete-backup"
                        data-i18n-tooltip="global.delete" 
                        data-tooltip="<?php echo translation('global.delete'); ?>">
                    <span class="material-symbols-rounded">delete</span>
                </button>
                <div class="component-toolbar__separator"></div>
                <button class="component-icon-button" data-action="deselect-backup" 
                        data-i18n-tooltip="global.deselect" 
                        data-tooltip="<?php echo translation('global.deselect'); ?>">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
        </div>

    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.backups_title"><?php echo translation('admin.backups_title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.backups_desc"><?php echo translation('admin.backups_desc'); ?></p>
        </div>

        <div class="component-table-container mt-16">
            <table class="component-table">
                <thead>
                    <tr>
                        <th data-i18n="admin.backups.filename"><?php echo translation('admin.backups.filename'); ?></th>
                        <th data-i18n="admin.backups.date"><?php echo translation('admin.backups.date'); ?></th>
                        <th data-i18n="admin.backups.size"><?php echo translation('admin.backups.size'); ?></th>
                    </tr>
                </thead>
                <tbody id="backups-table-body">
                    <tr>
                        <td colspan="3" class="component-table-empty">
                            <div class="small-spinner"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>