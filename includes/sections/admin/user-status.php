<?php
// includes/sections/admin/user-status.php

require_once __DIR__ . '/../../libs/Utils.php';
require_once __DIR__ . '/../../../api/services/AdminService.php';

$targetId = $_GET['id'] ?? null;
if (!$targetId) {
    echo "<script>window.location.href = '?page=admin/users';</script>";
    exit;
}

$currentAdminId = $_SESSION['user_id'] ?? 0; 
$adminService = new AdminService($pdo, $i18n, $currentAdminId);
$response = $adminService->getUserDetails($targetId);
$user = $response['success'] ? $response['user'] : null;

if (!$user) {
    echo $i18n->t('error.user_not_found');
    exit;
}

// === PREPARAR DATOS PARA JS ===
$suspensionData = [
    'status' => $user['account_status'],
    'reason' => $user['status_reason'] ?? '',
    'suspension_ends_at' => $user['suspension_ends_at']
];
?>

<div class="component-wrapper" data-section="admin-user-status" data-user-id="<?php echo htmlspecialchars($user['id']); ?>">

    <script type="application/json" id="server-status-data">
        <?php echo json_encode($suspensionData); ?>
    </script>

    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <?php echo $i18n->t('admin.user_status.title'); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" style="cursor: help; border-color: transparent;" data-tooltip="<?php echo $i18n->t('admin.audit_active_tooltip'); ?>">
                        <span class="material-symbols-rounded" style="color: var(--text-secondary);">history_edu</span>
                    </button>

                    <button class="component-button primary" id="btn-save-status" disabled>
                        <?php echo $i18n->t('global.save'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-header-card">
        <h1 class="component-page-title"><?php echo sprintf($i18n->t('admin.user_status.header_user'), htmlspecialchars($user['username'])); ?></h1>
        <p class="component-page-description"><?php echo $i18n->t('admin.user_status.desc'); ?></p>
    </div>

    <div class="component-card component-card--grouped">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('admin.user_status.account_status.title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('admin.user_status.account_status.desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">gpp_maybe</span>
                        <span class="trigger-select-text" data-element="current-status-label"><?php echo $i18n->t('admin.user_status.select_status'); ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    
                    <div class="popover-module">
                        <div class="menu-list">
                            <div class="menu-link" data-action="select-option" data-type="status" data-value="active" data-label="<?php echo $i18n->t('admin.user_status.status.active'); ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">check_circle</span></div>
                                <div class="menu-link-text"><?php echo $i18n->t('admin.user_status.status.active'); ?></div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="status" data-value="suspended" data-label="<?php echo $i18n->t('admin.user_status.status.suspended'); ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">block</span></div>
                                <div class="menu-link-text"><?php echo $i18n->t('admin.user_status.status.suspended'); ?></div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="status" data-value="deleted" data-label="<?php echo $i18n->t('admin.user_status.status.deleted'); ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">delete</span></div>
                                <div class="menu-link-text"><?php echo $i18n->t('admin.user_status.status.deleted'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-suspension-type">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('admin.user_status.suspension_type.title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('admin.user_status.suspension_type.desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">timer</span>
                        <span class="trigger-select-text" data-element="suspension-type-label"><?php echo $i18n->t('admin.user_status.select_type'); ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="popover-module">
                        <div class="menu-list">
                            <div class="menu-link" data-action="select-option" data-type="suspension_type" data-value="temp" data-label="<?php echo $i18n->t('admin.user_status.suspension_type.temp'); ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">timelapse</span></div>
                                <div class="menu-link-text"><?php echo $i18n->t('admin.user_status.suspension_type.temp'); ?></div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="suspension_type" data-value="perm" data-label="<?php echo $i18n->t('admin.user_status.suspension_type.perm'); ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">lock_forever</span></div>
                                <div class="menu-link-text"><?php echo $i18n->t('admin.user_status.suspension_type.perm'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-suspension-days">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('admin.user_status.end_date.title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('admin.user_status.end_date.desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="component-datepicker-wrapper" id="suspension-picker-wrapper" style="position: relative;">
                    <input type="hidden" id="suspension-date-input" name="suspension_ends_at">
                    
                    <div class="trigger-selector" style="cursor: pointer;">
                        <span class="material-symbols-rounded trigger-select-icon">calendar_month</span>
                        <span class="trigger-select-text" id="suspension-date-label"><?php echo $i18n->t('admin.user_status.select_date'); ?></span>
                        <span class="material-symbols-rounded" style="font-size: 18px;">edit_calendar</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-deletion-source">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('admin.user_status.decision.title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('admin.user_status.decision.desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">person</span>
                        <span class="trigger-select-text" data-element="deletion-source-label"><?php echo $i18n->t('admin.user_status.select_source'); ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="popover-module">
                        <div class="menu-list">
                            <div class="menu-link" data-action="select-option" data-type="deletion_source" data-value="user" data-label="<?php echo $i18n->t('admin.user_status.decision.user'); ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">person_remove</span></div>
                                <div class="menu-link-text"><?php echo $i18n->t('admin.user_status.decision.user'); ?></div>
                            </div>
                            <div class="menu-link" data-action="select-option" data-type="deletion_source" data-value="admin" data-label="<?php echo $i18n->t('admin.user_status.decision.admin'); ?>">
                                <div class="menu-link-icon"><span class="material-symbols-rounded">gavel</span></div>
                                <div class="menu-link-text"><?php echo $i18n->t('admin.user_status.decision.admin'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-4 d-none" id="group-reason">
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title"><?php echo $i18n->t('admin.user_status.reason.title'); ?></h2>
                    <p class="component-card__description"><?php echo $i18n->t('admin.user_status.reason.desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">description</span>
                        <span class="trigger-select-text" data-element="reason-label"><?php echo $i18n->t('admin.user_status.select_reason'); ?></span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    <div class="popover-module popover-module--searchable">
                         <div class="menu-content menu-content--flush">
                            <div class="menu-search-header">
                                <div class="component-input-wrapper">
                                    <input type="text" class="component-text-input component-text-input--sm" placeholder="<?php echo $i18n->t('global.search'); ?>" data-action="filter-reason"> 
                                </div>
                            </div>
                            <div class="menu-list menu-list--scrollable overflow-y" id="reason-list-container">
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>