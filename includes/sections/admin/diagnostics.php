<?php
// includes/sections/admin/diagnostics.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<div class="section-content active" data-section="admin/diagnostics">

    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title">Herramientas de Diagnóstico</h1>
            <p class="component-page-description">Utilidades del sistema para monitorización de Redis y conectividad WebSockets.</p>
        </div>

        <div class="component-accordion">
            <div class="component-accordion__header" data-action="toggle-accordion">
                <div class="component-accordion__icon">
                    <span class="material-symbols-rounded" style="color:#1565c0">dns</span>
                </div>
                <div class="component-accordion__text">
                    <h2 class="component-accordion__title">Estado de Redis</h2>
                    <p class="component-accordion__description">Monitorización de colas de mensajes y memoria en tiempo real.</p>
                </div>
                <div class="component-accordion__arrow">
                    <span class="material-symbols-rounded">expand_more</span>
                </div>
            </div>
            <div class="component-accordion__content">
                
                <div class="component-card component-card--column active" style="width: 100%; align-items: stretch;">
                    <div class="component-card__content" style="flex-direction: column; align-items: flex-start; width: 100%;">
                        
                        <div style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                            <div style="font-size: 14px; font-weight: 500;" id="redis-status-indicator">
                                <span class="status-dot" style="background-color: #ccc;"></span> 
                                <span style="color: #666;">Verificando conexión...</span>
                            </div>
                            <button type="button" id="btn-clear-redis" class="component-button danger" style="height: 32px; font-size: 12px; display: none;">
                                <span class="material-symbols-rounded" style="font-size:16px; margin-right:4px;">delete_sweep</span> Limpiar Todo
                            </button>
                        </div>

                        <div class="w-100" id="redis-content-area">
                            <h3 style="font-size:14px; margin:0 0 10px 0; color: #444;">Colas en Memoria (<span id="redis-count">0</span>)</h3>
                            
                            <div id="redis-keys-list" style="max-height: 400px; overflow-y: auto; background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 10px; min-height: 100px;">
                                <div style="display: flex; justify-content: center; align-items: center; height: 100%;">
                                    <div class="loader-spinner"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="redis-error-box" class="error-box w-100" style="display: none; background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; border: 1px solid #ffcdd2; margin-top: 10px;">
                            <strong>Error de Redis:</strong> <span id="redis-error-msg"></span>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <div class="component-accordion">
            <div class="component-accordion__header" data-action="toggle-accordion">
                <div class="component-accordion__icon">
                    <span class="material-symbols-rounded" style="color:#f57c00">hub</span>
                </div>
                <div class="component-accordion__text">
                    <h2 class="component-accordion__title">Puente PHP -> Python</h2>
                    <p class="component-accordion__description">Prueba de comunicación TCP interna para notificaciones.</p>
                </div>
                <div class="component-accordion__arrow">
                    <span class="material-symbols-rounded">expand_more</span>
                </div>
            </div>
            <div class="component-accordion__content">
                
                <div class="component-card component-card--column active" style="width: 100%; align-items: stretch;">
                    <div class="component-card__content" style="flex-direction: column; align-items: flex-start; width: 100%;">
                        
                        <p style="font-size:14px; color:#555; margin-bottom:20px; line-height: 1.5;">
                            Esta herramienta intenta establecer una conexión TCP directa al puerto <strong>8081</strong> (localhost) y enviar un paquete de prueba. Esto verifica que el servicio de WebSockets esté escuchando y aceptando comandos internos.
                        </p>

                        <button type="button" id="btn-test-bridge" class="component-button primary w-100" style="max-width: 300px;">
                            <span class="material-symbols-rounded" style="margin-right: 8px;">send</span> Enviar Señal de Prueba
                        </button>

                        <div id="bridge-result-container" style="margin-top: 20px; width: 100%; display: none;">
                            <div id="bridge-result-box" style="padding: 15px; border-radius: 8px; border: 1px solid transparent;">
                                <div style="display:flex; align-items:center; margin-bottom:5px;">
                                    <span class="material-symbols-rounded" id="bridge-icon" style="margin-right:8px;">info</span>
                                    <strong style="font-size:15px;" id="bridge-result-title"></strong>
                                </div>
                                <p style="margin: 5px 0 10px 32px; font-size:13px;" id="bridge-result-msg"></p>
                                <small style="display:block; border-top:1px solid rgba(0,0,0,0.05); padding-top:8px; margin-left:32px; color: #666;" id="bridge-result-hint">
                                </small>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>
    
    <style>
        /* Estilos específicos conservados y adaptados */
        .status-dot {
            height: 12px; width: 12px; border-radius: 50%; display: inline-block; margin-right: 6px;
        }
        .dot-green { background-color: #4caf50; box-shadow: 0 0 0 2px #e8f5e9; }
        .dot-red { background-color: #f44336; box-shadow: 0 0 0 2px #ffebee; }
        
        .redis-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .redis-item:last-child { border-bottom: none; }
        
        .code-block {
            background: #263238;
            color: #eceff1;
            padding: 10px;
            border-radius: 6px;
            font-family: 'Roboto Mono', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 5px;
            white-space: pre-wrap;
        }
        
        .loader-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1565c0;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</div>