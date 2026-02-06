<?php
// includes/sections/admin/redis-manager.php
?>
<div class="component-wrapper component-wrapper--full component-wrapper--flex" data-section="admin-redis-manager">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">
                        <?php echo $i18n->t('admin.redis.title'); ?>
                    </div>
                </div>

                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" data-action="refresh-all" data-tooltip="<?php echo $i18n->t('admin.redis.refresh'); ?>">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                    <button class="header-button" data-action="flush-db" data-tooltip="<?php echo $i18n->t('admin.redis.flush'); ?>" style="color: var(--color-error); border-color: rgba(211, 47, 47, 0.3);">
                        <span class="material-symbols-rounded">delete_forever</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="component-dashboard-grid mt-4" id="redis-stats-container">
        
        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.redis.stats.version'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">info</span>
            </div>
            <div class="component-stat-main-value" data-stat="version">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral"><?php echo $i18n->t('admin.redis.stats.stable'); ?></span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.redis.stats.uptime'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">timer</span>
            </div>
            <div class="component-stat-main-value" data-stat="uptime" style="font-size: 1.1rem; line-height: 1.4;">...</div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.redis.stats.memory'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">memory</span>
            </div>
            <div class="component-stat-main-value" data-stat="memory_used">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text"><?php echo $i18n->t('admin.redis.stats.peak'); ?> <strong data-stat="memory_peak">...</strong></span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.redis.stats.connections'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">hub</span>
            </div>
            <div class="component-stat-main-value" data-stat="connected_clients">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text"><?php echo $i18n->t('admin.redis.stats.clients'); ?></span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?php echo $i18n->t('admin.redis.stats.keys'); ?></span>
                <span class="material-symbols-rounded component-stat-icon">vpn_key</span>
            </div>
            <div class="component-stat-main-value" data-stat="total_keys">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text"><?php echo $i18n->t('admin.redis.stats.db_current'); ?></span>
            </div>
        </div>

    </div>

    <div class="component-card component-card--fill mt-4">
        
        <div class="component-toolbar-internal">
            <div class="component-input-wrapper flex-1">
                <input type="text" class="component-text-input" id="redis-search-input" placeholder="<?php echo $i18n->t('admin.redis.search_placeholder'); ?>" value="*">
            </div>
            <button class="component-button primary" id="btn-redis-search">
                <span class="material-symbols-rounded">search</span>
            </button>
        </div>

        <div class="component-table-wrapper component-table-scrollable">
            <table class="component-table" id="redis-keys-table">
                <thead class="component-table-sticky-header">
                    <tr>
                        <th><?php echo $i18n->t('admin.redis.table.key'); ?></th>
                        <th class="w-80"><?php echo $i18n->t('admin.redis.table.type'); ?></th>
                        <th class="w-80"><?php echo $i18n->t('admin.redis.table.ttl'); ?></th>
                        <th class="w-60"></th>
                    </tr>
                </thead>
                <tbody id="redis-keys-body">
                    </tbody>
            </table>
        </div>
        
        <div id="redis-loading" class="state-loading d-none">
            <div class="spinner-sm"></div>
            <p class="state-text"><?php echo $i18n->t('admin.redis.loading'); ?></p>
        </div>
    </div>

</div>