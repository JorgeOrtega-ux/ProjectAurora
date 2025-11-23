<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['user_role'], ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php';
    exit;
}

$targetUid = $_GET['uid'] ?? 0;
$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/user-history">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/user-status?uid=<?php echo htmlspecialchars($targetUid); ?>" data-tooltip="Volver a Estado">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions">Historial de Sanciones</span>
            </div>
        </div>
    </div>

    <div class="section-center-wrapper section-with-toolbar admin-users-wrapper">
        
        <input type="hidden" id="history-target-id" value="<?php echo htmlspecialchars($targetUid); ?>">

        <div class="component-header-card w-100" style="margin-bottom: 16px; display: flex; align-items: center; gap: 16px; text-align: left; padding: 16px;">
            <div class="user-table-avatar" id="history-avatar-container" style="width: 48px; height: 48px;">
                <img src="" id="history-user-avatar" style="display: none;">
                <div class="user-avatar-placeholder" id="history-user-icon">
                    <span class="material-symbols-rounded avatar-icon" style="font-size: 24px;">person</span>
                </div>
            </div>
            <div>
                <h1 class="component-page-title" id="history-username" style="font-size: 20px; margin: 0;">Cargando...</h1>
                <p class="component-page-description" id="history-email" style="font-size: 13px;">...</p>
            </div>
        </div>

        <div class="component-table-container">
            <table class="component-table">
                <thead>
                    <tr>
                        <th>Fecha Inicio</th>
                        <th>Razón</th>
                        <th>Duración</th>
                        <th>Fecha Fin (Original)</th>
                        <th>Admin (Sancionador)</th>
                        <th>Estado Final</th>
                    </tr>
                </thead>
                <tbody id="full-history-body">
                    <tr>
                        <td colspan="6" class="component-table-empty">
                            <div class="loader-spinner" style="margin: 20px auto; width: 30px; height: 30px; border-width: 3px;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="<?php echo $basePath; ?>assets/js/modules/admin-history.js"></script>