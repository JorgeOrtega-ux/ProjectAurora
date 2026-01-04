/**
 * public/assets/js/modules/app/whiteboard-ui.js
 * Gestión del DOM e Interfaz de Usuario
 */

export const WhiteboardUI = {
    elements: {
        viewport: null,
        status: null,
        fps: null, // Nuevo elemento para FPS
        zoomSlider: null,
        zoomDisplay: null,
        btnCenter: null,
        
        btnUndo: null,
        btnRedo: null,
        btnPhysicsAll: null,
        btnPhysicsSelected: null,

        drawer: null,
        drawerTitle: null,
        btnCloseDrawer: null,
        
        toolbar: null,
        secondaryToolbar: null, 
        btnScaleUp: null,
        btnScaleDown: null,
        btnDelete: null,
        btnColors: null,
        btnMakeHollow: null,
        btnBorderOptions: null,
        sizeDisplay: null,
        
        groupAperture: null,
        inpApertureSize: null,

        // Controles secundarios (Spin/Conveyor)
        btnSpinToggle: null,
        iconSpinToggle: null,
        inpSpinSpeed: null,
        valSpinSpeed: null,

        borderPopover: null,
        inpBorderWidth: null,
        valBorderWidth: null,
        inpBorderColor: null,
        inpBorderRadius: null,
        valBorderRadius: null,
        rowBorderRadius: null,

        // Elementos Debug
        btnDebug: null,
        debugPanel: null,
        debugContent: null
    },

    config: {
        sliderFillColor: '#000000', 
        sliderBgColor: '#e0e0e0',
        gridSize: 24
    },

    init: () => {
        console.log("WhiteboardUI: Cacheando elementos DOM...");
        
        const el = WhiteboardUI.elements;
        el.viewport = document.getElementById('wb-viewport');
        el.status = document.getElementById('wb-status-text');
        
        // --- INYECCIÓN DEL CONTADOR DE FPS ---
        // Buscamos si ya existe, si no, lo creamos al lado de las coordenadas
        el.fps = document.getElementById('wb-fps-display');
        if (!el.fps && el.status && el.status.parentNode) {
            el.fps = document.createElement('span');
            el.fps.id = 'wb-fps-display';
            // Estilos inline para asegurar que se vea bien sin tocar CSS externo
            el.fps.style.marginLeft = '12px';
            el.fps.style.paddingLeft = '12px';
            el.fps.style.borderLeft = '1px solid #ccc';
            el.fps.style.fontFamily = 'monospace';
            el.fps.style.color = '#666';
            el.fps.style.fontSize = '0.85em';
            el.fps.innerText = 'FPS: --';
            el.status.parentNode.appendChild(el.fps);
        }

        el.zoomSlider = document.getElementById('wb-zoom-slider');
        el.zoomDisplay = document.getElementById('wb-zoom-display');
        el.btnCenter = document.getElementById('wb-btn-center');
        
        el.btnUndo = document.getElementById('wb-btn-undo');
        el.btnRedo = document.getElementById('wb-btn-redo');
        el.btnPhysicsAll = document.getElementById('wb-btn-physics-all');
        el.btnPhysicsSelected = document.getElementById('wb-btn-physics-selected');
        
        el.drawer = document.getElementById('wb-drawer');
        el.drawerTitle = document.getElementById('wb-drawer-title');
        el.btnCloseDrawer = document.getElementById('wb-close-drawer');

        el.toolbar = document.getElementById('wb-top-toolbar');
        el.secondaryToolbar = document.getElementById('wb-secondary-toolbar');
        
        el.btnScaleUp = document.getElementById('btn-scale-up');
        el.btnScaleDown = document.getElementById('btn-scale-down');
        el.btnDelete = document.getElementById('btn-delete-selection');
        el.btnColors = document.getElementById('btn-open-colors');
        el.btnMakeHollow = document.getElementById('btn-make-hollow');
        el.btnBorderOptions = document.getElementById('btn-border-options');
        el.sizeDisplay = document.getElementById('wb-size-display');
        
        el.groupAperture = document.getElementById('wb-aperture-group');
        el.inpApertureSize = document.getElementById('inp-aperture-size');

        el.btnSpinToggle = document.getElementById('btn-spin-toggle');
        el.iconSpinToggle = document.getElementById('icon-spin-toggle');
        el.inpSpinSpeed = document.getElementById('inp-spin-speed');
        el.valSpinSpeed = document.getElementById('val-spin-speed');

        el.borderPopover = document.getElementById('wb-border-popover');
        el.inpBorderWidth = document.getElementById('inp-border-width');
        el.valBorderWidth = document.getElementById('val-border-width');
        el.inpBorderColor = document.getElementById('inp-border-color');
        el.inpBorderRadius = document.getElementById('inp-border-radius');
        el.valBorderRadius = document.getElementById('val-border-radius');
        el.rowBorderRadius = document.getElementById('row-border-radius');

        // Inicializar elementos Debug
        el.btnDebug = document.getElementById('wb-btn-debug');
        el.debugPanel = document.getElementById('wb-debug-panel');
        el.debugContent = document.getElementById('wb-debug-content');

        WhiteboardUI.bindSidebarEvents();
        WhiteboardUI.updateSliderFill();
    },

    bindSidebarEvents: () => {
        const { drawer, btnCloseDrawer } = WhiteboardUI.elements;
        if (!drawer) return;
        const toggleBtns = document.querySelectorAll('[data-drawer]');
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                
                if (btn.classList.contains('disabled') || btn.disabled) return;

                const contentId = btn.getAttribute('data-drawer');
                const contentEl = document.getElementById(contentId);
                const buttonTitle = btn.getAttribute('title');
                
                if (drawer.classList.contains('active') && contentEl.classList.contains('active')) {
                    WhiteboardUI.closeDrawer(); 
                    return;
                }
                
                WhiteboardUI.openDrawer(contentId, buttonTitle);
                toggleBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
        if (btnCloseDrawer) btnCloseDrawer.addEventListener('click', () => WhiteboardUI.closeDrawer());
    },

    openDrawer: (id, t) => {
        const { drawer, drawerTitle } = WhiteboardUI.elements;
        document.querySelectorAll('.wb-drawer-content').forEach(el => el.classList.remove('active'));
        const target = document.getElementById(id);
        if (target) target.classList.add('active');
        if (drawerTitle) drawerTitle.innerText = t || 'Menú';
        drawer.classList.add('active');
    },

    closeDrawer: () => {
        const { drawer } = WhiteboardUI.elements;
        if (drawer) drawer.classList.remove('active');
        document.querySelectorAll('[data-drawer]').forEach(btn => btn.classList.remove('active'));
    },

    toggleToolbar: (show) => {
        const { toolbar, secondaryToolbar } = WhiteboardUI.elements;
        if (!toolbar) return;
        
        if (show) {
            toolbar.classList.add('active');
        } else {
            toolbar.classList.remove('active');
            WhiteboardUI.toggleBorderPopover(false); 
            if (secondaryToolbar) secondaryToolbar.classList.remove('active');
        }
    },

    toggleBorderPopover: (show) => {
        const { borderPopover, btnBorderOptions } = WhiteboardUI.elements;
        if (!borderPopover) return;
        
        if (show && btnBorderOptions && (btnBorderOptions.disabled || btnBorderOptions.classList.contains('disabled'))) {
            return;
        }

        if (show) {
            borderPopover.classList.add('active');
            if(btnBorderOptions) btnBorderOptions.classList.add('active');
        } else {
            borderPopover.classList.remove('active');
            if(btnBorderOptions) btnBorderOptions.classList.remove('active');
        }
    },

    syncPopoverValues: (obj) => {
        const { inpBorderWidth, valBorderWidth, inpBorderColor, inpBorderRadius, valBorderRadius, rowBorderRadius } = WhiteboardUI.elements;
        
        if (obj.type === 'activeSelection') {
            obj = obj.getObjects()[0];
        }
        if (!obj) return;

        const width = obj.strokeWidth || 0;
        if (inpBorderWidth) inpBorderWidth.value = width;
        if (valBorderWidth) valBorderWidth.innerText = width;

        const color = obj.stroke || '#000000';
        if (inpBorderColor) {
            inpBorderColor.value = (color === 'transparent') ? '#000000' : color;
        }

        if (obj.type === 'rect') {
            if (rowBorderRadius) rowBorderRadius.style.display = 'flex';
            const rx = obj.rx || 0;
            if (inpBorderRadius) inpBorderRadius.value = rx;
            if (valBorderRadius) valBorderRadius.innerText = rx;
        } else {
            if (rowBorderRadius) rowBorderRadius.style.display = 'none';
        }
    },

    setBtnState: (btn, enable) => {
        if (!btn) return;
        if (enable) {
            btn.classList.remove('disabled');
            btn.removeAttribute('disabled');
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'all';
        } else {
            btn.classList.add('disabled');
            btn.setAttribute('disabled', 'true');
            btn.style.opacity = '0.3';
            btn.style.pointerEvents = 'none';
        }
    },

    updateToolbarValues: (activeObj) => {
        const { 
            sizeDisplay, groupAperture, inpApertureSize, secondaryToolbar, 
            btnSpinToggle, iconSpinToggle, inpSpinSpeed, valSpinSpeed,
            btnPhysicsSelected, btnColors, btnMakeHollow, btnBorderOptions 
        } = WhiteboardUI.elements;

        if (!sizeDisplay || !activeObj) return;

        let displayValue = "";
        
        const isCircleCut = activeObj.customType === 'circle-cut';
        const isConveyor = activeObj.customType === 'conveyor';
        const isMechanical = isCircleCut || isConveyor;

        WhiteboardUI.setBtnState(btnPhysicsSelected, !isMechanical);
        WhiteboardUI.setBtnState(btnColors, !isMechanical);
        WhiteboardUI.setBtnState(btnMakeHollow, !isMechanical);

        if (isConveyor) {
            WhiteboardUI.setBtnState(btnBorderOptions, false);
        } else {
            WhiteboardUI.setBtnState(btnBorderOptions, true);
        }

        if (isMechanical) {
            if (groupAperture) groupAperture.style.display = isCircleCut ? 'flex' : 'none';
            if (isCircleCut && inpApertureSize) inpApertureSize.value = activeObj.apertureDegree || 45;

            if (secondaryToolbar) secondaryToolbar.classList.add('active');
            
            let isPlaying = false;
            let speed = 2;

            if (isCircleCut) {
                isPlaying = activeObj.isSpinning || false;
                speed = activeObj.spinSpeed !== undefined ? activeObj.spinSpeed : 2;
            } else if (isConveyor) {
                isPlaying = activeObj.isSpinning !== false;
                speed = activeObj.conveyorSpeed !== undefined ? activeObj.conveyorSpeed : 2;
            }

            if (btnSpinToggle) {
                btnSpinToggle.style.display = 'flex'; 
                
                if (isPlaying) {
                    btnSpinToggle.classList.add('is-playing');
                    if (iconSpinToggle) iconSpinToggle.innerText = 'pause';
                } else {
                    btnSpinToggle.classList.remove('is-playing');
                    if (iconSpinToggle) iconSpinToggle.innerText = 'play_arrow';
                }
            }
            
            if (inpSpinSpeed) inpSpinSpeed.value = speed;
            if (valSpinSpeed) valSpinSpeed.innerText = speed;

        } else {
            if (groupAperture) groupAperture.style.display = 'none';
            if (secondaryToolbar) secondaryToolbar.classList.remove('active');
        }

        if (activeObj.type === 'activeSelection') {
            const objects = activeObj.getObjects();
            let firstHeight = null;
            let isUniform = true;

            const hasMechanical = objects.some(o => o.customType === 'conveyor' || o.customType === 'circle-cut');
            if (hasMechanical) {
                WhiteboardUI.setBtnState(btnPhysicsSelected, false);
                WhiteboardUI.setBtnState(btnColors, false);
                WhiteboardUI.setBtnState(btnMakeHollow, false);
            }

            for (let obj of objects) {
                const currentHeight = Math.round(obj.getScaledHeight());
                if (firstHeight === null) firstHeight = currentHeight;
                else if (Math.abs(currentHeight - firstHeight) > 1) {
                    isUniform = false;
                    break;
                }
            }
            displayValue = isUniform ? firstHeight : "--";
        } else {
            displayValue = Math.round(activeObj.getScaledHeight());
        }
        sizeDisplay.value = displayValue;
    },

    updateUIFromCanvas: (canvas) => {
        const { zoomSlider, zoomDisplay } = WhiteboardUI.elements;
        const zoom = canvas.getZoom(); 
        const percent = Math.round(zoom * 100);
        
        if (zoomSlider && Math.abs(zoomSlider.value - percent) > 1) { 
            zoomSlider.value = percent; 
            WhiteboardUI.updateSliderFill(); 
        }
        if (zoomDisplay) zoomDisplay.innerText = `${percent}%`;
        
        WhiteboardUI.updateGridBackground(canvas);
    },

    updateGridBackground: (canvas) => {
        const { viewport } = WhiteboardUI.elements;
        if (!viewport || !canvas) return;
        const vpt = canvas.viewportTransform; 
        const zoom = canvas.getZoom();
        const bgSize = WhiteboardUI.config.gridSize * zoom;
        viewport.style.backgroundPosition = `${vpt[4]}px ${vpt[5]}px`;
        viewport.style.backgroundSize = `${bgSize}px ${bgSize}px`;
    },

    updateSliderFill: () => {
        const slider = WhiteboardUI.elements.zoomSlider; if (!slider) return;
        const { sliderFillColor, sliderBgColor } = WhiteboardUI.config;
        const min = parseFloat(slider.min), max = parseFloat(slider.max), val = parseFloat(slider.value);
        const percentage = ((val - min) / (max - min)) * 100;
        slider.style.background = `linear-gradient(to right, ${sliderFillColor} 0%, ${sliderFillColor} ${percentage}%, ${sliderBgColor} ${percentage}%, ${sliderBgColor} 100%)`;
    },

    updateStatusText: (pointer) => {
        const { status } = WhiteboardUI.elements; if (!status || !pointer) return;
        status.innerText = `X: ${Math.round(pointer.x)} Y: ${Math.round(pointer.y)}`;
    },

    // --- NUEVO: Actualizar el contador de FPS ---
    updateFPS: (fpsVal) => {
        const { fps } = WhiteboardUI.elements;
        if (!fps) return;
        // Colorear según rendimiento (Opcional, pero útil)
        if (fpsVal >= 50) fps.style.color = '#4CAF50'; // Verde
        else if (fpsVal >= 30) fps.style.color = '#FF9800'; // Naranja
        else fps.style.color = '#F44336'; // Rojo
        
        fps.innerText = `FPS: ${fpsVal}`;
    },

    // --- MÉTODOS DE DEBUG ---
    toggleDebugPanel: (show) => {
        const { debugPanel, btnDebug } = WhiteboardUI.elements;
        if (!debugPanel) return;
        if (show) {
            debugPanel.classList.add('active');
            if(btnDebug) btnDebug.classList.add('active-state');
        } else {
            debugPanel.classList.remove('active');
            if(btnDebug) btnDebug.classList.remove('active-state');
        }
    },

    updateDebugContent: (data) => {
        const { debugContent } = WhiteboardUI.elements;
        if (!debugContent) return;
        debugContent.innerText = JSON.stringify(data, null, 2);
    }
};1