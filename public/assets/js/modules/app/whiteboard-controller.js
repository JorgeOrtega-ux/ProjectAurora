/**
 * public/assets/js/modules/app/whiteboard-controller.js
 */

export const WhiteboardController = {
    canvas: null,

    state: {
        panning: false,
        mouseX: 0, 
        mouseY: 0,
        isShiftPressed: false,
        lastPosX: 0,
        lastPosY: 0
    },
    
    config: {
        minScale: 0.1,
        maxScale: 5.0,
        sliderFillColor: '#000000', 
        sliderBgColor: '#e0e0e0',
        gridSize: 24,
        selectionColor: 'rgba(0, 153, 255, 0.1)', 
        selectionBorderColor: '#0099ff',           
        controlColor: '#0099ff',                   
        controlBg: '#ffffff'                       
    },

    elements: {
        viewport: null,
        status: null,
        zoomSlider: null,
        zoomDisplay: null,
        btnCenter: null,
        drawer: null,
        drawerTitle: null,
        btnCloseDrawer: null,
        
        // Toolbar Superior
        toolbar: null,
        btnScaleUp: null,
        btnScaleDown: null,
        btnDelete: null,
        btnColors: null,
        btnBorderOptions: null, // Nuevo
        sizeDisplay: null,

        // Popover Borde
        borderPopover: null,
        inpBorderWidth: null,
        valBorderWidth: null,
        inpBorderColor: null,
        inpBorderRadius: null,
        valBorderRadius: null,
        rowBorderRadius: null
    },

    init: () => {
        console.log("WhiteboardController: Iniciando...");
        
        WhiteboardController.elements.viewport = document.getElementById('wb-viewport');
        WhiteboardController.elements.status = document.getElementById('wb-status-text');
        WhiteboardController.elements.zoomSlider = document.getElementById('wb-zoom-slider');
        WhiteboardController.elements.zoomDisplay = document.getElementById('wb-zoom-display');
        WhiteboardController.elements.btnCenter = document.getElementById('wb-btn-center');
        
        WhiteboardController.elements.drawer = document.getElementById('wb-drawer');
        WhiteboardController.elements.drawerTitle = document.getElementById('wb-drawer-title');
        WhiteboardController.elements.btnCloseDrawer = document.getElementById('wb-close-drawer');

        // Toolbar
        WhiteboardController.elements.toolbar = document.getElementById('wb-top-toolbar');
        WhiteboardController.elements.btnScaleUp = document.getElementById('btn-scale-up');
        WhiteboardController.elements.btnScaleDown = document.getElementById('btn-scale-down');
        WhiteboardController.elements.btnDelete = document.getElementById('btn-delete-selection');
        WhiteboardController.elements.btnColors = document.getElementById('btn-open-colors');
        WhiteboardController.elements.btnBorderOptions = document.getElementById('btn-border-options');
        WhiteboardController.elements.sizeDisplay = document.getElementById('wb-size-display');

        // Popover Borde
        WhiteboardController.elements.borderPopover = document.getElementById('wb-border-popover');
        WhiteboardController.elements.inpBorderWidth = document.getElementById('inp-border-width');
        WhiteboardController.elements.valBorderWidth = document.getElementById('val-border-width');
        WhiteboardController.elements.inpBorderColor = document.getElementById('inp-border-color');
        WhiteboardController.elements.inpBorderRadius = document.getElementById('inp-border-radius');
        WhiteboardController.elements.valBorderRadius = document.getElementById('val-border-radius');
        WhiteboardController.elements.rowBorderRadius = document.getElementById('row-border-radius');

        if (!WhiteboardController.elements.viewport) return;

        WhiteboardController.initCanvas();
        WhiteboardController.bindEvents();
        WhiteboardController.bindSidebarEvents();
        WhiteboardController.bindShapeEvents();
        WhiteboardController.bindColorEvents(); 
        WhiteboardController.updateSliderFill();
        
        window.addEventListener('resize', WhiteboardController.resizeCanvas);
        WhiteboardController.resizeCanvas();
        WhiteboardController.centerBoard();
    },

    initCanvas: () => {
        WhiteboardController.canvas = new fabric.Canvas('wb-canvas', {
            fireRightClick: true,
            stopContextMenu: true,
            selection: true, 
            preserveObjectStacking: true,
            backgroundColor: 'transparent',
            selectionColor: WhiteboardController.config.selectionColor,
            selectionBorderColor: WhiteboardController.config.selectionBorderColor,
            selectionLineWidth: 1
        });

        fabric.Object.prototype.set({
            transparentCorners: false,      
            cornerColor: WhiteboardController.config.controlBg, 
            cornerStrokeColor: WhiteboardController.config.controlColor, 
            borderColor: WhiteboardController.config.controlColor, 
            cornerSize: 12, padding: 8, cornerStyle: 'circle', borderScaleFactor: 2,           
        });

        if (fabric.Object.prototype.controls.mtr) {
            fabric.Object.prototype.controls.mtr.offsetY = -35;
            fabric.Object.prototype.controls.mtr.withConnection = true;
            fabric.Object.prototype.controls.mtr.cursorStyle = 'pointer';
        }

        // Renderizadores de Píldoras (Minimizado para ahorrar espacio visual en el código)
        const pillLength=24, pillThickness=6; 
        const drawPillPath = (ctx, w, h) => {
            const x = -w/2, y = -h/2, r = Math.min(w, h)/2; 
            ctx.beginPath(); ctx.moveTo(x+r, y); ctx.lineTo(x+w-r, y); ctx.arcTo(x+w, y, x+w, y+h, r);
            ctx.lineTo(x+w, y+h-r); ctx.arcTo(x+w, y+h, x+w-r, y+h, r); ctx.lineTo(x+r, y+h);
            ctx.arcTo(x, y+h, x, y+h-r, r); ctx.lineTo(x, y+r); ctx.arcTo(x, y, x+r, y, r);
            ctx.closePath(); ctx.fill(); ctx.stroke();
        };
        const renderPill = (ctx, l, t, s, o, v) => {
            ctx.save(); ctx.translate(l, t); if(o.angle) ctx.rotate(fabric.util.degreesToRadians(o.angle));
            ctx.fillStyle = WhiteboardController.config.controlBg; ctx.strokeStyle = WhiteboardController.config.controlColor;
            ctx.lineWidth = 1; drawPillPath(ctx, v?pillThickness:pillLength, v?pillLength:pillThickness); ctx.restore();
        };
        fabric.Object.prototype.controls.mt.render = (c,l,t,s,o)=>renderPill(c,l,t,s,o,0);
        fabric.Object.prototype.controls.mb.render = (c,l,t,s,o)=>renderPill(c,l,t,s,o,0);
        fabric.Object.prototype.controls.ml.render = (c,l,t,s,o)=>renderPill(c,l,t,s,o,1);
        fabric.Object.prototype.controls.mr.render = (c,l,t,s,o)=>renderPill(c,l,t,s,o,1);
    },

    resizeCanvas: () => {
        const { viewport } = WhiteboardController.elements;
        if(viewport && WhiteboardController.canvas) {
            WhiteboardController.canvas.setWidth(viewport.clientWidth);
            WhiteboardController.canvas.setHeight(viewport.clientHeight);
            WhiteboardController.canvas.renderAll();
        }
    },

    bindEvents: () => {
        const { btnScaleUp, btnScaleDown, btnDelete, btnColors, btnBorderOptions, 
                borderPopover, inpBorderWidth, inpBorderColor, inpBorderRadius } = WhiteboardController.elements;
        const canvas = WhiteboardController.canvas;

        const updateToolbar = () => {
            const activeObj = canvas.getActiveObject();
            WhiteboardController.toggleToolbar(!!activeObj);
            
            // Si hay objeto seleccionado, actualizamos los valores del toolbar y del popover (si está abierto)
            if (activeObj) {
                WhiteboardController.updateToolbarValues();
                WhiteboardController.syncPopoverValues(activeObj);
            } else {
                // Si se deselecciona, cerramos popover
                WhiteboardController.toggleBorderPopover(false);
            }
        };

        canvas.on('selection:created', updateToolbar);
        canvas.on('selection:updated', updateToolbar);
        canvas.on('selection:cleared', updateToolbar);
        canvas.on('object:modified', updateToolbar);
        canvas.on('object:scaling', updateToolbar);

        // --- BOTONES TOOLBAR ---
        if (btnScaleUp) btnScaleUp.addEventListener('click', () => WhiteboardController.modifySelectionScale(1.1));
        if (btnScaleDown) btnScaleDown.addEventListener('click', () => WhiteboardController.modifySelectionScale(0.9));
        
        if (btnDelete) {
            btnDelete.addEventListener('click', () => {
                const activeObjects = canvas.getActiveObjects();
                if (activeObjects.length) {
                    canvas.discardActiveObject();
                    activeObjects.forEach((obj) => canvas.remove(obj));
                }
            });
        }

        if (btnColors) {
            btnColors.addEventListener('click', (e) => {
                e.stopPropagation();
                WhiteboardController.openDrawer('drawer-colors', 'Colores');
                WhiteboardController.toggleBorderPopover(false); // Cerrar el otro si está abierto
            });
        }

        // --- NUEVO: BOTÓN DE OPCIONES DE BORDE ---
        if (btnBorderOptions) {
            btnBorderOptions.addEventListener('click', (e) => {
                e.stopPropagation();
                // Toggle visibilidad del popover
                const isActive = borderPopover.classList.contains('active');
                WhiteboardController.toggleBorderPopover(!isActive);
                // Si abrimos, cerramos el drawer lateral para limpiar la vista
                if (!isActive) WhiteboardController.closeDrawer();
            });
        }

        // --- EVENTOS DE INPUTS DEL POPOVER ---
        if (inpBorderWidth) {
            inpBorderWidth.addEventListener('input', (e) => {
                const val = parseInt(e.target.value, 10);
                WhiteboardController.elements.valBorderWidth.innerText = val;
                WhiteboardController.updateSelectionProp('strokeWidth', val);
                // Si el borde es > 0 y el color es transparente, ponerlo negro por defecto para que se vea
                if (val > 0) {
                    const obj = canvas.getActiveObject();
                    if (obj && (obj.stroke === 'transparent' || !obj.stroke)) {
                        WhiteboardController.updateSelectionProp('stroke', '#000000');
                        if (inpBorderColor) inpBorderColor.value = '#000000';
                    }
                }
            });
        }

        if (inpBorderColor) {
            inpBorderColor.addEventListener('input', (e) => {
                WhiteboardController.updateSelectionProp('stroke', e.target.value);
            });
        }

        if (inpBorderRadius) {
            inpBorderRadius.addEventListener('input', (e) => {
                const val = parseInt(e.target.value, 10);
                WhiteboardController.elements.valBorderRadius.innerText = val;
                // En Fabric.js rects usan rx y ry
                WhiteboardController.updateSelectionProp('rx', val);
                WhiteboardController.updateSelectionProp('ry', val);
            });
        }

        // --- CIERRE DE POPOVERS AL CLIC FUERA ---
        // Agregar listener global para cerrar el popover si clicamos fuera del toolbar/popover
        document.addEventListener('click', (e) => {
            if (borderPopover && borderPopover.classList.contains('active')) {
                // Si el clic NO fue dentro del popover NI dentro del botón que lo abre
                if (!borderPopover.contains(e.target) && !btnBorderOptions.contains(e.target)) {
                    WhiteboardController.toggleBorderPopover(false);
                }
            }
        });


        // --- MOUSE & KEYBOARD (Zoom/Pan) ---
        canvas.on('mouse:wheel', (opt) => {
            opt.e.preventDefault(); opt.e.stopPropagation();
            if (opt.e.ctrlKey) {
                let zoom = canvas.getZoom() * (0.999 ** opt.e.deltaY);
                if (zoom > WhiteboardController.config.maxScale) zoom = WhiteboardController.config.maxScale;
                if (zoom < WhiteboardController.config.minScale) zoom = WhiteboardController.config.minScale;
                canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
                WhiteboardController.updateUIFromCanvas();
            } else {
                const vpt = canvas.viewportTransform;
                vpt[4] -= opt.e.deltaX; vpt[5] -= opt.e.deltaY;
                canvas.requestRenderAll(); WhiteboardController.updateGridBackground();
            }
        });

        canvas.on('mouse:down', (opt) => {
            const evt = opt.e;
            if (evt.button === 1 || (evt.button === 0 && evt.shiftKey)) {
                WhiteboardController.state.panning = true;
                canvas.selection = false; 
                WhiteboardController.state.lastPosX = evt.clientX;
                WhiteboardController.state.lastPosY = evt.clientY;
                canvas.defaultCursor = 'grabbing';
            }
        });

        canvas.on('mouse:move', (opt) => {
            const evt = opt.e;
            WhiteboardController.updateStatusText(opt.absolutePointer);
            if (WhiteboardController.state.panning) {
                const vpt = canvas.viewportTransform;
                vpt[4] += evt.clientX - WhiteboardController.state.lastPosX;
                vpt[5] += evt.clientY - WhiteboardController.state.lastPosY;
                canvas.requestRenderAll();
                WhiteboardController.state.lastPosX = evt.clientX;
                WhiteboardController.state.lastPosY = evt.clientY;
                WhiteboardController.updateGridBackground();
            }
        });

        canvas.on('mouse:up', () => {
            if (WhiteboardController.state.panning) {
                WhiteboardController.state.panning = false;
                canvas.selection = true; canvas.defaultCursor = 'default';
                if (WhiteboardController.state.isShiftPressed) canvas.defaultCursor = 'grab';
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Shift') {
                WhiteboardController.state.isShiftPressed = true;
                if (!WhiteboardController.state.panning) canvas.defaultCursor = 'grab';
            }
            if ((e.key === 'Delete' || e.key === 'Backspace') && !WhiteboardController.isInputActive()) {
                const activeObjects = canvas.getActiveObjects();
                if (activeObjects.length) {
                    canvas.discardActiveObject();
                    activeObjects.forEach((obj) => canvas.remove(obj));
                }
            }
        });

        document.addEventListener('keyup', (e) => {
            if (e.key === 'Shift') {
                WhiteboardController.state.isShiftPressed = false;
                if (!WhiteboardController.state.panning) canvas.defaultCursor = 'default';
            }
        });

        if (WhiteboardController.elements.zoomSlider) {
            WhiteboardController.elements.zoomSlider.addEventListener('input', (e) => {
                const percent = parseInt(e.target.value, 10);
                const center = canvas.getCenter();
                canvas.zoomToPoint({ x: center.left, y: center.top }, percent / 100);
                WhiteboardController.updateUIFromCanvas();
            });
        }
        
        if (WhiteboardController.elements.btnCenter) WhiteboardController.elements.btnCenter.addEventListener('click', () => WhiteboardController.centerBoard());
    },

    bindSidebarEvents: () => { /* ... (Igual que antes) ... */ 
        const { drawer, btnCloseDrawer } = WhiteboardController.elements;
        if (!drawer) return;
        const toggleBtns = document.querySelectorAll('[data-drawer]');
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const contentId = btn.getAttribute('data-drawer');
                const contentEl = document.getElementById(contentId);
                const buttonTitle = btn.getAttribute('title');
                if (drawer.classList.contains('active') && contentEl.classList.contains('active')) {
                    WhiteboardController.closeDrawer(); return;
                }
                WhiteboardController.openDrawer(contentId, buttonTitle);
                toggleBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
        if (btnCloseDrawer) btnCloseDrawer.addEventListener('click', () => WhiteboardController.closeDrawer());
    },

    bindShapeEvents: () => {
        const shapeButtons = document.querySelectorAll('.wb-shape-card');
        shapeButtons.forEach(btn => {
            btn.addEventListener('click', () => { WhiteboardController.addShape(btn.getAttribute('data-shape')); });
        });
    },

    bindColorEvents: () => {
        const colorSwatches = document.querySelectorAll('.wb-color-swatch');
        colorSwatches.forEach(swatch => {
            swatch.addEventListener('click', (e) => {
                const color = swatch.getAttribute('data-color');
                WhiteboardController.setColorToSelection(color);
            });
        });
    },

    // --- MANEJO DE PROPIEDADES ---

    setColorToSelection: (color) => {
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return; 

        const applyColor = (obj) => {
            if (obj.type === 'path' || (obj.strokeWidth > 0 && (!obj.fill || obj.fill === ''))) {
                obj.set('stroke', color);
            } else {
                obj.set('fill', color);
            }
        };

        if (activeObj.type === 'activeSelection') {
            activeObj.getObjects().forEach(obj => applyColor(obj));
        } else {
            applyColor(activeObj);
        }
        canvas.requestRenderAll();
    },

    // Función genérica para actualizar propiedad
    updateSelectionProp: (prop, value) => {
        const canvas = WhiteboardController.canvas;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return;

        if (activeObj.type === 'activeSelection') {
            activeObj.getObjects().forEach(obj => obj.set(prop, value));
        } else {
            activeObj.set(prop, value);
        }
        canvas.requestRenderAll();
    },

    // --- UI HELPERS ---

    toggleToolbar: (show) => {
        const { toolbar } = WhiteboardController.elements;
        if (!toolbar) return;
        if (show) toolbar.classList.add('active');
        else {
            toolbar.classList.remove('active');
            WhiteboardController.toggleBorderPopover(false); // Cerrar popover si se oculta toolbar
        }
    },

    toggleBorderPopover: (show) => {
        const { borderPopover, btnBorderOptions } = WhiteboardController.elements;
        if (!borderPopover) return;
        if (show) {
            borderPopover.classList.add('active');
            if(btnBorderOptions) btnBorderOptions.classList.add('active');
        } else {
            borderPopover.classList.remove('active');
            if(btnBorderOptions) btnBorderOptions.classList.remove('active');
        }
    },

    // Sincroniza los inputs del popover con el objeto seleccionado
    syncPopoverValues: (obj) => {
        const { inpBorderWidth, valBorderWidth, inpBorderColor, inpBorderRadius, valBorderRadius, rowBorderRadius } = WhiteboardController.elements;
        
        // Si hay varios objetos, tomamos el primero como referencia
        if (obj.type === 'activeSelection') {
            obj = obj.getObjects()[0];
        }

        if (!obj) return;

        // Borde Width
        const width = obj.strokeWidth || 0;
        if (inpBorderWidth) inpBorderWidth.value = width;
        if (valBorderWidth) valBorderWidth.innerText = width;

        // Borde Color
        const color = obj.stroke || '#000000';
        // Convertimos 'transparent' a blanco o negro para el input color picker
        if (inpBorderColor) {
            inpBorderColor.value = (color === 'transparent') ? '#000000' : color;
        }

        // Borde Radio (Solo para Rect)
        if (obj.type === 'rect') {
            if (rowBorderRadius) rowBorderRadius.style.display = 'flex';
            const rx = obj.rx || 0;
            if (inpBorderRadius) inpBorderRadius.value = rx;
            if (valBorderRadius) valBorderRadius.innerText = rx;
        } else {
            // Ocultamos opción si no es rect
            if (rowBorderRadius) rowBorderRadius.style.display = 'none';
        }
    },

    updateToolbarValues: () => {
        const canvas = WhiteboardController.canvas;
        const { sizeDisplay } = WhiteboardController.elements;
        if (!canvas || !sizeDisplay) return;

        const activeObj = canvas.getActiveObject();
        if (!activeObj) return;

        let displayValue = "";
        if (activeObj.type === 'activeSelection') {
            const objects = activeObj.getObjects();
            let firstHeight = null;
            let isUniform = true;

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

    modifySelectionScale: (factor) => {
        const canvas = WhiteboardController.canvas;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return;
        activeObj.scale(activeObj.scaleX * factor);
        activeObj.setCoords();
        canvas.requestRenderAll();
        WhiteboardController.updateToolbarValues();
    },

    addShape: (type) => { /* ... igual que antes ... */ 
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;
        const vpt = canvas.viewportTransform;
        const w = canvas.getWidth(); const h = canvas.getHeight();
        const centerX = (-vpt[4] + w / 2) / canvas.getZoom();
        const centerY = (-vpt[5] + h / 2) / canvas.getZoom();

        const commonProps = {
            left: centerX, top: centerY,
            fill: '#000000', stroke: 'transparent', strokeWidth: 0, strokeUniform: true,
            originX: 'center', originY: 'center', objectCaching: false
        };

        let obj;
        switch (type) {
            case 'rect': obj = new fabric.Rect({ ...commonProps, width: 100, height: 100 }); break;
            case 'circle': obj = new fabric.Circle({ ...commonProps, radius: 50 }); break;
            case 'triangle': obj = new fabric.Triangle({ ...commonProps, width: 100, height: 100 }); break;
            case 'pentagon': obj = new fabric.Polygon(WhiteboardController.getRegularPolygonPoints(5, 50), { ...commonProps }); break;
            case 'hexagon': obj = new fabric.Polygon(WhiteboardController.getRegularPolygonPoints(6, 50), { ...commonProps }); break;
            case 'octagon': obj = new fabric.Polygon(WhiteboardController.getRegularPolygonPoints(8, 50), { ...commonProps }); break;
            case 'arrow-right': 
                obj = new fabric.Path('M 0 0 L 100 0 L 95 -10 M 100 0 L 95 10', { ...commonProps, fill: '', stroke: '#000000', strokeWidth: 4, strokeLineCap: 'round', strokeLineJoin: 'round' }); break;
            case 'arrow-left': 
                obj = new fabric.Path('M 100 0 L 0 0 L 5 -10 M 0 0 L 5 10', { ...commonProps, fill: '', stroke: '#000000', strokeWidth: 4, strokeLineCap: 'round', strokeLineJoin: 'round' }); break;
            case 'arrow-double': 
                obj = new fabric.Path('M 5 10 L 0 0 L 5 -10 M 0 0 L 100 0 L 95 -10 M 100 0 L 95 10', { ...commonProps, fill: '', stroke: '#000000', strokeWidth: 4, strokeLineCap: 'round', strokeLineJoin: 'round' }); break;
        }
        if (obj) { canvas.add(obj); canvas.setActiveObject(obj); canvas.requestRenderAll(); }
    },

    getRegularPolygonPoints: (sideCount, radius) => {
        const sweep = Math.PI * 2 / sideCount;
        const cx = 0, cy = 0; const points = [];
        const rotationOffset = -Math.PI / 2;
        for (let i = 0; i < sideCount; i++) {
            const x = cx + radius * Math.cos(i * sweep + rotationOffset);
            const y = cy + radius * Math.sin(i * sweep + rotationOffset);
            points.push({ x, y });
        }
        return points;
    },
    openDrawer: (id, t) => {
        const { drawer, drawerTitle } = WhiteboardController.elements;
        document.querySelectorAll('.wb-drawer-content').forEach(el => el.classList.remove('active'));
        const target = document.getElementById(id);
        if (target) target.classList.add('active');
        if (drawerTitle) drawerTitle.innerText = t || 'Menú';
        drawer.classList.add('active');
    },
    closeDrawer: () => {
        const { drawer } = WhiteboardController.elements;
        if (drawer) drawer.classList.remove('active');
        document.querySelectorAll('[data-drawer]').forEach(btn => btn.classList.remove('active'));
    },
    updateUIFromCanvas: () => {
        const canvas = WhiteboardController.canvas;
        const { zoomSlider, zoomDisplay } = WhiteboardController.elements;
        const zoom = canvas.getZoom(); const percent = Math.round(zoom * 100);
        if (zoomSlider && Math.abs(zoomSlider.value - percent) > 1) { zoomSlider.value = percent; WhiteboardController.updateSliderFill(); }
        if (zoomDisplay) zoomDisplay.innerText = `${percent}%`;
        WhiteboardController.updateGridBackground();
    },
    updateGridBackground: () => {
        const { viewport } = WhiteboardController.elements; const canvas = WhiteboardController.canvas;
        if (!viewport || !canvas) return;
        const vpt = canvas.viewportTransform; const zoom = canvas.getZoom();
        const bgSize = WhiteboardController.config.gridSize * zoom;
        viewport.style.backgroundPosition = `${vpt[4]}px ${vpt[5]}px`;
        viewport.style.backgroundSize = `${bgSize}px ${bgSize}px`;
    },
    updateSliderFill: () => {
        const slider = WhiteboardController.elements.zoomSlider; if (!slider) return;
        const { sliderFillColor, sliderBgColor } = WhiteboardController.config;
        const min = parseFloat(slider.min), max = parseFloat(slider.max), val = parseFloat(slider.value);
        const percentage = ((val - min) / (max - min)) * 100;
        slider.style.background = `linear-gradient(to right, ${sliderFillColor} 0%, ${sliderFillColor} ${percentage}%, ${sliderBgColor} ${percentage}%, ${sliderBgColor} 100%)`;
    },
    updateStatusText: (pointer) => {
        const { status } = WhiteboardController.elements; if (!status || !pointer) return;
        status.innerText = `X: ${Math.round(pointer.x)} Y: ${Math.round(pointer.y)}`;
    },
    centerBoard: () => {
        const canvas = WhiteboardController.canvas; if(!canvas) return;
        canvas.setZoom(1); const w = canvas.getWidth(); const h = canvas.getHeight();
        const vpt = canvas.viewportTransform; vpt[0] = 1; vpt[3] = 1; vpt[4] = w/2; vpt[5] = h/2;
        canvas.requestRenderAll(); WhiteboardController.updateUIFromCanvas();
    },
    isInputActive: () => {
        const el = document.activeElement;
        return (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable));
    }
};