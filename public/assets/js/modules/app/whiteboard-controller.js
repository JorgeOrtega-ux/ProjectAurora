/**
 * public/assets/js/modules/app/whiteboard-controller.js
 * Controlador principal, estado y orquestación
 */

import { WhiteboardPhysics } from './whiteboard-physics.js';
import { WhiteboardUI } from './whiteboard-ui.js';

export const WhiteboardController = {
    canvas: null,

    state: {
        panning: false,
        mouseX: 0, 
        mouseY: 0,
        isShiftPressed: false,
        lastPosX: 0,
        lastPosY: 0,
        clipboard: null,
        history: [],
        historyIndex: -1,
        historyLocked: false,
        isSpinLoopRunning: false,
        
        // Estado de dibujo
        drawTool: 'pen', // pen, marker, highlighter, eraser
        drawColor: '#000000',
        drawWidth: 3
    },
    
    config: {
        minScale: 0.1,
        maxScale: 5.0,
        selectionColor: 'rgba(0, 153, 255, 0.1)', 
        selectionBorderColor: '#0099ff',           
        controlColor: '#0099ff',                   
        controlBg: '#ffffff'                       
    },

    init: () => {
        console.log("WhiteboardController: Iniciando...");
        
        // Inicializar subsistemas
        WhiteboardUI.init();
        
        if (!WhiteboardUI.elements.viewport) return;

        WhiteboardController.initCanvas();
        WhiteboardController.bindEvents();
        WhiteboardController.bindShapeEvents();
        WhiteboardController.bindColorEvents(); 
        WhiteboardController.bindDrawEvents(); 
        
        WhiteboardController.saveHistory();
        
        // Inicializar motor de física con referencia al canvas
        WhiteboardPhysics.init(WhiteboardController.canvas);

        window.addEventListener('resize', WhiteboardController.resizeCanvas);
        WhiteboardController.resizeCanvas();
        WhiteboardController.centerBoard();
        
        // Iniciar loop de animación para objetos que giran
        WhiteboardController.startSpinLoop();
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
            selectionLineWidth: 1,
            isDrawingMode: false
        });

        // Configuración de controles Fabric
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

        // Listeners del Canvas
        WhiteboardController.canvas.on('object:added', (e) => {
            // No guardar historia o física si es un Path (dibujo), ya que eso se maneja en 'path:created'
            if (e.target.type !== 'path') {
                if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
                WhiteboardPhysics.addBody(e.target);
            }
        });
        
        WhiteboardController.canvas.on('object:removed', (e) => {
            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
            WhiteboardPhysics.removeBody(e.target);
        });

        WhiteboardController.canvas.on('object:modified', () => {
            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
        });

        WhiteboardController.canvas.on('object:moving', (e) => {
            WhiteboardPhysics.syncBodyPosition(e.target);
        });
        
        WhiteboardController.canvas.on('object:rotating', (e) => {
            WhiteboardPhysics.syncBodyRotation(e.target);
        });
        
        WhiteboardController.canvas.on('object:scaling', (e) => {
            WhiteboardPhysics.removeBody(e.target);
            WhiteboardPhysics.addBody(e.target);
        });
        
        // --- GESTIÓN DE FINALIZACIÓN DE TRAZO (DIBUJO Y BORRADOR) ---
        WhiteboardController.canvas.on('path:created', (e) => {
            e.path.set({ objectCaching: false });
            
            // LÓGICA DE BORRADOR REAL
            if (WhiteboardController.state.drawTool === 'eraser') {
                e.path.set({
                    globalCompositeOperation: 'destination-out', // ESTO HACE LA MAGIA: Borra/Corta
                    stroke: 'rgba(0,0,0,1)', // Color opaco necesario para cortar, aunque no se vea
                    selectable: false,
                    evented: false,
                    perPixelTargetFind: true
                });
                // NO agregamos física al borrador para evitar muros invisibles
            } else {
                // Si es un dibujo normal (boli, marcador), sí agregamos física
                WhiteboardPhysics.addBody(e.path);
            }

            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
        });

        // Listeners de Selección para UI
        const updateToolbar = () => {
            const activeObj = WhiteboardController.canvas.getActiveObject();
            WhiteboardUI.toggleToolbar(!!activeObj);
            
            if (activeObj) {
                // Si seleccionamos algo, salimos del modo dibujo
                if(WhiteboardController.canvas.isDrawingMode) {
                    WhiteboardController.setDrawingMode(false);
                }
                
                WhiteboardUI.updateToolbarValues(activeObj);
                WhiteboardUI.syncPopoverValues(activeObj);
            } else {
                WhiteboardUI.toggleBorderPopover(false);
            }
        };

        WhiteboardController.canvas.on('selection:created', updateToolbar);
        WhiteboardController.canvas.on('selection:updated', updateToolbar);
        WhiteboardController.canvas.on('selection:cleared', updateToolbar);
        WhiteboardController.canvas.on('object:modified', updateToolbar);
        WhiteboardController.canvas.on('object:scaling', updateToolbar);

        // Zoom y Panning
        WhiteboardController.canvas.on('mouse:wheel', (opt) => {
            opt.e.preventDefault(); opt.e.stopPropagation();
            if (opt.e.ctrlKey) {
                let zoom = WhiteboardController.canvas.getZoom() * (0.999 ** opt.e.deltaY);
                if (zoom > WhiteboardController.config.maxScale) zoom = WhiteboardController.config.maxScale;
                if (zoom < WhiteboardController.config.minScale) zoom = WhiteboardController.config.minScale;
                WhiteboardController.canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
                WhiteboardUI.updateUIFromCanvas(WhiteboardController.canvas);
            } else {
                const vpt = WhiteboardController.canvas.viewportTransform;
                vpt[4] -= opt.e.deltaX; vpt[5] -= opt.e.deltaY;
                WhiteboardController.canvas.requestRenderAll(); 
                WhiteboardUI.updateGridBackground(WhiteboardController.canvas);
            }
        });

        WhiteboardController.canvas.on('mouse:down', (opt) => {
            const evt = opt.e;
            if (evt.button === 1 || (evt.button === 0 && evt.shiftKey)) {
                WhiteboardController.state.panning = true;
                WhiteboardController.canvas.selection = false; 
                WhiteboardController.canvas.isDrawingMode = false; // Pausar dibujo temporalmente al hacer pan
                WhiteboardController.state.lastPosX = evt.clientX;
                WhiteboardController.state.lastPosY = evt.clientY;
                WhiteboardController.canvas.defaultCursor = 'grabbing';
            }
        });

        WhiteboardController.canvas.on('mouse:move', (opt) => {
            const evt = opt.e;
            WhiteboardUI.updateStatusText(opt.absolutePointer);
            if (WhiteboardController.state.panning) {
                const vpt = WhiteboardController.canvas.viewportTransform;
                vpt[4] += evt.clientX - WhiteboardController.state.lastPosX;
                vpt[5] += evt.clientY - WhiteboardController.state.lastPosY;
                WhiteboardController.canvas.requestRenderAll();
                WhiteboardController.state.lastPosX = evt.clientX;
                WhiteboardController.state.lastPosY = evt.clientY;
                WhiteboardUI.updateGridBackground(WhiteboardController.canvas);
            }
        });

        WhiteboardController.canvas.on('mouse:up', () => {
            if (WhiteboardController.state.panning) {
                WhiteboardController.state.panning = false;
                WhiteboardController.canvas.selection = true; 
                // Restaurar modo dibujo si estaba activo
                if (WhiteboardUI.elements.drawer.querySelector('[data-drawer="drawer-draw"]').classList.contains('active')) {
                     WhiteboardController.canvas.isDrawingMode = true;
                }
                
                WhiteboardController.canvas.defaultCursor = 'default';
                if (WhiteboardController.state.isShiftPressed) WhiteboardController.canvas.defaultCursor = 'grab';
            }
        });
    },

    resizeCanvas: () => {
        const { viewport } = WhiteboardUI.elements;
        if(viewport && WhiteboardController.canvas) {
            WhiteboardController.canvas.setWidth(viewport.clientWidth);
            WhiteboardController.canvas.setHeight(viewport.clientHeight);
            WhiteboardController.canvas.renderAll();
        }
    },

    startSpinLoop: () => {
        if (WhiteboardController.state.isSpinLoopRunning) return;
        WhiteboardController.state.isSpinLoopRunning = true;

        const loop = () => {
            const canvas = WhiteboardController.canvas;
            if (canvas) {
                const objects = canvas.getObjects();
                let needsRender = false;

                objects.forEach(obj => {
                    // Animación del Círculo (Rotación)
                    if (obj.customType === 'circle-cut' && obj.isSpinning && obj.spinSpeed !== 0) {
                        obj.angle += parseFloat(obj.spinSpeed);
                        obj.angle = obj.angle % 360;
                        obj.setCoords(); 
                        WhiteboardPhysics.syncBodyRotation(obj);
                        needsRender = true;
                    }
                    // Animación de la Cinta Transportadora (Desplazamiento de línea)
                    else if (obj.customType === 'conveyor' && obj.isSpinning && obj.conveyorSpeed !== 0) {
                        const speed = obj.conveyorSpeed || 0;
                        obj.strokeDashOffset -= speed; 
                        if (Math.abs(obj.strokeDashOffset) > 1000) obj.strokeDashOffset = 0;
                        needsRender = true;
                    }
                });

                if (needsRender) {
                    canvas.requestRenderAll();
                }
            }
            fabric.util.requestAnimFrame(loop);
        };
        fabric.util.requestAnimFrame(loop);
    },

    bindEvents: () => {
        const ui = WhiteboardUI.elements;
        const canvas = WhiteboardController.canvas;

        if (ui.btnScaleUp) ui.btnScaleUp.addEventListener('click', () => WhiteboardController.modifySelectionScale(1.1));
        if (ui.btnScaleDown) ui.btnScaleDown.addEventListener('click', () => WhiteboardController.modifySelectionScale(0.9));
        
        if (ui.btnDelete) {
            ui.btnDelete.addEventListener('click', () => {
                const activeObjects = canvas.getActiveObjects();
                if (activeObjects.length) {
                    canvas.discardActiveObject();
                    activeObjects.forEach((obj) => canvas.remove(obj));
                }
            });
        }

        if (ui.btnUndo) ui.btnUndo.addEventListener('click', () => WhiteboardController.undo());
        if (ui.btnRedo) ui.btnRedo.addEventListener('click', () => WhiteboardController.redo());
        
        if (ui.btnPhysicsAll) {
            ui.btnPhysicsAll.addEventListener('click', () => {
                const enable = !WhiteboardPhysics.globalEnabled;
                WhiteboardPhysics.toggleGlobal(enable);
                if (enable) ui.btnPhysicsAll.classList.add('active-state');
                else ui.btnPhysicsAll.classList.remove('active-state');
            });
        }
        
        if (ui.btnPhysicsSelected) {
            ui.btnPhysicsSelected.addEventListener('click', () => {
                const activeObjects = canvas.getActiveObjects();
                WhiteboardPhysics.activateForSelection(activeObjects);
                canvas.discardActiveObject();
                canvas.requestRenderAll();
            });
        }

        if (ui.btnColors) {
            ui.btnColors.addEventListener('click', (e) => {
                e.stopPropagation();
                WhiteboardUI.openDrawer('drawer-colors', 'Colores');
                WhiteboardUI.toggleBorderPopover(false); 
                WhiteboardController.setDrawingMode(false); // Salir de modo dibujo al abrir colores de relleno
            });
        }

        if (ui.btnMakeHollow) {
            ui.btnMakeHollow.addEventListener('click', () => {
                WhiteboardController.makeSelectionHollow();
            });
        }

        if (ui.btnBorderOptions) {
            ui.btnBorderOptions.addEventListener('click', (e) => {
                e.stopPropagation();
                const isActive = ui.borderPopover.classList.contains('active');
                WhiteboardUI.toggleBorderPopover(!isActive);
                if (!isActive) WhiteboardUI.closeDrawer();
            });
        }

        if (ui.inpApertureSize) {
            ui.inpApertureSize.addEventListener('input', (e) => {
                const activeObj = canvas.getActiveObject();
                if (!activeObj || activeObj.customType !== 'circle-cut') return;
                
                const val = parseInt(e.target.value, 10);
                activeObj.set('apertureDegree', val);
                
                const startDeg = val / 2;
                const endDeg = 360 - (val / 2);
                
                activeObj.set({
                    startAngle: startDeg,
                    endAngle: endDeg
                });
                
                activeObj.setCoords();
                canvas.requestRenderAll();
                
                WhiteboardPhysics.removeBody(activeObj);
                WhiteboardPhysics.addBody(activeObj);
            });
            
            ui.inpApertureSize.addEventListener('change', () => {
                WhiteboardController.saveHistory();
            });
        }

        // --- EVENTOS DE GIRO Y CINTA ---
        if (ui.btnSpinToggle) {
            ui.btnSpinToggle.addEventListener('click', () => {
                const activeObj = canvas.getActiveObject();
                // Permitir si es círculo o cinta
                if (!activeObj || (activeObj.customType !== 'circle-cut' && activeObj.customType !== 'conveyor')) return;
                
                // Toggle isSpinning
                const currentState = (activeObj.isSpinning !== undefined) ? activeObj.isSpinning : (activeObj.customType === 'conveyor');
                const newState = !currentState;
                activeObj.set('isSpinning', newState);
                
                WhiteboardUI.updateToolbarValues(activeObj);

                // Si es conveyor, necesitamos actualizar la física inmediatamente para que se detenga/arranque
                if (activeObj.customType === 'conveyor') {
                    WhiteboardPhysics.removeBody(activeObj);
                    WhiteboardPhysics.addBody(activeObj);
                }
            });
        }

        if (ui.inpSpinSpeed) {
            ui.inpSpinSpeed.addEventListener('input', (e) => {
                const val = parseInt(e.target.value, 10);
                if (ui.valSpinSpeed) ui.valSpinSpeed.innerText = val;
                
                const activeObj = canvas.getActiveObject();
                if (activeObj) {
                     if(activeObj.customType === 'circle-cut') {
                        activeObj.set('spinSpeed', val);
                     } else if (activeObj.customType === 'conveyor') {
                        // Actualizar velocidad de la cinta
                        activeObj.set('conveyorSpeed', val);
                        // Recrear cuerpo físico para actualizar el plugin.speed
                        WhiteboardPhysics.removeBody(activeObj);
                        WhiteboardPhysics.addBody(activeObj);
                     }
                }
            });
        }

        if (ui.inpBorderWidth) {
            ui.inpBorderWidth.addEventListener('input', (e) => {
                const val = parseInt(e.target.value, 10);
                ui.valBorderWidth.innerText = val;
                WhiteboardController.updateSelectionProp('strokeWidth', val);
                if (val > 0) {
                    const obj = canvas.getActiveObject();
                    if (obj && (obj.stroke === 'transparent' || !obj.stroke)) {
                        WhiteboardController.updateSelectionProp('stroke', '#000000');
                        if (ui.inpBorderColor) ui.inpBorderColor.value = '#000000';
                    }
                }
            });
        }

        if (ui.inpBorderColor) {
            ui.inpBorderColor.addEventListener('input', (e) => {
                WhiteboardController.updateSelectionProp('stroke', e.target.value);
            });
        }

        if (ui.inpBorderRadius) {
            ui.inpBorderRadius.addEventListener('input', (e) => {
                const val = parseInt(e.target.value, 10);
                ui.valBorderRadius.innerText = val;
                WhiteboardController.updateSelectionProp('rx', val);
                WhiteboardController.updateSelectionProp('ry', val);
            });
        }

        document.addEventListener('click', (e) => {
            if (ui.borderPopover && ui.borderPopover.classList.contains('active')) {
                if (!ui.borderPopover.contains(e.target) && !ui.btnBorderOptions.contains(e.target)) {
                    WhiteboardUI.toggleBorderPopover(false);
                }
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

            const isCtrl = e.ctrlKey || e.metaKey;
            if (isCtrl && !WhiteboardController.isInputActive()) {
                if (e.code === 'KeyC') { e.preventDefault(); WhiteboardController.copy(); }
                if (e.code === 'KeyV') { e.preventDefault(); WhiteboardController.paste(); }
                if (e.code === 'KeyZ' && !e.shiftKey) { e.preventDefault(); WhiteboardController.undo(); }
                if ((e.code === 'KeyY') || (e.code === 'KeyZ' && e.shiftKey)) { e.preventDefault(); WhiteboardController.redo(); }
            }
        });

        document.addEventListener('keyup', (e) => {
            if (e.key === 'Shift') {
                WhiteboardController.state.isShiftPressed = false;
                if (!WhiteboardController.state.panning) canvas.defaultCursor = 'default';
            }
        });

        if (ui.zoomSlider) {
            ui.zoomSlider.addEventListener('input', (e) => {
                const percent = parseInt(e.target.value, 10);
                const center = canvas.getCenter();
                canvas.zoomToPoint({ x: center.left, y: center.top }, percent / 100);
                WhiteboardUI.updateUIFromCanvas(canvas);
            });
        }
        
        if (ui.btnCenter) ui.btnCenter.addEventListener('click', () => WhiteboardController.centerBoard());
        
        // Listener sidebar general para desactivar dibujo si se cambia de tab
        const sidebarBtns = document.querySelectorAll('.wb-sidebar-btn[data-drawer]');
        sidebarBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-drawer');
                if (target !== 'drawer-draw') {
                    WhiteboardController.setDrawingMode(false);
                } else {
                    // Si clicamos en Dibujo, reactivamos el último estado
                    WhiteboardController.setDrawingMode(true);
                }
            });
        });
    },

    bindShapeEvents: () => {
        const shapeButtons = document.querySelectorAll('.wb-shape-card');
        shapeButtons.forEach(btn => {
            btn.addEventListener('click', () => { 
                WhiteboardController.addShape(btn.getAttribute('data-shape')); 
                WhiteboardController.setDrawingMode(false); // Desactivar dibujo al añadir forma
            });
        });
    },

    bindColorEvents: () => {
        const colorSwatches = document.querySelectorAll('.wb-color-swatch:not([data-draw-color])');
        colorSwatches.forEach(swatch => {
            swatch.addEventListener('click', (e) => {
                const color = swatch.getAttribute('data-color');
                WhiteboardController.setColorToSelection(color);
            });
        });
    },
    
    bindDrawEvents: () => {
        const ui = WhiteboardUI.elements;
        
        // Botones de Herramienta (Boli, Marcador, Resaltador, Borrador)
        ui.drawBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tool = btn.getAttribute('data-tool');
                WhiteboardController.setDrawingTool(tool);
            });
        });
        
        // Slider de Grosor de Dibujo
        if (ui.inpDrawWidth) {
            ui.inpDrawWidth.addEventListener('input', (e) => {
                const val = parseInt(e.target.value, 10);
                if (ui.valDrawWidth) ui.valDrawWidth.innerText = val;
                WhiteboardController.state.drawWidth = val;
                WhiteboardController.updateBrushSettings();
            });
        }
        
        // Paleta de Color de Dibujo
        ui.drawColorSwatches.forEach(swatch => {
            swatch.addEventListener('click', (e) => {
                const color = swatch.getAttribute('data-draw-color');
                WhiteboardController.state.drawColor = color;
                
                // Si la herramienta es borrador, cambiamos a boli para que pinte
                if (WhiteboardController.state.drawTool === 'eraser') {
                    WhiteboardController.setDrawingTool('pen');
                } else {
                    WhiteboardController.updateBrushSettings();
                }
            });
        });
    },

    // --- LÓGICA DE DIBUJO ---

    setDrawingMode: (isActive) => {
        const canvas = WhiteboardController.canvas;
        if(!canvas) return;
        
        canvas.isDrawingMode = isActive;
        if (isActive) {
            canvas.discardActiveObject();
            canvas.requestRenderAll();
            WhiteboardController.updateBrushSettings();
        }
    },

    setDrawingTool: (tool) => {
        WhiteboardController.state.drawTool = tool;
        WhiteboardUI.setActiveDrawTool(tool);
        
        // Activar modo dibujo si no lo está
        if (!WhiteboardController.canvas.isDrawingMode) {
            WhiteboardController.setDrawingMode(true);
        }
        
        // Actualizar parámetros por defecto según herramienta
        const ui = WhiteboardUI.elements;
        let defaultWidth = 3;
        
        if (tool === 'marker') defaultWidth = 10;
        else if (tool === 'highlighter') defaultWidth = 20;
        else if (tool === 'eraser') defaultWidth = 20;
        
        // Actualizar UI del slider sin disparar evento
        WhiteboardController.state.drawWidth = defaultWidth;
        if (ui.inpDrawWidth) ui.inpDrawWidth.value = defaultWidth;
        if (ui.valDrawWidth) ui.valDrawWidth.innerText = defaultWidth;
        
        WhiteboardController.updateBrushSettings();
    },

    updateBrushSettings: () => {
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;

        const { drawTool, drawColor, drawWidth } = WhiteboardController.state;
        
        // Instancia base
        canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
        
        if (drawTool === 'eraser') {
            // Mientras dibujamos, se ve blanco/goma, pero al soltar (path:created) se convierte en transparente
            canvas.freeDrawingBrush.color = 'rgba(255,255,255,0.7)'; 
            canvas.freeDrawingBrush.width = drawWidth;
            canvas.freeDrawingBrush.strokeLineCap = 'round';
        } 
        else if (drawTool === 'highlighter') {
            // Resaltador: Color con transparencia
            const rgba = WhiteboardController.hexToRgba(drawColor, 0.5);
            canvas.freeDrawingBrush.color = rgba;
            canvas.freeDrawingBrush.width = drawWidth;
            canvas.freeDrawingBrush.strokeLineCap = 'butt'; 
        }
        else if (drawTool === 'marker') {
            canvas.freeDrawingBrush.color = drawColor;
            canvas.freeDrawingBrush.width = drawWidth;
            canvas.freeDrawingBrush.strokeLineCap = 'round';
        } 
        else {
            // Pen (Bolígrafo)
            canvas.freeDrawingBrush.color = drawColor;
            canvas.freeDrawingBrush.width = drawWidth;
        }
    },

    hexToRgba: (hex, alpha) => {
        let c;
        if(/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)){
            c= hex.substring(1).split('');
            if(c.length== 3){
                c= [c[0], c[0], c[1], c[1], c[2], c[2]];
            }
            c= '0x'+c.join('');
            return 'rgba('+[(c>>16)&255, (c>>8)&255, c&255].join(',')+','+alpha+')';
        }
        return hex;
    },

    // --- COLORES Y FORMAS EXISTENTES ---

    setColorToSelection: (color) => {
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return; 

        const applyColor = (obj) => {
            if (color === 'transparent') {
                obj.set('fill', 'transparent');
            } else {
                if (obj.type === 'path' && (!obj.fill || obj.fill === 'transparent' || obj.fill === '')) {
                     obj.set('stroke', color);
                } else {
                    obj.set('fill', color);
                }
            }
            // Re-generar física
            WhiteboardPhysics.removeBody(obj);
            WhiteboardPhysics.addBody(obj);
        };

        if (activeObj.type === 'activeSelection') {
            activeObj.getObjects().forEach(obj => applyColor(obj));
        } else {
            applyColor(activeObj);
        }
        
        WhiteboardUI.syncPopoverValues(activeObj);
        canvas.requestRenderAll();
        WhiteboardController.saveHistory(); 
    },

    makeSelectionHollow: () => {
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return;

        const applyHollow = (obj) => {
            obj.set({
                fill: 'transparent',
                stroke: '#000000', 
                strokeWidth: 2     
            });
            WhiteboardPhysics.removeBody(obj);
            WhiteboardPhysics.addBody(obj);
        };

        if (activeObj.type === 'activeSelection') {
            activeObj.getObjects().forEach(obj => applyHollow(obj));
        } else {
            applyHollow(activeObj);
        }

        WhiteboardUI.syncPopoverValues(activeObj);
        canvas.requestRenderAll();
        WhiteboardController.saveHistory();
    },

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
        WhiteboardController.saveHistory(); 
    },

    modifySelectionScale: (factor) => {
        const canvas = WhiteboardController.canvas;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return;
        activeObj.scale(activeObj.scaleX * factor);
        activeObj.setCoords();
        canvas.requestRenderAll();
        WhiteboardUI.updateToolbarValues(activeObj);
        WhiteboardController.saveHistory(); 
    },

    // --- LÓGICA DE HISTORIAL Y PORTAPAPELES ---

    saveHistory: () => {
        if (WhiteboardController.state.historyLocked) return;
        if (WhiteboardController.state.historyIndex < WhiteboardController.state.history.length - 1) {
            WhiteboardController.state.history = WhiteboardController.state.history.slice(0, WhiteboardController.state.historyIndex + 1);
        }
        const json = JSON.stringify(WhiteboardController.canvas);
        WhiteboardController.state.history.push(json);
        WhiteboardController.state.historyIndex++;
        
        if (WhiteboardController.state.history.length > 50) {
            WhiteboardController.state.history.shift();
            WhiteboardController.state.historyIndex--;
        }
    },

    undo: () => {
        if (WhiteboardController.state.historyIndex > 0) {
            WhiteboardController.state.historyLocked = true; 
            WhiteboardController.state.historyIndex--;
            const prevState = WhiteboardController.state.history[WhiteboardController.state.historyIndex];
            WhiteboardController.canvas.loadFromJSON(prevState, () => {
                WhiteboardController.canvas.renderAll();
                WhiteboardController.state.historyLocked = false;
                WhiteboardPhysics.rebuildWorld(WhiteboardController.canvas.getObjects());
            });
        }
    },

    redo: () => {
        if (WhiteboardController.state.historyIndex < WhiteboardController.state.history.length - 1) {
            WhiteboardController.state.historyLocked = true;
            WhiteboardController.state.historyIndex++;
            const nextState = WhiteboardController.state.history[WhiteboardController.state.historyIndex];
            WhiteboardController.canvas.loadFromJSON(nextState, () => {
                WhiteboardController.canvas.renderAll();
                WhiteboardController.state.historyLocked = false;
                WhiteboardPhysics.rebuildWorld(WhiteboardController.canvas.getObjects());
            });
        }
    },

    copy: () => {
        const activeObj = WhiteboardController.canvas.getActiveObject();
        if (activeObj) {
            activeObj.clone((cloned) => { WhiteboardController.state.clipboard = cloned; });
        }
    },

    paste: () => {
        if (!WhiteboardController.state.clipboard) return;
        WhiteboardController.state.clipboard.clone((clonedObj) => {
            WhiteboardController.canvas.discardActiveObject();
            clonedObj.set({ left: clonedObj.left + 20, top: clonedObj.top + 20, evented: true });
            if (clonedObj.type === 'activeSelection') {
                clonedObj.canvas = WhiteboardController.canvas;
                clonedObj.forEachObject((obj) => { WhiteboardController.canvas.add(obj); });
                clonedObj.setCoords();
            } else {
                WhiteboardController.canvas.add(clonedObj);
            }
            WhiteboardController.canvas.setActiveObject(clonedObj);
            WhiteboardController.canvas.requestRenderAll();
            WhiteboardController.saveHistory();
        });
    },

    // --- CREACIÓN DE FORMAS ---

    addShape: (type) => {
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
            
            case 'line':
                obj = new fabric.Line([0, 0, 150, 0], {
                    ...commonProps,
                    fill: '',
                    stroke: '#000000',
                    strokeWidth: 4,
                    strokeLineCap: 'round'
                });
                break;

            case 'circle-cut':
                const apertureDeg = 45;
                const startDeg = apertureDeg / 2;
                const endDeg = 360 - (apertureDeg / 2);
                
                obj = new fabric.Circle({
                    ...commonProps,
                    radius: 50,
                    fill: 'transparent',
                    stroke: '#000000',
                    strokeWidth: 10,
                    startAngle: startDeg, 
                    endAngle: endDeg      
                });
                
                obj.set('customType', 'circle-cut');
                obj.set('apertureDegree', apertureDeg);
                obj.set('isSpinning', false);
                obj.set('spinSpeed', 2);
                break;

            case 'conveyor':
                obj = new fabric.Rect({
                    ...commonProps,
                    width: 250, height: 40,
                    fill: '#e5e7eb',
                    stroke: '#4b5563',
                    strokeWidth: 3,
                    strokeDashArray: [10, 5], 
                    rx: 5, ry: 5
                });
                obj.set('customType', 'conveyor');
                obj.set('conveyorSpeed', 5); 
                obj.set('isSpinning', false); 
                break;
        }
        if (obj) { 
            canvas.add(obj); 
            canvas.setActiveObject(obj); 
            canvas.requestRenderAll(); 
        }
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

    // --- UTILS ---

    centerBoard: () => {
        const canvas = WhiteboardController.canvas; if(!canvas) return;
        canvas.setZoom(1); const w = canvas.getWidth(); const h = canvas.getHeight();
        const vpt = canvas.viewportTransform; vpt[0] = 1; vpt[3] = 1; vpt[4] = w/2; vpt[5] = h/2;
        canvas.requestRenderAll(); 
        WhiteboardUI.updateUIFromCanvas(canvas);
    },

    isInputActive: () => {
        const el = document.activeElement;
        return (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable));
    }
};