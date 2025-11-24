<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

$serverConfig = getServerConfig($pdo);
$maintMode = (int)$serverConfig['maintenance_mode'];
$regMode = (int)$serverConfig['allow_registrations'];
?>
<div class="section-content active" data-section="admin/server">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.server_title"><?php echo trans('admin.server_title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.server_desc"><?php echo trans('admin.server_desc'); ?></p>
        </div>

        <div class="component-card component-card--edit-mode active">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="admin.server.maintenanceTitle">
                        <?php echo trans('admin.server.maintenanceTitle'); ?>
                    </h2>
                    <p class="component-card__description" data-i18n="admin.server.maintenanceDesc">
                        <?php echo trans('admin.server.maintenanceDesc'); ?>
                    </p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" id="toggle-maintenance-mode" data-action="update-maintenance-mode" 
                           <?php echo ($maintMode === 1) ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="component-card component-card--edit-mode active">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="admin.server.registrationTitle">
                        <?php echo trans('admin.server.registrationTitle'); ?>
                    </h2>
                    <p class="component-card__description" data-i18n="admin.server.registrationDesc">
                        <?php echo trans('admin.server.registrationDesc'); ?>
                    </p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" id="toggle-allow-registration" data-action="update-registration-mode" 
                           <?php echo ($regMode === 1) ? 'checked' : ''; ?>>
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

    </div>
</div>