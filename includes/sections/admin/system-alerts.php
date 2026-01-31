<?php
// includes/sections/admin/system-alerts.php
?>
<div class="component-wrapper" data-section="admin-system-alerts">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title">Sistema de Alertas Globales</div>
                </div>
                <div class="component-toolbar__side component-toolbar__side--right" style="gap: 12px; align-items: center;">
                    <button class="header-button" id="btn-emit-alert" data-tooltip="Emitir Comunicado">
                        <span class="material-symbols-rounded">campaign</span>
                    </button>
                    <div class="component-divider-vertical" style="height: 24px; border-left: 1px solid var(--border-light); margin: 0 4px;"></div>
                    <button class="header-button" data-action="refresh-status" data-tooltip="Actualizar Métricas">
                        <span class="material-symbols-rounded">refresh</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-dashboard-grid mt-4">
        
        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Alcance Potencial</span>
                <span class="material-symbols-rounded component-stat-icon" style="color: var(--primary-color);">group_add</span>
            </div>
            <div class="component-stat-main-value" id="stat-online-users">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-text">Usuarios conectados ahora</span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Difusiones Hoy</span>
                <span class="material-symbols-rounded component-stat-icon">today</span>
            </div>
            <div class="component-stat-main-value" id="stat-alerts-today">...</div>
            <div class="component-stat-footer">
                <span class="component-trend-badge neutral" id="badge-alerts-total">
                    <span class="material-symbols-rounded" style="font-size:14px;">history</span> Total: 0
                </span>
            </div>
        </div>

        <div class="component-stat-card">
            <div class="component-stat-header">
                <span class="component-stat-title">Nivel de Impacto</span>
                <span class="material-symbols-rounded component-stat-icon" id="stat-impact-icon">monitor_heart</span>
            </div>
            <div class="component-stat-main-value" id="stat-last-severity" style="font-size: 1.5rem;">Normal</div>
            <div class="component-stat-footer">
                <span class="component-trend-text" id="stat-last-time">Sin actividad reciente</span>
            </div>
        </div>

        <div class="component-stat-card" id="card-status-indicator" style="border-left: 4px solid var(--color-success);">
            <div class="component-stat-header">
                <span class="component-stat-title">Estado Global</span>
                <span class="material-symbols-rounded component-stat-icon" id="stat-active-icon" style="color:var(--color-success);">check_circle</span>
            </div>
            <div class="component-stat-main-value" style="font-size: 1.2rem;" id="stat-active-text">Operativo</div>
            <div class="component-stat-footer">
                 <button class="component-button danger small" id="btn-deactivate-alert-mini" style="display: none; width: 100%; justify-content: center;">
                    Desactivar Alerta
                </button>
            </div>
        </div>

    </div>

    <div class="component-card component-card--grouped mt-4">
        
        <div class="component-group-item">
            <div class="component-card__text">
                <span class="component-card__title">Categoría de Alerta</span>
                <span class="component-card__description">Define la naturaleza del comunicado global.</span>
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
                            <span class="material-symbols-rounded">policy</span> Políticas y Legal
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="group-performance" class="config-group">
            <hr class="component-divider">
            <div class="component-group-item">
                <div class="component-card__text">
                    <span class="component-card__title">Diagnóstico</span>
                    <span class="component-card__description">Seleccione el mensaje predefinido para los usuarios.</span>
                </div>
                
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" id="trigger-perf-msg">
                         <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-rounded" id="icon-perf-msg">troubleshoot</span>
                            <span class="trigger-select-text" id="text-perf-msg">Degradación de Servicio</span>
                        </div>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>

                    <div class="popover-module" id="popover-perf-msg">
                        <div class="menu-list">
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-perf-msg" data-value="degradation">
                                <span class="material-symbols-rounded">troubleshoot</span> Degradación de Servicio
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-perf-msg" data-value="latency">
                                <span class="material-symbols-rounded">network_check</span> Latencia Alta Detectada
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-perf-msg" data-value="overload">
                                <span class="material-symbols-rounded">memory</span> Sobrecarga Temporal
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="group-maintenance" class="config-group" style="display: none;">
            <hr class="component-divider">
            
            <div class="component-group-item">
                <div class="component-card__text">
                    <span class="component-card__title">Modalidad</span>
                    <span class="component-card__description">Tipo de intervención técnica.</span>
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
                        <span class="component-card__title">Fecha de Inicio</span>
                        <span class="component-card__description">Cuándo comenzará la desconexión.</span>
                    </div>
                    <div class="component-input-wrapper" style="width: auto;">
                        <input type="datetime-local" id="maint-start-time" class="component-text-input">
                    </div>
                </div>

                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-card__text">
                        <span class="component-card__title">Duración Estimada</span>
                        <span class="component-card__description">Tiempo en minutos.</span>
                    </div>
                    <div class="stepper-control">
                        <button class="stepper-btn" id="btn-duration-dec" type="button">
                            <span class="material-symbols-rounded">remove</span>
                        </button>
                        <input type="number" id="maint-duration" class="stepper-input" value="60" step="15">
                        <button class="stepper-btn" id="btn-duration-inc" type="button">
                            <span class="material-symbols-rounded">add</span>
                        </button>
                    </div>
                </div>
            </div>

            <div id="subgroup-maint-emergency" style="display: none;">
                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-message component-message--error w-100 m-0">
                        <span style="font-weight: 600;">Atención:</span> Esta acción notificará un cierre inminente a todos los usuarios conectados.
                    </div>
                </div>
                
                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-card__text">
                        <span class="component-card__title">Hora de Corte</span>
                        <span class="component-card__description">Hora límite para cerrar sesión.</span>
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
                    <span class="component-card__title">Documento Legal</span>
                </div>
                
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" id="trigger-policy-doc">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-rounded" id="icon-policy-doc">description</span>
                            <span class="trigger-select-text" id="text-policy-doc">Términos y Condiciones</span>
                        </div>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>

                    <div class="popover-module" id="popover-policy-doc">
                        <div class="menu-list">
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-doc" data-value="terms">
                                <span class="material-symbols-rounded">description</span> Términos y Condiciones
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-doc" data-value="privacy">
                                <span class="material-symbols-rounded">lock</span> Política de Privacidad
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-doc" data-value="cookies">
                                <span class="material-symbols-rounded">cookie</span> Política de Cookies
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">
            
            <div class="component-group-item">
                <div class="component-card__text">
                    <span class="component-card__title">Tipo de Actualización</span>
                </div>
                
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" id="trigger-policy-status">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-rounded" id="icon-policy-status">calendar_month</span>
                            <span class="trigger-select-text" id="text-policy-status">Actualización Futura</span>
                        </div>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>

                    <div class="popover-module" id="popover-policy-status">
                        <div class="menu-list">
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-status" data-value="future">
                                <span class="material-symbols-rounded">calendar_month</span> Actualización Futura
                            </div>
                            <div class="component-button" style="justify-content: flex-start; border:none;" data-action="select-policy-status" data-value="immediate">
                                <span class="material-symbols-rounded">publish</span> Ya Disponible
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

            <div id="subgroup-policy-date">
                <hr class="component-divider">
                <div class="component-group-item">
                    <div class="component-card__text">
                        <span class="component-card__title">Entra en Vigor</span>
                    </div>
                    <div class="component-input-wrapper" style="width: auto;">
                        <input type="date" id="policy-effective-date" class="component-text-input">
                    </div>
                </div>
            </div>
        </div>
        
        <hr class="component-divider">

        <div class="component-group-item" style="flex-direction: column; align-items: flex-start; gap: 16px;">
            <div class="component-card__text">
                <span class="component-card__title">Vista Previa</span>
                <span class="component-card__description">Simulación de cómo lo verán los usuarios.</span>
            </div>
            
            <div style="width: 100%; background: var(--bg-surface); border: 1px solid #00000020; border-radius: 12px; padding: 8px; display: flex; align-items: center; gap: 16px;">
                <div style="background: var(--primary-color-10); color: var(--primary-color); padding: 8px; border-radius: 50%; display: flex;">
                    <span class="material-symbols-rounded" id="preview-icon">info</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;" id="preview-text-container">
                    <strong style="font-size: 15px; color: var(--text-primary);" id="preview-title">Título de la Alerta</strong>
                    <span style="font-size: 14px; color: var(--text-secondary); line-height: 1.4;" id="preview-message">
                        El contenido del mensaje aparecerá aquí...
                    </span>
                    </div>
            </div>
        </div>

    </div>
</div>