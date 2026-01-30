<?php
// includes/sections/admin/backup-config.php
?>
<div class="component-wrapper" data-section="admin-backup-config">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="component-button ghost icon-only" data-action="back-to-backups" title="Volver">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </button>
                    <div class="component-toolbar-title">Configuración Automática</div>
                </div>
                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="component-button primary" id="btn-save-backup-config">
                        <span class="material-symbols-rounded">save</span>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-header-card">
        <h1 class="component-page-title">Automatización</h1>
        <p class="component-page-description">Supervisa y configura las tareas programadas de copia de seguridad.</p>
    </div>

    <div id="config-loading-state" class="state-loading">
        <div class="spinner-sm"></div>
        <p class="state-text">Cargando estado...</p>
    </div>

    <div id="config-content-area" class="d-none">

        <div class="component-dashboard-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            
            <div class="component-stat-card">
                <div class="component-stat-header">
                    <span class="component-stat-title">Próxima Ejecución</span>
                    <span class="material-symbols-rounded component-stat-icon" style="color: var(--color-primary);">timer</span>
                </div>
                <div class="component-stat-main-value" id="stat-countdown" style="font-feature-settings: 'tnum';">--:--:--</div>
                <div class="component-stat-footer">
                    <span class="component-trend-text" id="stat-next-date">Calculando...</span>
                </div>
            </div>

            <div class="component-stat-card">
                <div class="component-stat-header">
                    <span class="component-stat-title">Último Respaldo</span>
                    <span class="material-symbols-rounded component-stat-icon">history</span>
                </div>
                <div class="component-stat-main-value" style="font-size: 1.5rem;" id="stat-last-run">...</div>
                <div class="component-stat-footer">
                    <span class="component-trend-badge success" id="stat-status-badge">
                        <span class="material-symbols-rounded" style="font-size:14px;">check_circle</span> Activo
                    </span>
                </div>
            </div>

            <div class="component-stat-card" style="background: var(--bg-surface-2); border: 1px dashed var(--border-light);">
                <div class="component-stat-header">
                    <span class="component-stat-title">Acciones Rápidas</span>
                    <span class="material-symbols-rounded component-stat-icon">bolt</span>
                </div>
                <div style="flex-grow: 1; display: flex; align-items: center;">
                    <button class="component-button secondary w-100" id="btn-trigger-now">
                        <span class="material-symbols-rounded">play_arrow</span>
                        Adelantar Respaldo
                    </button>
                </div>
                <div class="component-stat-footer">
                    <span class="component-trend-text">Reinicia el ciclo del temporizador</span>
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
                        <h2 class="component-card__title">Habilitar Backups Automáticos</h2>
                        <p class="component-card__description">El scheduler revisará esta configuración cada 60 segundos.</p>
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
                        <h2 class="component-card__title">Frecuencia (Horas)</h2>
                        <p class="component-card__description">Tiempo a esperar después del último backup exitoso.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="stepper-control w-100" data-role="stepper" data-step-small="1" data-step-large="6">
                        <div class="stepper-side left">
                            <button type="button" class="component-button stepper-btn" data-action="dec-large" title="-6">
                                <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                            </button>
                            <button type="button" class="component-button stepper-btn" data-action="dec-small" title="-1">
                                <span class="material-symbols-rounded">chevron_left</span>
                            </button>
                        </div>
                        <div class="stepper-center">
                            <input type="number" class="component-text-input stepper-input" id="input-frequency" value="24" min="1">
                        </div>
                        <div class="stepper-side right">
                            <button type="button" class="component-button stepper-btn" data-action="inc-small" title="+1">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </button>
                            <button type="button" class="component-button stepper-btn" data-action="inc-large" title="+6">
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
                        <h2 class="component-card__title">Política de Retención</h2>
                        <p class="component-card__description">Archivos a conservar antes de limpiar los más antiguos.</p>
                    </div>
                </div>
                <div class="component-card__actions w-100">
                    <div class="stepper-control w-100" data-role="stepper" data-step-small="1" data-step-large="5">
                        <div class="stepper-side left">
                            <button type="button" class="component-button stepper-btn" data-action="dec-large" title="-5">
                                <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                            </button>
                            <button type="button" class="component-button stepper-btn" data-action="dec-small" title="-1">
                                <span class="material-symbols-rounded">chevron_left</span>
                            </button>
                        </div>
                        <div class="stepper-center">
                            <input type="number" class="component-text-input stepper-input" id="input-retention" value="10" min="1">
                        </div>
                        <div class="stepper-side right">
                            <button type="button" class="component-button stepper-btn" data-action="inc-small" title="+1">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </button>
                            <button type="button" class="component-button stepper-btn" data-action="inc-large" title="+5">
                                <span class="material-symbols-rounded">keyboard_double_arrow_right</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>