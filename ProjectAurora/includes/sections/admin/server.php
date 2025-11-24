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
$maxUsers = (int)$serverConfig['max_concurrent_users'];
?>
<div class="section-content active" data-section="admin/server">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.server_title"><?php echo trans('admin.server_title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.server_desc"><?php echo trans('admin.server_desc'); ?></p>
        </div>

        <div class="component-card component-card--grouped" style="border-color: #2196f3; background-color: #f0f7ff;">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-icon-container" style="background: #fff; border-color: #2196f3;">
                        <span class="material-symbols-rounded" style="color: #2196f3;">monitor_heart</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #0d47a1;">Monitor en Tiempo Real</h2>
                        <p class="component-card__description">Métricas en vivo del servidor WebSocket.</p>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; width: 100%; margin-top: 16px;">
                    <div style="background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #bbdefb; text-align: center;">
                        <span style="font-size: 12px; color: #666; font-weight: 600;">MÁXIMO</span>
                        <div style="font-size: 24px; font-weight: 700; color: #333;" id="debug-max-users"><?php echo $maxUsers; ?></div>
                    </div>
                    <div style="background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #bbdefb; text-align: center;">
                        <span style="font-size: 12px; color: #666; font-weight: 600;">TOTAL BD</span>
                        <div style="font-size: 24px; font-weight: 700; color: #333;" id="debug-db-sessions">-</div>
                    </div>
                    <div style="background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #bbdefb; text-align: center;">
                        <span style="font-size: 12px; color: #666; font-weight: 600;">EN COLA</span>
                        <div style="font-size: 24px; font-weight: 700; color: #e65100;" id="debug-queue-len">-</div>
                    </div>
                    <div style="background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #bbdefb; text-align: center;">
                        <span style="font-size: 12px; color: #666; font-weight: 600;">JUGANDO</span>
                        <div style="font-size: 24px; font-weight: 700; color: #2e7d32;" id="debug-real-users">-</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--column">
            <div class="component-card__content w-100">
                <div class="component-card__text">
                    <h2 class="component-card__title">Registro de Logs (Live)</h2>
                    <p class="component-card__description">Actividad reciente del servidor de colas.</p>
                </div>
            </div>
            
            <div id="server-log-console" style="
                width: 100%;
                height: 200px;
                background-color: #1e1e1e;
                color: #00ff00;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                padding: 12px;
                border-radius: 8px;
                overflow-y: auto;
                margin-top: 8px;
                border: 1px solid #333;
                display: flex;
                flex-direction: column;
                gap: 4px;
            ">
                <span style="color: #666;">Esperando conexión con logs...</span>
            </div>
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

        <div class="component-card component-card--column active">
            <div class="component-card__content w-100">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="admin.server.maxConcurrentUsersTitle">
                        <?php echo trans('admin.server.maxConcurrentUsersTitle'); ?>
                    </h2>
                    <p class="component-card__description" data-i18n="admin.server.maxConcurrentUsersDesc">
                        <?php echo trans('admin.server.maxConcurrentUsersDesc'); ?>
                    </p>
                </div>
            </div>
            <div class="component-card__actions w-100">
                <div class="component-stepper component-stepper--multi" style="max-width: 265px;" 
                     data-action="update-max-concurrent-users" 
                     data-current-value="<?php echo $maxUsers; ?>" 
                     data-min="1" 
                     data-max="5000">
                    
                    <button type="button" class="stepper-button" data-step-action="decrement-10">
                        <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                    </button>
                    <button type="button" class="stepper-button" data-step-action="decrement-1">
                        <span class="material-symbols-rounded">chevron_left</span>
                    </button>
                    
                    <div class="stepper-value"><?php echo $maxUsers; ?></div>
                    
                    <button type="button" class="stepper-button" data-step-action="increment-1">
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                    <button type="button" class="stepper-button" data-step-action="increment-10">
                        <span class="material-symbols-rounded">keyboard_double_arrow_right</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>