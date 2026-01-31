<?php
// includes/sections/admin/system-alerts.php
?>
<div class="component-wrapper" data-section="admin-system-alerts">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <button class="header-button" data-nav="admin/dashboard" data-tooltip="Volver">
                        <span class="material-symbols-rounded">arrow_back</span>
                    </button>
                    <div class="component-toolbar-title">Sistema de Alertas Globales</div>
                </div>
                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" data-action="refresh-status" data-tooltip="Verificar Estado">
                        <span class="material-symbols-rounded">sync</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-card component-card--grouped mt-24">
        
        <div class="component-group-item">
            <div class="component-card__content">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded" id="status-icon">check_circle</span>
                </div>
                <div class="component-card__text">
                    <span class="component-card__title">Estado Actual</span>
                    <span class="component-card__description" id="status-text">Cargando...</span>
                </div>
            </div>
            <div class="component-card__actions">
                <button class="component-button danger" id="btn-deactivate-alert" style="display: none;">
                    Desactivar
                </button>
            </div>
        </div>
        
        <hr class="component-divider">

        <div class="component-group-item">
            <div class="component-card__text">
                <span class="component-card__title">Tipo de Alerta</span>
                <span class="component-card__description">Define la categoría del mensaje global.</span>
            </div>
            
            <div class="trigger-select-wrapper">
                <div class="trigger-selector" id="trigger-alert-type">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded" id="icon-alert-type">speed</span>
                        <span class="trigger-select-text" id="text-alert-type">Problemas de Rendimiento</span>
                    </div>
                    <span class="material-symbols-rounded">expand_more</span>
                </div>

                <div class="popover-module" id="popover-alert-type">
                    <div class="menu-list menu-list--scrollable">
                        <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-main-type" data-value="performance">
                            <span class="material-symbols-rounded">speed</span> Rendimiento
                        </div>
                        <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-main-type" data-value="maintenance">
                            <span class="material-symbols-rounded">build</span> Mantenimiento
                        </div>
                        <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-main-type" data-value="policy">
                            <span class="material-symbols-rounded">policy</span> Políticas
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="group-performance" class="config-group">
            <hr class="component-divider">
            <div class="component-group-item" style="align-items: flex-start;">
                <div class="component-card__text" style="max-width: 200px; margin-top: 8px;">
                    <span class="component-card__title">Mensaje</span>
                    <span class="component-card__description">Detalle del problema.</span>
                </div>
                <div class="component-input-wrapper" style="flex: 1; max-width: 400px;">
                    <textarea id="perf-message" class="component-textarea-read" style="height: 80px; background: var(--bg-surface);">Estamos experimentando problemas de rendimiento. Trabajamos para resolverlo.</textarea>
                </div>
            </div>
        </div>

        <div id="group-maintenance" class="config-group" style="display: none;">
            <hr class="component-divider">
            
            <div class="component-group-item">
                <div class="component-card__text">
                    <span class="component-card__title">Modalidad</span>
                    <span class="component-card__description">¿Es planificado o una emergencia?</span>
                </div>
                
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" id="trigger-maint-type">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-rounded" id="icon-maint-type">event</span>
                            <span class="trigger-select-text" id="text-maint-type">Programado</span>
                        </div>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>

                    <div class="popover-module" id="popover-maint-type">
                        <div class="menu-list">
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-maint-type" data-value="scheduled">
                                <span class="material-symbols-rounded">event</span> Programado
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none; color: var(--color-error);" data-action="select-maint-type" data-value="emergency">
                                <span class="material-symbols-rounded">warning</span> Emergencia
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="subgroup-maint-scheduled">
                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-card__text">
                        <span class="component-card__title">Inicio Programado</span>
                    </div>
                    <div class="component-input-wrapper" style="width: auto;">
                        <input type="datetime-local" id="maint-start-time" class="component-text-input">
                    </div>
                </div>

                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-card__text">
                        <span class="component-card__title">Duración (min)</span>
                    </div>
                    <div class="stepper-control">
                        <button class="stepper-btn" onclick="this.nextElementSibling.stepDown(15)">
                            <span class="material-symbols-rounded">remove</span>
                        </button>
                        <input type="number" id="maint-duration" class="stepper-input" value="60" step="15">
                        <button class="stepper-btn" onclick="this.previousElementSibling.stepUp(15)">
                            <span class="material-symbols-rounded">add</span>
                        </button>
                    </div>
                </div>
            </div>

            <div id="subgroup-maint-emergency" style="display: none;">
                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-message component-message--error w-100 m-0">
                        <span style="font-weight: 600;">Advertencia:</span> Se avisará a los usuarios de un cierre inminente.
                    </div>
                </div>
                
                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-card__text">
                        <span class="component-card__title">Hora de corte</span>
                    </div>
                    <div class="component-input-wrapper" style="width: auto;">
                        <input type="time" id="maint-emergency-time" class="component-text-input">
                    </div>
                </div>
            </div>
        </div>

        <div id="group-policy" class="config-group" style="display: none;">
            <hr class="component-divider">
            
            <div class="component-group-item">
                <div class="component-card__text">
                    <span class="component-card__title">Documento</span>
                </div>
                
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" id="trigger-policy-doc">
                        <span class="trigger-select-text" id="text-policy-doc">Términos y Condiciones</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>

                    <div class="popover-module" id="popover-policy-doc">
                        <div class="menu-list">
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-doc" data-value="terms">
                                Términos y Condiciones
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-doc" data-value="privacy">
                                Política de Privacidad
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-doc" data-value="cookies">
                                Política de Cookies
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">
            <div class="component-group-item">
                <div class="component-card__text">
                    <span class="component-card__title">Enlace al Documento</span>
                </div>
                <div class="component-input-wrapper" style="max-width: 300px;">
                    <input type="url" id="policy-link" class="component-text-input" placeholder="https://...">
                </div>
            </div>

            <hr class="component-divider">
            <div class="component-group-item">
                <div class="component-card__text">
                    <span class="component-card__title">Entra en vigor</span>
                </div>
                <div class="component-input-wrapper" style="width: auto;">
                    <input type="date" id="policy-effective-date" class="component-text-input">
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-group-item">
            <div class="component-card__text"></div> <div class="component-card__actions">
                 <button class="component-button primary" id="btn-emit-alert">
                    <span class="material-symbols-rounded">campaign</span>
                    Emitir Alerta
                </button>
            </div>
        </div>

    </div>
</div>