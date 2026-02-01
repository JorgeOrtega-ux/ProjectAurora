<?php
// includes/sections/admin/dashboard.php
?>
<div class="component-wrapper component-wrapper--full" data-section="admin-dashboard">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <?php echo $i18n->t('menu.admin.dashboard'); ?>
                    </div>
                </div>

               <div class="component-toolbar__side component-toolbar__side--right">
                    
                    <button class="header-button" data-nav="admin/log-files" data-tooltip="<?php echo $i18n->t('admin.dashboard.nav.logs'); ?>">
                        <span class="material-symbols-rounded">description</span>
                    </button>

                    <button class="header-button" data-nav="admin/audit-log" data-tooltip="<?php echo $i18n->t('admin.dashboard.nav.audit'); ?>">
                        <span class="material-symbols-rounded">history_edu</span>
                    </button>

                    <button class="header-button" data-nav="admin/alerts" data-tooltip="<?php echo $i18n->t('admin.dashboard.nav.alerts'); ?>">
                        <span class="material-symbols-rounded">campaign</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="component-dashboard-grid mt-4">
        
        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.dashboard.stats.total_users'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">group</span>
            </div>
            <div class="component-stat-main-value" data-stat="total_users">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral" data-trend="total_users">
                    <span class="material-symbols-rounded" style="font-size:14px;">remove</span> 0%
                </span>
                <span class="component-trend-text"><?php echo $i18n->t('admin.dashboard.stats.vs_last_month'); ?></span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.dashboard.stats.new_today'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">person_add</span>
            </div>
            <div class="component-stat-main-value" data-stat="new_users_today">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral" data-trend="new_users_today">
                    <span class="material-symbols-rounded" style="font-size:14px;">remove</span> 0%
                </span>
                <span class="component-trend-text"><?php echo $i18n->t('admin.dashboard.stats.vs_yesterday'); ?></span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.dashboard.stats.online_now'); ?></span>
                <span class="material-symbols-rounded component-stat-icon" style="color:var(--color-success);">wifi</span>
            </div>
            <div class="component-stat-main-value" data-stat="online_total">...</div>
            <div class="component-stat-footer" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                <div style="font-size:11px; color:var(--text-secondary); display:flex; justify-content:space-between; width:100%;">
                    <span><?php echo $i18n->t('admin.dashboard.stats.users'); ?>:</span> <strong style="color:var(--text-primary);" data-stat="online_users">0</strong>
                </div>
                <div style="font-size:11px; color:var(--text-secondary); display:flex; justify-content:space-between; width:100%;">
                    <span><?php echo $i18n->t('admin.dashboard.stats.guests'); ?>:</span> <strong style="color:var(--text-primary);" data-stat="online_guests">0</strong>
                </div>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.dashboard.stats.alerts'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">notifications_active</span>
            </div>
            <div class="component-stat-main-value" data-stat="system_activity">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text"><?php echo $i18n->t('admin.dashboard.stats.events_today'); ?></span>
            </div>
        </div>

    </div>

</div>