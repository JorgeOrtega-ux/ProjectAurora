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

            <div class="dashboard-stats-grid">
                <?php
                $templates = [
                    ['id' => 'maintenance_warning', 'icon' => 'engineering', 'color' => '#f57c00', 'bg' => '#fff3e0'],
                    ['id' => 'high_traffic', 'icon' => 'dns', 'color' => '#d32f2f', 'bg' => '#ffebee'],
                    ['id' => 'critical_issue', 'icon' => 'report', 'color' => '#c62828', 'bg' => '#ffcdd2'],
                    ['id' => 'update_info', 'icon' => 'info', 'color' => '#1976d2', 'bg' => '#e3f2fd']
                ];

                foreach ($templates as $tpl) {
                    $titleKey = "admin.alerts.templates.{$tpl['id']}.title";
                    $descKey = "admin.alerts.templates.{$tpl['id']}.desc";
                    ?>
                    <div class="component-card component-card--column alert-template-card" data-template-id="<?php echo $tpl['id']; ?>">
                        <div class="component-card__content" style="width:100%; gap:12px;">
                            <div class="component-icon-container" style="background-color: <?php echo $tpl['bg']; ?>; border-color: <?php echo $tpl['bg']; ?>;">
                                <span class="material-symbols-rounded" style="color: <?php echo $tpl['color']; ?>;"><?php echo $tpl['icon']; ?></span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title" data-i18n="<?php echo $titleKey; ?>">
                                    <?php echo translation($titleKey); ?>
                                </h2>
                                <p class="component-card__description" data-i18n="<?php echo $descKey; ?>">
                                    <?php echo translation($descKey); ?>
                                </p>
                            </div>
                        </div>
                        <div class="component-card__actions w-100" style="justify-content: flex-end;">
                            <button class="component-button primary btn-emit-alert" 
                                    data-action="emit-alert" 
                                    data-id="<?php echo $tpl['id']; ?>"
                                    data-i18n="admin.alerts.emit_btn">
                                <?php echo translation('admin.alerts.emit_btn'); ?>
                            </button>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

    </div>
</div>