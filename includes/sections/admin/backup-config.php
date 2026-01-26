<?php
// includes/sections/admin/backup-config.php
?>
<div class="component-wrapper" data-section="admin-backup-config">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-action="back-to-backups" data-tooltip="Volver a Copias de Seguridad">
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
        <p class="component-page-description">Configura la frecuencia y retención de las copias de seguridad automáticas.</p>
    </div>

    <div id="config-loading-state" class="state-loading">
        <div class="spinner-sm"></div>
        <p class="state-text">Cargando configuración...</p>
    </div>

    <div id="config-content-area" class="component-card component-card--grouped mt-4 d-none">

        <div class="component-group-item">
            <div class="component-card__content">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">schedule</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title">Habilitar Backups Automáticos</h2>
                    <p class="component-card__description">El sistema generará copias de seguridad sin intervención manual.</p>
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
                    <p class="component-card__description">Intervalo de tiempo entre cada copia de seguridad automática.</p>
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
                    <h2 class="component-card__title">Política de Retención (Archivos)</h2>
                    <p class="component-card__description">Cantidad máxima de copias automáticas a conservar. Las más antiguas se eliminarán.</p>
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