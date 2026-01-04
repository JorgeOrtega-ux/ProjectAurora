/**
 * public/assets/js/modules/app/whiteboard-controller.js
 * Controlador principal, estado y orquestación
 */

import { WhiteboardPhysics } from './whiteboard-physics.js';
import { WhiteboardUI } from './whiteboard-ui.js';

export const WhiteboardController = {
    canvas: null,
    currentUUID: null, // Almacena el ID del pizarrón actual

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
        debugMode: false
    },
    
    config: {
        minScale: 0.1,
        maxScale: 5.0,
        selectionColor: 'rgba(0, 153, 255, 0.1)', 
        selectionBorderColor: '#0099ff',           
        controlColor: '#0099ff',                   
        controlBg: '#ffffff'                       
    },

    init: async () => {
        console.log("WhiteboardController: Iniciando...");
        
        // Capturar UUID del DOM
        const container = document.querySelector('.wb-container');
        if (container && container.dataset.wbUuid) {
            WhiteboardController.currentUUID = container.dataset.wbUuid;
            console.log("Whiteboard UUID:", WhiteboardController.currentUUID);
        }

        // Inicializar subsistemas
        WhiteboardUI.init();
        
        if (!WhiteboardUI.elements.viewport) return;

        WhiteboardController.initCanvas();
        WhiteboardController.bindEvents();
        WhiteboardController.bindShapeEvents();
        WhiteboardController.bindColorEvents(); 
        
        // Inicializar motor de física con referencia al canvas
        WhiteboardPhysics.init(WhiteboardController.canvas);

        window.addEventListener('resize', WhiteboardController.resizeCanvas);
        WhiteboardController.resizeCanvas();
        
        // IMPORTANTE: Cargar datos del servidor si existe un UUID
        if (WhiteboardController.currentUUID) {
            await WhiteboardController.loadFromServer(WhiteboardController.currentUUID);
        } else {
            // Si es nuevo o no hay datos, centrar y guardar estado inicial
            WhiteboardController.centerBoard();
            WhiteboardController.saveHistory();
        }
        
        // Iniciar loop de animación para objetos que giran y debug
        WhiteboardController.startSpinLoop();
    },

    // --- LÓGICA DE CLIENTE-SERVIDOR ---

    loadFromServer: async (uuid) => {
        try {
            // Mostrar estado de carga (opcional)
            console.log("Cargando pizarrón...");
            
            const response = await fetch(`api/whiteboard-handler.php?action=load&uuid=${uuid}`);
            const res = await response.json();

            if (res.success && res.data) {
                const data = res.data;
                
                // Cargar JSON en FabricJS
                WhiteboardController.canvas.loadFromJSON(data, () => {
                    console.log("Pizarrón cargado correctamente.");
                    
                    // Re-renderizar y reconstruir mundo físico
                    WhiteboardController.canvas.requestRenderAll();
                    WhiteboardPhysics.rebuildWorld(WhiteboardController.canvas.getObjects());
                    
                    // Guardar este estado como el inicial en el historial
                    WhiteboardController.saveHistory();
                    
                    // Asegurar que el zoom y centro sean correctos si vienen en el JSON o resetear
                    // (Opcional: aquí podrías guardar el viewportTransform en el JSON también)
                });
            } else {
                console.warn("No se encontraron datos o hubo error:", res.error);
            }
        } catch (error) {
            console.error("Error de red al cargar:", error);
        }
    },

    save: async () => {
        if (!WhiteboardController.currentUUID) {
            alert("Error: No se ha identificado el pizarrón (Falta UUID).");
            return;
        }

        const btnSave = document.getElementById('wb-btn-save');
        const originalIcon = btnSave ? btnSave.innerHTML : '';

        try {
            // Feedback Visual: Loading
            if (btnSave) {
                btnSave.classList.add('active-state');
                btnSave.innerHTML = '<span class="material-symbols-rounded vector-spin">refresh</span>';
            }

            // Preparar JSON
            // Es CRUCIAL incluir las propiedades personalizadas
            const propertiesToInclude = ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree', 'id', 'selectable'];
            const jsonContent = JSON.stringify(WhiteboardController.canvas.toJSON(propertiesToInclude));

            const response = await fetch('api/whiteboard-handler.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    uuid: WhiteboardController.currentUUID,
                    content: jsonContent
                })
            });

            const res = await response.json();

            if (res.success) {
                // Feedback: Éxito (Poner verde momentáneamente o usar ToastManager si estuviera importado)
                console.log("Guardado exitoso.");
                if (btnSave) {
                    btnSave.style.color = '#16a34a'; // Green
                    setTimeout(() => btnSave.style.color = '', 2000);
                }
            } else {
                throw new Error(res.error || "Error desconocido");
            }

        } catch (error) {
            console.error("Error al guardar:", error);
            alert("Error al guardar: " + error.message);
            if (btnSave) btnSave.style.color = '#ef4444'; // Red
        } finally {
            // Restaurar botón
            if (btnSave) {
                btnSave.classList.remove('active-state');
                btnSave.innerHTML = '<span class="material-symbols-rounded">save</span>';
            }
        }
    },

    // ----------------------------------

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

        // Listeners de Selección para UI
        const updateToolbar = () => {
            const activeObj = WhiteboardController.canvas.getActiveObject();
            WhiteboardUI.toggleToolbar(!!activeObj);
            
            if (activeObj) {
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

        // Variables para el cálculo de FPS
        let lastTime = performance.now();
        let frameCount = 0;

        const loop = () => {
            // --- CÁLCULO DE FPS ---
            const now = performance.now();
            frameCount++;
            const delta = now - lastTime;
            
            // Actualizar cada 1000ms (1 segundo)
            if (delta >= 1000) {
                const fps = Math.round((frameCount * 1000) / delta);
                WhiteboardUI.updateFPS(fps);
                frameCount = 0;
                lastTime = now;
            }
            // ---------------------

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

                // Actualizar Debug en tiempo real si está activo
                if (WhiteboardController.state.debugMode) {
                    WhiteboardController.updateDebugData();
                }
            }
            fabric.util.requestAnimFrame(loop);
        };
        fabric.util.requestAnimFrame(loop);
    },

    updateDebugData: () => {
        const objects = WhiteboardController.canvas.getObjects();
        const debugData = objects.map((obj, index) => {
            const data = {
                index: index,
                type: obj.type, // Tipo nativo de Fabric (rect, circle, polygon, path)
                customType: obj.customType || 'N/A', // Tipo personalizado (circle-cut, arrow-right, rect)
                position: { 
                    x: Math.round(obj.left), 
                    y: Math.round(obj.top) 
                },
                angle: Math.round(obj.angle),
                dimensions: {
                    width: Math.round(obj.width * obj.scaleX),
                    height: Math.round(obj.height * obj.scaleY)
                },
                style: {
                    fill: obj.fill,
                    stroke: obj.stroke,
                    strokeWidth: obj.strokeWidth
                }
            };

            if (obj.customType === 'circle-cut') {
                data.mechanics = {
                    isSpinning: obj.isSpinning,
                    speed: obj.spinSpeed,
                    aperture: obj.apertureDegree
                };
            } else if (obj.customType === 'conveyor') {
                data.mechanics = {
                    isSpinning: obj.isSpinning,
                    speed: obj.conveyorSpeed
                };
            }
            
            return data;
        });

        WhiteboardUI.updateDebugContent(debugData);
    },

    bindEvents: () => {
        const ui = WhiteboardUI.elements;
        const canvas = WhiteboardController.canvas;

        // VINCULACIÓN DEL BOTÓN GUARDAR (NUEVO)
        const btnSave = document.getElementById('wb-btn-save');
        if (btnSave) {
            btnSave.addEventListener('click', () => {
                WhiteboardController.save();
            });
        }

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
                
                // PROTECCIÓN: Filtrar mecánicas que no deberían tener físicas activables manuales
                const validObjects = activeObjects.filter(obj => 
                    obj.customType !== 'conveyor' && obj.customType !== 'circle-cut'
                );
                
                if (validObjects.length > 0) {
                    WhiteboardPhysics.activateForSelection(validObjects);
                    canvas.discardActiveObject();
                    canvas.requestRenderAll();
                }
            });
        }

        if (ui.btnColors) {
            ui.btnColors.addEventListener('click', (e) => {
                e.stopPropagation();
                if (ui.btnColors.disabled || ui.btnColors.classList.contains('disabled')) return;
                
                WhiteboardUI.openDrawer('drawer-colors', 'Colores');
                WhiteboardUI.toggleBorderPopover(false); 
            });
        }

        if (ui.btnMakeHollow) {
            ui.btnMakeHollow.addEventListener('click', () => {
                if (ui.btnMakeHollow.disabled || ui.btnMakeHollow.classList.contains('disabled')) return;
                WhiteboardController.makeSelectionHollow();
            });
        }

        if (ui.btnBorderOptions) {
            ui.btnBorderOptions.addEventListener('click', (e) => {
                e.stopPropagation();
                if (ui.btnBorderOptions.disabled || ui.btnBorderOptions.classList.contains('disabled')) return;

                const isActive = ui.borderPopover.classList.contains('active');
                WhiteboardUI.toggleBorderPopover(!isActive);
                if (!isActive) WhiteboardUI.closeDrawer();
            });
        }

        if (ui.btnDebug) {
            ui.btnDebug.addEventListener('click', () => {
                WhiteboardController.state.debugMode = !WhiteboardController.state.debugMode;
                WhiteboardUI.toggleDebugPanel(WhiteboardController.state.debugMode);
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
                
                // ATAJO GUARDAR (CTRL+S)
                if (e.code === 'KeyS') { e.preventDefault(); WhiteboardController.save(); }
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
    },

    bindShapeEvents: () => {
        const shapeButtons = document.querySelectorAll('.wb-shape-card');
        shapeButtons.forEach(btn => {
            btn.addEventListener('click', () => { 
                WhiteboardController.addShape(btn.getAttribute('data-shape')); 
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
    
    // --- COLORES Y FORMAS EXISTENTES ---

    setColorToSelection: (color) => {
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return; 

        const applyColor = (obj) => {
            // PROTECCIÓN: Las mecánicas no cambian de color (Circle Cut es hueco, Conveyor es textura)
            if (obj.customType === 'conveyor' || obj.customType === 'circle-cut') return;

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
            // PROTECCIÓN: No aplicar a mecánicas
            if (obj.customType === 'conveyor' || obj.customType === 'circle-cut') return;

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

        // Protección adicional para propiedades de borde en Conveyor
        if (prop === 'strokeWidth' || prop === 'stroke') {
             // Si es selección simple, chequeamos
             if (activeObj.customType === 'conveyor') return;
             
             // Si es selección múltiple, filtramos dentro del loop
        }

        const applyProp = (obj) => {
            if (obj.customType === 'conveyor' && (prop === 'strokeWidth' || prop === 'stroke' || prop === 'rx' || prop === 'ry')) return;
            obj.set(prop, value);
        }

        if (activeObj.type === 'activeSelection') {
            activeObj.getObjects().forEach(obj => applyProp(obj));
        } else {
            applyProp(activeObj);
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
        
        // MODIFICADO: Incluir propiedades personalizadas en el JSON para que el 'customType' se guarde
        // Esto es crucial para la futura exportación/importación
        const propertiesToInclude = ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree'];
        const json = JSON.stringify(WhiteboardController.canvas.toJSON(propertiesToInclude));
        
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
            // Clonar incluyendo las propiedades personalizadas importantes
            activeObj.clone((cloned) => { WhiteboardController.state.clipboard = cloned; }, 
            ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree']);
        }
    },

    paste: () => {
        if (!WhiteboardController.state.clipboard) return;
        
        // Al pegar, también necesitamos asegurar que las propiedades se clonen
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
        }, ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree']);
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
            // MODIFICADO: Asignar customType a TODOS los objetos
            // Usamos el 'type' del argumento (ej. 'rect', 'arrow-left') como identificador único
            if (!obj.customType) {
                obj.set('customType', type);
            }
            
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