

<div class="wb-container">
    
    <div class="wb-status-overlay">
        <span id="wb-status-text">X: 0 Y: 0</span>
    </div>

    <div class="wb-main-row">
        
        <div class="wb-sidebar" id="wb-sidebar">
            <button class="wb-sidebar-btn" data-drawer="drawer-shapes" title="Figuras">
                <span class="material-symbols-rounded">category</span>
            </button>
            <button class="wb-sidebar-btn" data-drawer="drawer-toys" title="Objetos">
                <span class="material-symbols-rounded">smart_toy</span>
            </button>
            <button class="wb-sidebar-btn" data-drawer="drawer-draw" title="Dibujar">
                <span class="material-symbols-rounded">brush</span>
            </button>

            <div style="flex: 1;"></div>

            <button class="wb-sidebar-btn" title="Configuración">
                <span class="material-symbols-rounded">settings</span>
            </button>

            <div id="wb-drawer" class="wb-sidebar-drawer">
                <div class="wb-drawer-header">
                    <span id="wb-drawer-title" style="font-weight: 600; font-size: 14px;">Menú</span>
                    <button id="wb-close-drawer" style="float: right; background: none; border: none; cursor: pointer;">
                        <span class="material-symbols-rounded" style="font-size: 18px;">close</span>
                    </button>
                </div>

                <div class="wb-drawer-body">
                    <div id="drawer-shapes" class="wb-drawer-content">
                        <div class="wb-shapes-grid">
                            <div class="wb-shape-card" title="Cuadrado"><span class="material-symbols-rounded">crop_square</span></div>
                            <div class="wb-shape-card" title="Círculo"><span class="material-symbols-rounded">circle</span></div>
                            <div class="wb-shape-card" title="Triángulo"><span class="material-symbols-rounded">change_history</span></div>
                            <div class="wb-shape-card" title="Hexágono"><span class="material-symbols-rounded">hexagon</span></div>
                        </div>
                    </div>

                    <div id="drawer-toys" class="wb-drawer-content">
                        <p style="font-size: 13px; color: #666;">Elementos 3D próximamente.</p>
                    </div>

                    <div id="drawer-draw" class="wb-drawer-content">
                        <p style="font-size: 13px; color: #666;">Configuración de pincel.</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="wb-viewport" class="wb-viewport">
            <div id="wb-selection-box" class="wb-selection-box"></div>
            <div id="wb-surface" class="wb-surface">
                </div>
        </div>
        
    </div>

    <div class="wb-footer">
        <div class="wb-footer-controls">
            <button class="wb-tool-btn" id="wb-btn-center">Centrar</button>
            <div style="width: 1px; height: 24px; background-color: var(--border-color, #e0e0e0);"></div>
            <div class="wb-zoom-wrapper">
                <input type="range" id="wb-zoom-slider" class="wb-zoom-slider" min="10" max="500" value="100">
                <span id="wb-zoom-display" style="font-size: 12px; font-weight: 500; min-width: 40px; text-align: right;">100%</span>
            </div>
        </div>
    </div>
</div>