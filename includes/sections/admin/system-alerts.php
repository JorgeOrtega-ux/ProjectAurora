<div class="component-wrapper" data-section="admin-system-alerts">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title"><?= $i18n->t('admin.alerts.title') ?></div>
                </div>
                <div class="component-toolbar__side component-toolbar__side--right" style="gap: 12px; align-items: center;">
                    <button class="header-button" data-action="emit-alert" data-tooltip="<?= $i18n->t('admin.alerts.btn_emit') ?>">
                        <span class="material-symbols-rounded">campaign</span>
                    </button>
                    <div class="component-divider-vertical" style="height: 24px; border-left: 1px solid var(--border-light); margin: 0 4px;"></div>
                    <button class="header-button" data-action="refresh-status" data-tooltip="<?= $i18n->t('admin.alerts.btn_refresh') ?>">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-dashboard-grid mt-4">
        
        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?= $i18n->t('admin.alerts.stat_reach') ?></span>
                <span class="material-symbols-rounded component-stat-icon" style="color: var(--primary-color);">group_add</span>
            </div>
            <div class="component-stat-main-value" data-stat="online-users">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text"><?= $i18n->t('admin.alerts.stat_reach_desc') ?></span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?= $i18n->t('admin.alerts.stat_today') ?></span>
                <span class="material-symbols-rounded component-stat-icon">today</span>
            </div>
            <div class="component-stat-main-value" data-stat="alerts-today">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral" data-stat="badge-total">
                    <span class="material-symbols-rounded" style="font-size:14px;">history</span> Total: 0
                </span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title"><?= $i18n->t('admin.alerts.stat_impact') ?></span>
                <span class="material-symbols-rounded component-stat-icon" data-stat="impact-icon">monitor_heart</span>
            </div>
            <div class="component-stat-main-value" data-stat="last-severity" style="font-size: 1.5rem;"><?= $i18n->t('admin.alerts.stat_none') ?></div>
            <div class="component-stat-footer">
                <span class="component-trend-text" data-stat="last-time"><?= $i18n->t('admin.alerts.stat_none') ?></span>
            </div>
        </div>

        <div class="component-stat-card" data-element="card-status-indicator" style="border-left: 4px solid var(--color-success);">
            <div class="component-stat-header">
                <span class="component-stat-title"><?= $i18n->t('admin.alerts.stat_status') ?></span>
                <span class="material-symbols-rounded component-stat-icon" data-stat="active-icon" style="color:var(--color-success);">check_circle</span>
            </div>
            <div class="component-stat-main-value" style="font-size: 1.2rem;" data-stat="active-text"><?= $i18n->t('admin.alerts.stat_operational') ?></div>
            <div class="component-stat-footer">
                 <button class="component-button danger small" data-action="deactivate-alert-mini" style="display: none; width: 100%; justify-content: center;">
                    <?= $i18n->t('admin.alerts.btn_deactivate') ?>
                </button>
            </div>
        </div>

    </div>

    <div class="component-card component-card--grouped mt-4">
        
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <span class="component-card__title"><?= $i18n->t('admin.alerts.cat_title') ?></span>
                    <span class="component-card__description"><?= $i18n->t('admin.alerts.cat_desc') ?></span>
                </div>
            </div>
            
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector" data-selector="alert-type">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-rounded" data-preview="icon-type">speed</span>
                            <span class="trigger-select-text" data-preview="text-type"><?= $i18n->t('admin.alerts.type_perf') ?></span>
                        </div>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>

                    <div class="popover-module">
                        <div class="menu-list">
                            <div class="menu-link active" data-action="select-main-type" data-value="performance">
                                <span class="material-symbols-rounded menu-link-icon">speed</span>
                                <span class="menu-link-text"><?= $i18n->t('admin.alerts.type_perf') ?></span>
                                <div class="radio-indicator"></div>
                            </div>
                            <div class="menu-link" data-action="select-main-type" data-value="maintenance">
                                <span class="material-symbols-rounded menu-link-icon">build</span>
                                <span class="menu-link-text"><?= $i18n->t('admin.alerts.type_maint') ?></span>
                                <div class="radio-indicator"></div>
                            </div>
                            <div class="menu-link" data-action="select-main-type" data-value="policy">
                                <span class="material-symbols-rounded menu-link-icon">policy</span>
                                <span class="menu-link-text"><?= $i18n->t('admin.alerts.type_policy') ?></span>
                                <div class="radio-indicator"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="config-group" data-group="performance">
            <hr class="component-divider">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <span class="component-card__title"><?= $i18n->t('admin.alerts.diag_title') ?></span>
                        <span class="component-card__description"><?= $i18n->t('admin.alerts.diag_desc') ?></span>
                    </div>
                </div>
                
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector" data-selector="perf-msg">
                             <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="material-symbols-rounded" data-preview="icon-perf">troubleshoot</span>
                                <span class="trigger-select-text" data-preview="text-perf"><?= $i18n->t('admin.alerts.perf_deg') ?></span>
                            </div>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-perf-msg" data-value="degradation">
                                    <span class="material-symbols-rounded menu-link-icon">troubleshoot</span>
                                    <span class="menu-link-text"><?= $i18n->t('admin.alerts.perf_deg') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                                <div class="menu-link" data-action="select-perf-msg" data-value="latency">
                                    <span class="material-symbols-rounded menu-link-icon">network_check</span>
                                    <span class="menu-link-text"><?= $i18n->t('admin.alerts.perf_lat') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                                <div class="menu-link" data-action="select-perf-msg" data-value="overload">
                                    <span class="material-symbols-rounded menu-link-icon">memory</span>
                                    <span class="menu-link-text"><?= $i18n->t('admin.alerts.perf_over') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="config-group" data-group="maintenance" style="display: none;">
            <hr class="component-divider">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <span class="component-card__title"><?= $i18n->t('admin.alerts.mode_title') ?></span>
                        <span class="component-card__description"><?= $i18n->t('admin.alerts.mode_desc') ?></span>
                    </div>
                </div>
                
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector" data-selector="maint-type">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="material-symbols-rounded" data-preview="icon-maint">event</span>
                                <span class="trigger-select-text" data-preview="text-maint"><?= $i18n->t('admin.alerts.mode_sched') ?></span>
                            </div>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-maint-type" data-value="scheduled">
                                    <span class="material-symbols-rounded menu-link-icon">event</span>
                                    <span class="menu-link-text"><?= $i18n->t('admin.alerts.mode_sched') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                                <div class="menu-link" style="color: var(--color-error);" data-action="select-maint-type" data-value="emergency">
                                    <span class="material-symbols-rounded menu-link-icon">warning</span>
                                    <span class="menu-link-text"><?= $i18n->t('admin.alerts.mode_emerg') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div data-subgroup="maint-scheduled">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <span class="component-card__title"><?= $i18n->t('admin.alerts.start_title') ?></span>
                            <span class="component-card__description"><?= $i18n->t('admin.alerts.start_desc') ?></span>
                        </div>
                    </div>
                    
                    <div class="component-card__actions">
                        <div class="date-time-picker-wrapper" data-element="wrapper-maint-start">
                            <div class="trigger-selector">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="material-symbols-rounded" style="color: var(--text-tertiary);">calendar_today</span>
                                    <span class="trigger-select-text"><?= $i18n->t('global.select_date') ?></span>
                                </div>
                                <span class="material-symbols-rounded">expand_more</span>
                            </div>
                            <input type="hidden" data-input="maint-start-time">
                        </div>
                    </div>
                </div>

                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <span class="component-card__title"><?= $i18n->t('admin.alerts.dur_title') ?></span>
                            <span class="component-card__description"><?= $i18n->t('admin.alerts.dur_desc') ?></span>
                        </div>
                    </div>
                    <div class="component-card__actions">
                        <div class="component-stepper">
                            <div class="component-stepper__side">
                                <button class="component-button square" data-action="duration-dec" type="button">
                                    <span class="material-symbols-rounded">remove</span>
                                </button>
                            </div>
                            <div class="component-stepper__center">
                                <input type="number" data-input="maint-duration" class="component-stepper__input" value="60" step="15">
                            </div>
                            <div class="component-stepper__side">
                                <button class="component-button square" data-action="duration-inc" type="button">
                                    <span class="material-symbols-rounded">add</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div data-subgroup="maint-emergency" style="display: none;">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-message component-message--error w-100 m-0">
                        <span style="font-weight: 600;"><?= $i18n->t('admin.alerts.emerg_warn_title') ?>:</span> 
                        <?= $i18n->t('admin.alerts.emerg_warn_desc') ?>
                    </div>
                </div>
                
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <span class="component-card__title"><?= $i18n->t('admin.alerts.cutoff_title') ?></span>
                            <span class="component-card__description"><?= $i18n->t('admin.alerts.cutoff_desc') ?></span>
                        </div>
                    </div>
                    
                    <div class="component-card__actions">
                        <div class="date-time-picker-wrapper" data-element="wrapper-maint-emergency">
                            <div class="trigger-selector">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="material-symbols-rounded" style="color: var(--text-tertiary);">schedule</span>
                                    <span class="trigger-select-text"><?= $i18n->t('global.select_time') ?></span>
                                </div>
                                <span class="material-symbols-rounded">expand_more</span>
                            </div>
                            <input type="hidden" data-input="maint-emergency-time">
                        </div>
                    </div>
                    </div>
            </div>
        </div>

        <div class="config-group" data-group="policy" style="display: none;">
            <hr class="component-divider">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <span class="component-card__title"><?= $i18n->t('admin.alerts.doc_title') ?></span>
                        <span class="component-card__description"><?= $i18n->t('admin.alerts.doc_desc') ?></span>
                    </div>
                </div>
                
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector" data-selector="policy-doc">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="material-symbols-rounded" data-preview="icon-policy-doc">description</span>
                                <span class="trigger-select-text" data-preview="text-policy-doc"><?= $i18n->t('system_alerts.policy.names.terms') ?></span>
                            </div>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-policy-doc" data-value="terms">
                                    <span class="material-symbols-rounded menu-link-icon">description</span>
                                    <span class="menu-link-text"><?= $i18n->t('system_alerts.policy.names.terms') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                                <div class="menu-link" data-action="select-policy-doc" data-value="privacy">
                                    <span class="material-symbols-rounded menu-link-icon">lock</span>
                                    <span class="menu-link-text"><?= $i18n->t('system_alerts.policy.names.privacy') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                                <div class="menu-link" data-action="select-policy-doc" data-value="cookies">
                                    <span class="material-symbols-rounded menu-link-icon">cookie</span>
                                    <span class="menu-link-text"><?= $i18n->t('system_alerts.policy.names.cookies') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">
            
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <span class="component-card__title"><?= $i18n->t('admin.alerts.update_type_title') ?></span>
                        <span class="component-card__description"><?= $i18n->t('admin.alerts.update_type_desc') ?></span>
                    </div>
                </div>
                
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector" data-selector="policy-status">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="material-symbols-rounded" data-preview="icon-policy-status">calendar_month</span>
                                <span class="trigger-select-text" data-preview="text-policy-status"><?= $i18n->t('admin.alerts.status_future') ?></span>
                            </div>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>

                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-policy-status" data-value="future">
                                    <span class="material-symbols-rounded menu-link-icon">calendar_month</span>
                                    <span class="menu-link-text"><?= $i18n->t('admin.alerts.status_future') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                                <div class="menu-link" data-action="select-policy-status" data-value="immediate">
                                    <span class="material-symbols-rounded menu-link-icon">publish</span>
                                    <span class="menu-link-text"><?= $i18n->t('admin.alerts.status_immediate') ?></span>
                                    <div class="radio-indicator"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <span class="component-card__title"><?= $i18n->t('admin.alerts.link_title') ?></span>
                        <span class="component-card__description"><?= $i18n->t('admin.alerts.link_desc') ?></span>
                    </div>
                </div>
                <div class="component-card__actions">
                    <div class="component-input-wrapper" style="max-width: 300px;">
                        <input type="url" data-input="policy-link" class="component-text-input" placeholder="https://...">
                    </div>
                </div>
            </div>

            <div data-subgroup="policy-date">
                <hr class="component-divider">
                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <span class="component-card__title"><?= $i18n->t('admin.alerts.date_title') ?></span>
                            <span class="component-card__description"><?= $i18n->t('admin.alerts.date_desc') ?></span>
                        </div>
                    </div>
                    
                    <div class="component-card__actions">
                        <div class="date-time-picker-wrapper" data-element="wrapper-policy-date">
                            <div class="trigger-selector">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="material-symbols-rounded" style="color: var(--text-tertiary);">calendar_month</span>
                                    <span class="trigger-select-text"><?= $i18n->t('global.select_date') ?></span>
                                </div>
                                <span class="material-symbols-rounded">expand_more</span>
                            </div>
                            <input type="hidden" data-input="policy-effective-date">
                        </div>
                    </div>
                    </div>
            </div>
        </div>
        
        <hr class="component-divider">

        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__text">
                    <span class="component-card__title"><?= $i18n->t('admin.alerts.preview_title') ?></span>
                    <span class="component-card__description"><?= $i18n->t('admin.alerts.preview_desc') ?></span>
                </div>
            </div>
            
            <div class="component-card__actions">
                <div style="width: 100%; background: var(--bg-surface); border: 1px solid #00000020; border-radius: 12px; padding: 12px; display: flex; align-items: center; gap: 16px;">
                    <div style="background: var(--primary-color-10); color: var(--primary-color); padding: 8px; border-radius: 50%; display: flex;">
                        <span class="material-symbols-rounded" data-preview="card-icon">info</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px;" data-preview="text-container">
                        <strong style="font-size: 15px; color: var(--text-primary);" data-preview="card-title"><?= $i18n->t('admin.alerts.preview_default_title') ?></strong>
                        <span style="font-size: 14px; color: var(--text-secondary); line-height: 1.4;" data-preview="card-message">
                            <?= $i18n->t('admin.alerts.preview_default_msg') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>