<?php
// includes/sections/admin/alerts.php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/alerts">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions" data-i18n="admin.alerts_title"><?php echo translation('admin.alerts_title'); ?></span>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="admin.alerts_title"><?php echo translation('admin.alerts_title'); ?></h1>
            <p class="component-page-description" data-i18n="admin.alerts_desc"><?php echo translation('admin.alerts_desc'); ?></p>
        </div>

        <div class="mt-16">
            <div id="active-alert-indicator" class="component-card component-card--danger d-none mb-16" style="border-color: #2e7d32; background-color: #e8f5e9;">
                <div class="component-card__content" style="align-items: center;">
                    <span class="material-symbols-rounded" style="color: #2e7d32; font-size: 32px;">broadcast_on_personal</span>
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #2e7d32;">
                            <span data-i18n="admin.alerts.active_label"><?php echo translation('admin.alerts.active_label'); ?></span>: 
                            <span id="active-alert-name">...</span>
                        </h2>
                        <p class="component-card__description" style="color: #1b5e20;">
                            Esta alerta está siendo mostrada a todos los usuarios conectados.
                        </p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button class="component-button danger" data-action="stop-alert" data-i18n="admin.alerts.stop_btn">
                        <?php echo translation('admin.alerts.stop_btn'); ?>
                    </button>
                </div>
            </div>

            <div class="component-card component-card--grouped">
                <input type="hidden" id="input-alert-type" value="">

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-i18n="admin.alerts.select_title">Seleccionar Alerta</h2>
                            <p class="component-card__description" data-i18n="admin.alerts.select_desc">Elige el tipo de mensaje global que verán los usuarios.</p>
                        </div>
                    </div>
                    
                    <div class="component-card__actions w-100">
                        <div class="trigger-select-wrapper w-100">
                            <div class="trigger-selector" data-action="toggle-dropdown" data-target="dropdown-alert-types">
                                <div class="trigger-select-icon">
                                    <span class="material-symbols-rounded" id="current-alert-icon">campaign</span>
                                </div>
                                <div class="trigger-select-text">
                                    <span id="current-alert-text">Selecciona una alerta...</span>
                                </div>
                                <div class="trigger-select-arrow">
                                    <span class="material-symbols-rounded">arrow_drop_down</span>
                                </div>
                            </div>

                            <div class="popover-module popover-module--anchor-width body-title disabled" id="dropdown-alert-types">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <?php
                                        $templates = [
                                            ['id' => 'maintenance_warning', 'icon' => 'engineering', 'color' => '#f57c00'],
                                            ['id' => 'high_traffic', 'icon' => 'dns', 'color' => '#212121'],
                                            ['id' => 'critical_issue', 'icon' => 'report', 'color' => '#d32f2f'],
                                            ['id' => 'update_info', 'icon' => 'info', 'color' => '#1976d2']
                                        ];

                                        foreach ($templates as $tpl) {
                                            $titleKey = "admin.alerts.templates.{$tpl['id']}.title";
                                            $titleVal = translation($titleKey);
                                            ?>
                                            <div class="menu-link" 
                                                 data-action="select-alert-option" 
                                                 data-value="<?php echo $tpl['id']; ?>"
                                                 data-label="<?php echo $titleVal; ?>"
                                                 data-icon="<?php echo $tpl['icon']; ?>"
                                                 data-color="<?php echo $tpl['color']; ?>">
                                                <div class="menu-link-icon">
                                                    <span class="material-symbols-rounded" style="color: <?php echo $tpl['color']; ?>"><?php echo $tpl['icon']; ?></span>
                                                </div>
                                                <div class="menu-link-text">
                                                    <?php echo $titleVal; ?>
                                                </div>
                                                <div class="menu-link-icon"></div>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                         <div class="component-card__text">
                            <h2 class="component-card__title">Vista Previa</h2>
                            <p class="component-card__description" id="alert-preview-desc">Selecciona un tipo para ver la descripción.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <button class="component-button primary" id="btn-emit-selected-alert" disabled>
                            <span data-i18n="admin.alerts.emit_btn"><?php echo translation('admin.alerts.emit_btn'); ?></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>