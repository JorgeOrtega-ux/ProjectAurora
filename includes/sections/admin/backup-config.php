<?php
// includes/sections/admin/backup-config.php
?>
<div class="component-wrapper" data-section="admin-backup-config">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title"><?php echo $i18n->t('admin.backup_config.toolbar_title'); ?></div>
                </div>
                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" id="btn-save-backup-config">
                        <span class="material-symbols-rounded">save</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-header-card">
        <h1 class="component-page-title"><?php echo $i18n->t('admin.backup_config.title'); ?></h1>
        <p class="component-page-description"><?php echo $i18n->t('admin.backup_config.desc'); ?></p>
    </div>

    <div id="config-loading-state" class="state-loading">
        <div class="spinner-sm"></div>
        <p class="state-text"><?php echo $i18n->t('admin.backup_config.loading'); ?></p>
    </div>

    <div id="config-content-area" class="d-none">

        <div class="component-dashboard-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            
            <div class="component-stat-card">
                <div class="component-stat-header">
                    <span class="component-stat-title"><?php echo $i18n->t('admin.backup_config.stats.next_run'); ?></span>
                    <span class="material-symbols-rounded component-stat-icon" style="color: var(--color-primary);">timer</span>
                </div>
                <div class="component-stat-main-value" id="stat-countdown" style="font-feature-settings: 'tnum';">--:--:--</div>
                <div class="component-stat-footer">
                    <span class="component-trend-text" id="stat-next-date"><?php echo $i18n->t('admin.backup_config.stats.calculating'); ?></span>
                </div>
            </div>

            <div class="component-stat-card">
                <div class="component-stat-header">
                    <span class="component-stat-title"><?php echo $i18n->t('admin.backup_config.stats.last_run'); ?></span>
                    <span class="material-symbols-rounded component-stat-icon">history</span>
                </div>
                <div class="component-stat-main-value" style="font-size: 1.5rem;" id="stat-last-run">...</div>
                <div class="component-stat-footer">
                    <span class="component-trend-badge success" id="stat-status-badge">
                        <span class="material-symbols-rounded" style="font-size:14px;">check_circle</span> <?php echo $i18n->t('admin.backup_config.stats.active'); ?>
                    </span>
                </div>
            </div>

            <div class="component-stat-card" style="background: var(--bg-surface-2); border: 1px dashed var(--border-light);">
                <div class="component-stat-header">
                    <span class="component-stat-title"><?php echo $i18n->t('admin.backup_config.stats.quick_actions'); ?></span>
                    <span class="material-symbols-rounded component-stat-icon">bolt</span>
                </div>
                <div style="flex-grow: 1; display: flex; align-items: center;">
                    <button class="component-button secondary w-100" id="btn-trigger-now">
                        <span class="material-symbols-rounded">play_arrow</span>
                        <?php echo $i18n->t('admin.backup_config.btn_trigger'); ?>
                    </button>
                </div>
                <div class="component-stat-footer">
                    <span class="component-trend-text"><?php echo $i18n->t('admin.backup_config.trigger_desc'); ?></span>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped mt-4">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">schedule</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.backup_config.enable.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.backup_config.enable.desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="input-auto-enabled">
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content w-100">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.backup_config.frequency.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.backup_config.frequency.desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="component-stepper w-100" data-role="stepper" data-step-small="1" data-step-large="6">
                        <div class="component-stepper__side">
                            <button type="button" class="component-button square" data-action="dec-large" title="-6">
                                <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                            </button>
                            <button type="button" class="component-button square" data-action="dec-small" title="-1">
                                <span class="material-symbols-rounded">chevron_left</span>
                            </button>
                        </div>
                        <div class="component-stepper__center">
                            <input type="number" class="component-stepper__input" id="input-frequency" value="24" min="1">
                        </div>
                        <div class="component-stepper__side">
                            <button type="button" class="component-button square" data-action="inc-small" title="+1">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </button>
                            <button type="button" class="component-button square" data-action="inc-large" title="+6">
                                <span class="material-symbols-rounded">keyboard_double_arrow_right</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content w-100">
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.backup_config.retention.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.backup_config.retention.desc'); ?></p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="component-stepper w-100" data-role="stepper" data-step-small="1" data-step-large="5">
                        <div class="component-stepper__side">
                            <button type="button" class="component-button square" data-action="dec-large" title="-5">
                                <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                            </button>
                            <button type="button" class="component-button square" data-action="dec-small" title="-1">
                                <span class="material-symbols-rounded">chevron_left</span>
                            </button>
                        </div>
                        <div class="component-stepper__center">
                            <input type="number" class="component-stepper__input" id="input-retention" value="10" min="1">
                        </div>
                        <div class="component-stepper__side">
                            <button type="button" class="component-button square" data-action="inc-small" title="+1">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </button>
                            <button type="button" class="component-button square" data-action="inc-large" title="+5">
                                <span class="material-symbols-rounded">keyboard_double_arrow_right</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>