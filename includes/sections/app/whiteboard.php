<?php
// includes/sections/app/whiteboard.php

// Intentar recuperar el UUID de la URL si no viene definido por el router
if (!isset($uuid) || empty($uuid)) {
    // Asumiendo formato /whiteboard/{uuid}
    $pathParts = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $uuid = end($pathParts);
}

// Validación básica de seguridad para el UUID
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
    $uuid = ''; // UUID inválido
}
?>
<div class="wb-container" data-wb-uuid="<?php echo htmlspecialchars($uuid); ?>">
    
    <style>
        /* --- ESTILOS TOOLBAR SUPERIOR --- */
        .wb-top-toolbar {
            position: absolute;
            top: 20px; left: 50%; transform: translateX(-50%) translateY(-20px);
            background: #ffffff; padding: 6px 12px; border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #e0e0e0;
            display: flex; gap: 8px; align-items: center; z-index: 100;
            opacity: 0; visibility: hidden; transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: none;
        }
        
        .wb-top-toolbar.active {
            opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); pointer-events: all;
        }

        /* --- TOOLBAR SECUNDARIO (ANIMACIÓN) --- */
        .wb-secondary-toolbar {
            position: absolute;
            top: 70px; /* Debajo del toolbar principal */
            left: 50%; transform: translateX(-50%) translateY(-10px);
            background: #ffffff; padding: 6px 12px; border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #e0e0e0;
            display: flex; gap: 10px; align-items: center; z-index: 99;
            opacity: 0; visibility: hidden; transition: all 0.2s;
            pointer-events: none;
        }
        
        .wb-secondary-toolbar.active {
            opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); pointer-events: all;
        }

        .wb-tool-group { display: flex; align-items: center; gap: 4px; }

        .wb-tool-btn {
            background: transparent; border: 1px solid transparent; cursor: pointer;
            padding: 6px; border-radius: 4px; display: flex; align-items: center;
            justify-content: center; color: #555; transition: all 0.2s; position: relative;
        }
        
        .wb-tool-btn:hover, .wb-tool-btn.active { background-color: #f5f5f5; color: #000; }
        .wb-tool-btn.active-state { background-color: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
        
        /* Botón Play activo */
        .wb-tool-btn.is-playing { background-color: #dcfce7; color: #16a34a; border-color: #bbf7d0; }

        .wb-tool-input-wrapper {
            display: flex; align-items: center; background: #f5f5f5;
            border-radius: 4px; padding: 2px 6px; border: 1px solid transparent;
        }
        
        .wb-tool-label { font-size: 11px; color: #888; margin-right: 4px; font-weight: 500; text-transform: uppercase; }
        
        .wb-tool-value {
            font-size: 13px; font-weight: 600; color: #333; min-width: 30px;
            text-align: center; border: none; background: transparent; outline: none; font-family: inherit;
        }

        .wb-divider { width: 1px; height: 24px; background-color: #e0e0e0; margin: 0 4px; }

        /* --- ESTILOS POPOVER DE BORDE --- */
        .wb-popover {
            position: absolute;
            top: 60px; 
            left: 50%;
            transform: translateX(-50%) translateY(-10px);
            background: #fff;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border: 1px solid #e0e0e0;
            width: 220px;
            z-index: 99;
            opacity: 0; visibility: hidden;
            transition: all 0.2s;
            pointer-events: none;
        }

        .wb-popover.active {
            opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); pointer-events: all;
        }

        .wb-popover-row {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;
        }
        .wb-popover-row:last-child { margin-bottom: 0; }

        .wb-popover-label { font-size: 12px; color: #666; font-weight: 500; }

        .wb-slider { width: 100px; accent-color: #000; }
        .wb-slider-mini { width: 60px; accent-color: #000; height: 4px; }
        
        /* Slider de velocidad */
        .wb-slider-velocity { width: 80px; accent-color: #0284c7; height: 4px; }

        .wb-color-input {
            width: 30px; height: 30px; border: none; padding: 0; background: none; cursor: pointer;
        }

        .wb-colors-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; padding: 5px; }
        .wb-color-swatch { width: 100%; aspect-ratio: 1; border-radius: 6px; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); transition: transform 0.1s; }
        .wb-color-swatch:hover { transform: scale(1.1); box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1; }
        
        /* --- MODO DEBUG --- */
        .wb-debug-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 320px;
            max-height: calc(100vh - 140px);
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 15px;
            color: #22d3ee;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 11px;
            z-index: 1000;
            overflow-y: auto;
            backdrop-filter: blur(4px);
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }

        .wb-debug-panel.active {
            display: block;
        }

        .wb-debug-content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* Scrollbar del debug */
        .wb-debug-panel::-webkit-scrollbar { width: 6px; }
        .wb-debug-panel::-webkit-scrollbar-track { background: #0f172a; }
        .wb-debug-panel::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        .wb-debug-panel::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* --- MODAL DE COMPARTIR --- */
        .wb-modal-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.2s;
        }
        .wb-modal-overlay.active { opacity: 1; visibility: visible; }
        .wb-modal {
            background: #fff; width: 400px; max-width: 90%;
            border-radius: 12px; padding: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transform: translateY(20px); transition: transform 0.2s;
        }
        .wb-modal-overlay.active .wb-modal { transform: translateY(0); }
        .wb-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .wb-modal-title { font-size: 18px; font-weight: 700; color: #1e293b; }
        .wb-modal-close { background: none; border: none; cursor: pointer; color: #64748b; }
        
        .wb-share-option {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;
            margin-bottom: 10px; cursor: pointer; transition: all 0.2s;
        }
        .wb-share-option:hover { background: #f8fafc; border-color: #cbd5e1; }
        .wb-share-option.selected { border-color: #3b82f6; background: #eff6ff; }
        .wb-share-radio { margin-top: 4px; accent-color: #3b82f6; }
        .wb-share-text h4 { margin: 0 0 4px; font-size: 14px; font-weight: 600; color: #334155; }
        .wb-share-text p { margin: 0; font-size: 12px; color: #64748b; }

        .wb-share-link-box {
            margin-top: 16px; display: none;
        }
        .wb-share-link-box.active { display: block; animation: fadeIn 0.3s; }
        .wb-link-wrapper {
            display: flex; gap: 8px; margin-top: 8px;
        }
        .wb-link-input {
            flex: 1; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;
            font-size: 13px; color: #475569; background: #f8fafc; outline: none;
        }
        .wb-btn-copy {
            padding: 8px 16px; background: #3b82f6; color: #fff; border: none;
            border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer;
        }
        .wb-btn-copy:hover { background: #2563eb; }

    </style>

    <div id="wb-debug-panel" class="wb-debug-panel">
        <div style="margin-bottom: 10px; border-bottom: 1px solid #334155; padding-bottom: 5px; color: #fff; font-weight: bold;">
            DEBUG MONITOR (JSON)
        </div>
        <div id="wb-debug-content" class="wb-debug-content">Waiting for data...</div>
    </div>

    <div class="wb-status-overlay">
        <span id="wb-status-text">X: 0 Y: 0</span>
    </div>

    <div id="wb-share-modal" class="wb-modal-overlay">
        <div class="wb-modal">
            <div class="wb-modal-header">
                <span class="wb-modal-title">Compartir Pizarrón</span>
                <button id="wb-share-close" class="wb-modal-close"><span class="material-symbols-rounded">close</span></button>
            </div>
            
            <label class="wb-share-option" id="opt-private">
                <input type="radio" name="wb-visibility" value="private" class="wb-share-radio">
                <div class="wb-share-text">
                    <h4>Privado</h4>
                    <p>Solo tú tienes acceso a este pizarrón.</p>
                </div>
            </label>

            <label class="wb-share-option" id="opt-public">
                <input type="radio" name="wb-visibility" value="public" class="wb-share-radio">
                <div class="wb-share-text">
                    <h4>Cualquiera con el enlace</h4>
                    <p>Usuarios registrados con el link pueden ver y editar.</p>
                </div>
            </label>

            <div id="wb-share-link-area" class="wb-share-link-box">
                <div style="font-size: 13px; font-weight: 500; color: #334155;">Enlace para copiar:</div>
                <div class="wb-link-wrapper">
                    <input type="text" id="wb-share-input" class="wb-link-input" readonly value="<?php echo "https://" . $_SERVER['HTTP_HOST'] . "/ProjectAurora/whiteboard/" . htmlspecialchars($uuid); ?>">
                    <button id="wb-btn-copy-link" class="wb-btn-copy">Copiar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="wb-main-row">
        
        <div class="wb-sidebar" id="wb-sidebar">
            <button class="wb-sidebar-btn" data-drawer="drawer-shapes" title="Figuras">
                <span class="material-symbols-rounded">category</span>
            </button>
            <button class="wb-sidebar-btn" data-drawer="drawer-toys" title="Objetos">
                <span class="material-symbols-rounded">smart_toy</span>
            </button>

            <div style="flex: 1;"></div>
            <button class="wb-sidebar-btn" title="Configuración"><span class="material-symbols-rounded">settings</span></button>

            <div id="wb-drawer" class="wb-sidebar-drawer">
                <div class="wb-drawer-header">
                    <span id="wb-drawer-title" style="font-weight: 600; font-size: 14px;">Menú</span>
                    <button id="wb-close-drawer" style="float: right; background: none; border: none; cursor: pointer;">
                        <span class="material-symbols-rounded" style="font-size: 18px;">close</span>
                    </button>
                </div>
                <div class="wb-drawer-body">
                    <div id="drawer-shapes" class="wb-drawer-content">
                        <div class="wb-shapes-section">
                            <h4 style="font-size: 12px; color: #666; margin: 10px 0 5px; text-transform: uppercase;">Líneas</h4>
                            <div class="wb-shapes-grid">
                                <div class="wb-shape-card" data-shape="line" title="Línea Recta"><span class="material-symbols-rounded">remove</span></div>
                            </div>
                        </div>

                        <div class="wb-shapes-section">
                            <h4 style="font-size: 12px; color: #666; margin: 10px 0 5px; text-transform: uppercase;">Básicas</h4>
                            <div class="wb-shapes-grid">
                                <div class="wb-shape-card" data-shape="rect" title="Rectángulo"><span class="material-symbols-rounded">crop_square</span></div>
                                <div class="wb-shape-card" data-shape="circle" title="Círculo"><span class="material-symbols-rounded">circle</span></div>
                                <div class="wb-shape-card" data-shape="triangle" title="Triángulo"><span class="material-symbols-rounded">change_history</span></div>
                            </div>
                        </div>
                        <div class="wb-shapes-section">
                            <h4 style="font-size: 12px; color: #666; margin: 15px 0 5px; text-transform: uppercase;">Polígonos</h4>
                            <div class="wb-shapes-grid">
                                <div class="wb-shape-card" data-shape="pentagon" title="Pentágono"><span class="material-symbols-rounded">pentagon</span></div>
                                <div class="wb-shape-card" data-shape="hexagon" title="Hexágono"><span class="material-symbols-rounded">hexagon</span></div>
                                <div class="wb-shape-card" data-shape="octagon" title="Octágono"><span class="material-symbols-rounded">stop_circle</span></div>
                            </div>
                        </div>
                        <div class="wb-shapes-section">
                            <h4 style="font-size: 12px; color: #666; margin: 15px 0 5px; text-transform: uppercase;">Flechas</h4>
                            <div class="wb-shapes-grid">
                                <div class="wb-shape-card" data-shape="arrow-right" title="Flecha Derecha"><span class="material-symbols-rounded">arrow_right_alt</span></div>
                                <div class="wb-shape-card" data-shape="arrow-left" title="Flecha Izquierda"><span class="material-symbols-rounded">west</span></div>
                                <div class="wb-shape-card" data-shape="arrow-double" title="Bidireccional"><span class="material-symbols-rounded">sync_alt</span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="drawer-colors" class="wb-drawer-content">
                        <h4 style="font-size: 12px; color: #666; margin: 10px 0 10px; text-transform: uppercase;">Paleta de Colores</h4>
                        <div class="wb-colors-grid">
                            <div class="wb-color-swatch" data-color="transparent" title="Sin Relleno (Transparente)"
                                 style="background: repeating-linear-gradient(45deg, #e0e0e0, #e0e0e0 5px, #ffffff 5px, #ffffff 10px); border: 1px solid #ccc; display: flex; align-items: center; justify-content: center;">
                                 <span class="material-symbols-rounded" style="font-size: 18px; color: #666;">block</span>
                            </div>
                            <div class="wb-color-swatch" data-color="#000000" style="background-color: #000000;" title="Negro"></div>
                            <div class="wb-color-swatch" data-color="#ffffff" style="background-color: #ffffff; border: 1px solid #ddd;" title="Blanco"></div>
                            <div class="wb-color-swatch" data-color="#ef4444" style="background-color: #ef4444;" title="Rojo"></div>
                            <div class="wb-color-swatch" data-color="#3b82f6" style="background-color: #3b82f6;" title="Azul"></div>
                            <div class="wb-color-swatch" data-color="#22c55e" style="background-color: #22c55e;" title="Verde"></div>
                            <div class="wb-color-swatch" data-color="#eab308" style="background-color: #eab308;" title="Amarillo"></div>
                        </div>
                    </div>

                    <div id="drawer-toys" class="wb-drawer-content">
                         <h4 style="font-size: 12px; color: #666; margin: 10px 0 5px; text-transform: uppercase;">Mecánicos</h4>
                        <div class="wb-shapes-grid">
                             <div class="wb-shape-card" data-shape="circle-cut" title="Anillo Motorizado"><span class="material-symbols-rounded">data_usage</span></div>
                             <div class="wb-shape-card" data-shape="conveyor" title="Cinta Transportadora"><span class="material-symbols-rounded">conveyor_belt</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="wb-viewport" class="wb-viewport">
            
            <div id="wb-top-toolbar" class="wb-top-toolbar">
                <div class="wb-tool-group">
                    <button class="wb-tool-btn" id="btn-scale-down" title="Reducir"><span class="material-symbols-rounded" style="font-size: 18px;">remove</span></button>
                    <div class="wb-tool-input-wrapper">
                        <span class="wb-tool-label">H</span>
                        <input type="text" id="wb-size-display" class="wb-tool-value" value="100" readonly>
                        <span style="font-size: 10px; color: #888;">px</span>
                    </div>
                    <button class="wb-tool-btn" id="btn-scale-up" title="Aumentar"><span class="material-symbols-rounded" style="font-size: 18px;">add</span></button>
                </div>

                <div class="wb-divider"></div>

                <div class="wb-tool-group" id="wb-aperture-group" style="display: none;">
                    <div class="wb-tool-input-wrapper">
                        <span class="wb-tool-label">Apertura</span>
                        <input type="range" id="inp-aperture-size" class="wb-slider-mini" min="10" max="160" step="5" value="45">
                    </div>
                     <div class="wb-divider"></div>
                </div>

                <div class="wb-tool-group">
                    <button class="wb-tool-btn" id="btn-open-colors" title="Color de Relleno">
                        <span class="material-symbols-rounded" style="font-size: 18px;">format_color_fill</span>
                    </button>
                    <button class="wb-tool-btn" id="btn-make-hollow" title="Hacer Hueca (Borde + Sin Relleno)">
                        <span class="material-symbols-rounded" style="font-size: 18px;">check_box_outline_blank</span>
                    </button>
                    <button class="wb-tool-btn" id="btn-border-options" title="Opciones de Borde">
                        <span class="material-symbols-rounded" style="font-size: 18px;">border_style</span>
                    </button>
                </div>

                <div class="wb-divider"></div>

                <div class="wb-tool-group">
                    <button class="wb-tool-btn" id="btn-delete-selection" title="Eliminar selección" style="color: #ef4444;">
                        <span class="material-symbols-rounded" style="font-size: 18px;">delete</span>
                    </button>
                </div>
            </div>

            <div id="wb-secondary-toolbar" class="wb-secondary-toolbar">
                <div class="wb-tool-group">
                    <button class="wb-tool-btn" id="btn-spin-toggle" title="Play/Pausa">
                        <span class="material-symbols-rounded" id="icon-spin-toggle" style="font-size: 20px;">play_arrow</span>
                    </button>
                </div>
                <div class="wb-divider"></div>
                <div class="wb-tool-group">
                    <span class="wb-tool-label" style="margin-left: 4px;">Velocidad</span>
                    <input type="range" id="inp-spin-speed" class="wb-slider-velocity" min="-20" max="20" step="1" value="2">
                    <span id="val-spin-speed" style="font-size: 11px; margin-left: 5px; min-width: 20px;">2</span>
                </div>
            </div>

            <div id="wb-border-popover" class="wb-popover">
                <div class="wb-popover-row">
                    <span class="wb-popover-label">Grosor</span>
                    <input type="range" id="inp-border-width" class="wb-slider" min="0" max="20" step="1" value="0">
                    <span id="val-border-width" style="font-size: 11px; color:#333; width: 20px; text-align:right;">0</span>
                </div>
                
                <div class="wb-popover-row">
                    <span class="wb-popover-label">Color</span>
                    <input type="color" id="inp-border-color" class="wb-color-input" value="#000000">
                </div>

                <div class="wb-popover-row" id="row-border-radius">
                    <span class="wb-popover-label">Radio</span>
                    <input type="range" id="inp-border-radius" class="wb-slider" min="0" max="50" step="1" value="0">
                    <span id="val-border-radius" style="font-size: 11px; color:#333; width: 20px; text-align:right;">0</span>
                </div>
            </div>

            <div id="wb-surface" class="wb-surface">
                <canvas id="wb-canvas"></canvas>
            </div>
        </div>
        
    </div>

    <div class="wb-footer">
        <div class="wb-footer-controls">
            <button class="wb-tool-btn" id="wb-btn-save" title="Guardar Proyecto" style="color: #2563eb;">
                <span class="material-symbols-rounded">save</span>
            </button>
            
            <button class="wb-tool-btn" id="wb-btn-share" title="Compartir y Acceso" style="color: #0891b2; margin-left: 4px;">
                <span class="material-symbols-rounded">share</span>
            </button>

            <div style="width: 1px; height: 24px; background-color: var(--border-color, #e0e0e0); margin: 0 5px;"></div>
            
            <button class="wb-tool-btn" id="wb-btn-physics-selected" title="Activar Física (Selección)">
                <span class="material-symbols-rounded">touch_app</span> </button>
            <button class="wb-tool-btn" id="wb-btn-physics-all" title="Activar/Desactivar Física (Todo)">
                <span class="material-symbols-rounded">bolt</span> </button>
            
            <div style="width: 1px; height: 24px; background-color: var(--border-color, #e0e0e0); margin: 0 5px;"></div>

            <button class="wb-tool-btn" id="wb-btn-undo" title="Deshacer (Ctrl+Z)">
                <span class="material-symbols-rounded">undo</span>
            </button>
            <button class="wb-tool-btn" id="wb-btn-redo" title="Rehacer (Ctrl+Y)">
                <span class="material-symbols-rounded">redo</span>
            </button>
            
            <div style="width: 1px; height: 24px; background-color: var(--border-color, #e0e0e0); margin: 0 5px;"></div>
            
            <button class="wb-tool-btn" id="wb-btn-center">Centrar</button>
            <div style="width: 1px; height: 24px; background-color: var(--border-color, #e0e0e0);"></div>
            <div class="wb-zoom-wrapper">
                <input type="range" id="wb-zoom-slider" class="wb-zoom-slider" min="10" max="500" value="100">
                <span id="wb-zoom-display" style="font-size: 12px; font-weight: 500; min-width: 40px; text-align: right;">100%</span>
            </div>

            <div style="width: 1px; height: 24px; background-color: var(--border-color, #e0e0e0); margin: 0 5px;"></div>
            <button class="wb-tool-btn" id="wb-btn-debug" title="Modo Debug (JSON Monitor)">
                <span class="material-symbols-rounded">bug_report</span>
            </button>
        </div>
    </div>
</div>