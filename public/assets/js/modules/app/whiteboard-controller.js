/**
 * public/assets/js/modules/app/whiteboard-controller.js
 * Controlador principal con Logs de Diagnóstico, Throttling y AUTOGUARDADO OPTIMIZADO
 */

import { WhiteboardPhysics } from './whiteboard-physics.js';
import { WhiteboardUI } from './whiteboard-ui.js';
import { WebSocketManager } from '../../core/websocket-manager.js';

export const WhiteboardController = {
    canvas: null,
    currentUUID: null, 

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
        debugMode: false,
        isRemoteUpdate: false,
        isLoading: false,
        
        // --- ESTADO AUTOGUARDADO ---
        saveTimer: null,
        isSaving: false,
        isSavePending: false // Optimización para no tocar el DOM constantemente
    },
    
    config: {
        minScale: 0.1,
        maxScale: 5.0,
        selectionColor: 'rgba(0, 153, 255, 0.1)', 
        selectionBorderColor: '#0099ff',           
        controlColor: '#0099ff',                   
        controlBg: '#ffffff',
        autoSaveDelay: 2000 
    },

    generateUUID: () => {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    },

    init: async () => {
        console.log("WhiteboardController: Iniciando...");
        
        const container = document.querySelector('.wb-container');
        if (container && container.dataset.wbUuid) {
            WhiteboardController.currentUUID = container.dataset.wbUuid;
            console.log("Whiteboard UUID:", WhiteboardController.currentUUID);
        }

        WhiteboardUI.init();
        
        if (!WhiteboardUI.elements.viewport) return;

        WhiteboardController.initCanvas();
        WhiteboardController.bindEvents();
        WhiteboardController.bindShapeEvents();
        WhiteboardController.bindColorEvents(); 
        
        WhiteboardPhysics.init(WhiteboardController.canvas);

        window.addEventListener('resize', WhiteboardController.resizeCanvas);
        WhiteboardController.resizeCanvas();
        
        if (WhiteboardController.currentUUID) {
            await WhiteboardController.loadFromServer(WhiteboardController.currentUUID);
            WebSocketManager.connect(WhiteboardController.currentUUID);
            WebSocketManager.subscribe(WhiteboardController.handleRemoteMessage);
        } else {
            WhiteboardController.centerBoard();
            WhiteboardController.saveHistory();
        }
        
        WhiteboardController.startSpinLoop();
    },

    // --- MANEJADOR DE MENSAJES REMOTOS ---
    handleRemoteMessage: (msg) => {
        if (!msg || !msg.type) return;
        
        if(msg.type !== 'system') {
            // console.log(`%c[WS RX] ${msg.type}`, 'color: #00d2ff;', msg);
        }

        const canvas = WhiteboardController.canvas;
        WhiteboardController.state.isRemoteUpdate = true;

        try {
            if (msg.type === 'OBJECT_UPDATE') {
                const obj = WhiteboardController.findObjectById(msg.objectId);
                if (obj) {
                    obj.set(msg.data);
                    obj.setCoords();
                    WhiteboardPhysics.syncBodyPosition(obj); 
                    canvas.requestRenderAll();
                }
            } 
            else if (msg.type === 'OBJECT_ADDED') {
                if (!WhiteboardController.findObjectById(msg.data.id)) {
                    fabric.util.enlivenObjects([msg.data], (enlivenedObjects) => {
                        enlivenedObjects.forEach((obj) => {
                            canvas.add(obj);
                        });
                        canvas.requestRenderAll();
                    });
                }
            } 
            else if (msg.type === 'OBJECT_REMOVED') {
                const obj = WhiteboardController.findObjectById(msg.objectId);
                if (obj) {
                    canvas.remove(obj);
                    canvas.requestRenderAll();
                }
            }
        } catch (error) {
            console.error("Error WS:", error);
        } finally {
            WhiteboardController.state.isRemoteUpdate = false;
        }
    },

    findObjectById: (id) => {
        return WhiteboardController.canvas.getObjects().find(o => o.id === id);
    },

    loadFromServer: async (uuid) => {
        try {
            console.log("Cargando pizarrón...");
            const response = await fetch(`${window.BASE_PATH}api/whiteboard-handler.php?action=load&uuid=${uuid}`);
            const res = await response.json();

            if (res.success && res.data) {
                const data = res.data;
                WhiteboardController.state.isLoading = true;

                WhiteboardController.canvas.loadFromJSON(data, () => {
                    console.log("Pizarrón cargado.");
                    WhiteboardController.state.isLoading = false;
                    WhiteboardController.canvas.requestRenderAll();
                    WhiteboardPhysics.rebuildWorld(WhiteboardController.canvas.getObjects());
                    WhiteboardController.saveHistory();
                });
            } else {
                console.warn("Error cargar datos:", res.error);
            }
        } catch (error) {
            console.error("Error red:", error);
            WhiteboardController.state.isLoading = false;
        }
    },

    // --- AUTOGUARDADO OPTIMIZADO (Sin lag) ---
    triggerAutoSave: () => {
        // 1. Reiniciar timer
        if (WhiteboardController.state.saveTimer) {
            clearTimeout(WhiteboardController.state.saveTimer);
        }

        // 2. Optimización: Solo tocar el DOM si NO estaba ya pendiente
        // Esto evita recálculos de estilo masivos mientras mueves el mouse
        if (!WhiteboardController.state.isSavePending) {
            const btnSave = document.getElementById('wb-btn-save');
            if (btnSave) btnSave.style.color = '#fbbf24'; // Color "Pendiente" (Ámbar)
            WhiteboardController.state.isSavePending = true;
        }

        // 3. Programar guardado
        WhiteboardController.state.saveTimer = setTimeout(() => {
            WhiteboardController.save(true);
        }, WhiteboardController.config.autoSaveDelay);
    },

    save: async (silentMode = false) => {
        if (!WhiteboardController.currentUUID) {
            if (!silentMode) alert("Falta UUID.");
            return;
        }

        if (WhiteboardController.state.isSaving) return;
        WhiteboardController.state.isSaving = true;

        const btnSave = document.getElementById('wb-btn-save');

        try {
            if (btnSave && !silentMode) {
                btnSave.classList.add('active-state');
                btnSave.innerHTML = '<span class="material-symbols-rounded vector-spin">refresh</span>';
            }

            const propertiesToInclude = ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree', 'id', 'selectable'];
            const jsonContent = JSON.stringify(WhiteboardController.canvas.toJSON(propertiesToInclude));

            const response = await fetch(`${window.BASE_PATH}api/whiteboard-handler.php?action=save`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    uuid: WhiteboardController.currentUUID,
                    content: jsonContent
                })
            });

            const res = await response.json();

            if (res.success) {
                if (btnSave) {
                    btnSave.style.color = '#16a34a'; // Verde (Éxito)
                    setTimeout(() => {
                        // Solo quitar el verde si no hay otro guardado pendiente
                        if (!WhiteboardController.state.isSavePending) btnSave.style.color = ''; 
                    }, 2000);
                }
            } else {
                throw new Error(res.error || "Error desconocido");
            }

        } catch (error) {
            console.error("Error save:", error);
            if (!silentMode) alert("Error al guardar: " + error.message);
            if (btnSave) btnSave.style.color = '#ef4444'; 
        } finally {
            WhiteboardController.state.isSaving = false;
            WhiteboardController.state.isSavePending = false; // Resetear bandera
            
            if (btnSave) {
                if (!silentMode) {
                    btnSave.classList.remove('active-state');
                    btnSave.innerHTML = '<span class="material-symbols-rounded">save</span>';
                }
                // Si no hay nada pendiente, restaurar color (por si acaso)
                if (!WhiteboardController.state.saveTimer) {
                     // Dejar que el timeout del éxito maneje el color
                }
            }
        }
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

        // Renderizado de controles personalizados (Pildoras)
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

        // =========================================================================
        // --- LOGICA WS (Throttling) ---
        // =========================================================================

        const throttle = (func, limit) => {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            }
        };

        const sendObjectUpdate = (obj, sourceEvent) => {
            if (!obj.id) return;
            
            if (WhiteboardController.state.debugMode) {
                console.log(`[WS TX] Enviando (${sourceEvent})...`);
            }

            const updateData = {
                left: obj.left, top: obj.top, angle: obj.angle,
                scaleX: obj.scaleX, scaleY: obj.scaleY,
                fill: obj.fill, stroke: obj.stroke, strokeWidth: obj.strokeWidth,
                customType: obj.customType, isSpinning: obj.isSpinning,
                spinSpeed: obj.spinSpeed, conveyorSpeed: obj.conveyorSpeed
            };

            WebSocketManager.send({
                type: 'OBJECT_UPDATE',
                objectId: obj.id,
                data: updateData
            });
        };

        const throttledSendUpdate = throttle((obj) => {
            sendObjectUpdate(obj, 'moving');
        }, 40);

        // --- LISTENERS ---

        // 1. MOVIMIENTO
        WhiteboardController.canvas.on('object:moving', (e) => {
            WhiteboardPhysics.syncBodyPosition(e.target);
            if (!WhiteboardController.state.isRemoteUpdate && !WhiteboardController.state.isLoading) {
                throttledSendUpdate(e.target);
                WhiteboardController.triggerAutoSave(); // Autoguardado
            }
        });

        // 2. ROTACIÓN
        WhiteboardController.canvas.on('object:rotating', (e) => {
            WhiteboardPhysics.syncBodyRotation(e.target);
            if (!WhiteboardController.state.isRemoteUpdate && !WhiteboardController.state.isLoading) {
                throttledSendUpdate(e.target);
                WhiteboardController.triggerAutoSave();
            }
        });
        
        // 3. ESCALADO
        WhiteboardController.canvas.on('object:scaling', (e) => {
            WhiteboardPhysics.removeBody(e.target);
            WhiteboardPhysics.addBody(e.target);
            if (!WhiteboardController.state.isRemoteUpdate && !WhiteboardController.state.isLoading) {
                throttledSendUpdate(e.target);
                WhiteboardController.triggerAutoSave();
            }
        });

        // 4. MODIFICADO FINAL
        WhiteboardController.canvas.on('object:modified', (e) => {
            if (WhiteboardController.state.isRemoteUpdate || WhiteboardController.state.isLoading) return;
            const obj = e.target;
            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
            
            sendObjectUpdate(obj, 'modified');
            WhiteboardController.triggerAutoSave();
        });

        // 5. CREACIÓN
        WhiteboardController.canvas.on('object:added', (e) => {
            if (WhiteboardController.state.isRemoteUpdate || WhiteboardController.state.isLoading) return;
            const obj = e.target;
            if (obj.type === 'selection' || obj.type === 'activeSelection') return;

            if (!obj.id) obj.set('id', WhiteboardController.generateUUID());

            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
            WhiteboardPhysics.addBody(obj);

            const propertiesToInclude = ['id', 'customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree', 'selectable'];
            WebSocketManager.send({
                type: 'OBJECT_ADDED',
                data: obj.toJSON(propertiesToInclude)
            });
            WhiteboardController.triggerAutoSave();
        });
        
        // 6. ELIMINACIÓN
        WhiteboardController.canvas.on('object:removed', (e) => {
            if (WhiteboardController.state.isRemoteUpdate || WhiteboardController.state.isLoading) return;
            const obj = e.target;

            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
            WhiteboardPhysics.removeBody(obj);

            if (obj.id) {
                WebSocketManager.send({
                    type: 'OBJECT_REMOVED',
                    objectId: obj.id
                });
            }
            WhiteboardController.triggerAutoSave();
        });

        WhiteboardController.canvas.on('path:created', (e) => {
            if (WhiteboardController.state.isRemoteUpdate || WhiteboardController.state.isLoading) return;
            const path = e.path;
            if (!path.id) path.set('id', WhiteboardController.generateUUID());
            WhiteboardController.triggerAutoSave();
        });

        // Eventos UI...
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

        let lastTime = performance.now();
        let frameCount = 0;

        const loop = () => {
            const now = performance.now();
            frameCount++;
            const delta = now - lastTime;
            
            if (delta >= 1000) {
                const fps = Math.round((frameCount * 1000) / delta);
                WhiteboardUI.updateFPS(fps);
                frameCount = 0;
                lastTime = now;
            }

            const canvas = WhiteboardController.canvas;
            if (canvas) {
                const objects = canvas.getObjects();
                let needsRender = false;

                objects.forEach(obj => {
                    if (obj.customType === 'circle-cut' && obj.isSpinning && obj.spinSpeed !== 0) {
                        obj.angle += parseFloat(obj.spinSpeed);
                        obj.angle = obj.angle % 360;
                        obj.setCoords(); 
                        WhiteboardPhysics.syncBodyRotation(obj);
                        needsRender = true;
                    }
                    else if (obj.customType === 'conveyor' && obj.isSpinning && obj.conveyorSpeed !== 0) {
                        const speed = obj.conveyorSpeed || 0;
                        obj.strokeDashOffset -= speed; 
                        if (Math.abs(obj.strokeDashOffset) > 1000) obj.strokeDashOffset = 0;
                        
                        // FIX: Forzar redibujado visual para el conveyor
                        obj.dirty = true;
                        
                        needsRender = true;
                    }
                });

                if (needsRender) {
                    canvas.requestRenderAll();
                }

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
                id: obj.id,
                type: obj.type,
                customType: obj.customType || 'N/A',
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
                
                activeObj.fire('modified');

                WhiteboardPhysics.removeBody(activeObj);
                WhiteboardPhysics.addBody(activeObj);
            });
            
            ui.inpApertureSize.addEventListener('change', () => {
                WhiteboardController.saveHistory();
            });
        }

        if (ui.btnSpinToggle) {
            ui.btnSpinToggle.addEventListener('click', () => {
                const activeObj = canvas.getActiveObject();
                if (!activeObj || (activeObj.customType !== 'circle-cut' && activeObj.customType !== 'conveyor')) return;
                
                const currentState = (activeObj.isSpinning !== undefined) ? activeObj.isSpinning : (activeObj.customType === 'conveyor');
                const newState = !currentState;
                activeObj.set('isSpinning', newState);
                
                WhiteboardUI.updateToolbarValues(activeObj);
                activeObj.fire('modified');

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
                        activeObj.set('conveyorSpeed', val);
                        WhiteboardPhysics.removeBody(activeObj);
                        WhiteboardPhysics.addBody(activeObj);
                     }
                     activeObj.fire('modified');
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
    
    setColorToSelection: (color) => {
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return; 

        const applyColor = (obj) => {
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
            WhiteboardPhysics.removeBody(obj);
            WhiteboardPhysics.addBody(obj);
            obj.fire('modified');
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
            if (obj.customType === 'conveyor' || obj.customType === 'circle-cut') return;

            obj.set({
                fill: 'transparent',
                stroke: '#000000', 
                strokeWidth: 2     
            });
            WhiteboardPhysics.removeBody(obj);
            WhiteboardPhysics.addBody(obj);
            obj.fire('modified');
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

        if (prop === 'strokeWidth' || prop === 'stroke') {
             if (activeObj.customType === 'conveyor') return;
        }

        const applyProp = (obj) => {
            if (obj.customType === 'conveyor' && (prop === 'strokeWidth' || prop === 'stroke' || prop === 'rx' || prop === 'ry')) return;
            obj.set(prop, value);
            obj.fire('modified');
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
        activeObj.fire('modified');
        canvas.requestRenderAll();
        WhiteboardUI.updateToolbarValues(activeObj);
        WhiteboardController.saveHistory(); 
    },

    saveHistory: () => {
        if (WhiteboardController.state.historyLocked) return;
        if (WhiteboardController.state.historyIndex < WhiteboardController.state.history.length - 1) {
            WhiteboardController.state.history = WhiteboardController.state.history.slice(0, WhiteboardController.state.historyIndex + 1);
        }
        
        const propertiesToInclude = ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree', 'id'];
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
            activeObj.clone((cloned) => { WhiteboardController.state.clipboard = cloned; }, 
            ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree', 'id']);
        }
    },

    paste: () => {
        if (!WhiteboardController.state.clipboard) return;
        
        WhiteboardController.state.clipboard.clone((clonedObj) => {
            WhiteboardController.canvas.discardActiveObject();
            clonedObj.set({ left: clonedObj.left + 20, top: clonedObj.top + 20, evented: true });
            
            if (clonedObj.type === 'activeSelection') {
                clonedObj.canvas = WhiteboardController.canvas;
                clonedObj.forEachObject((obj) => { 
                    obj.set('id', WhiteboardController.generateUUID());
                    WhiteboardController.canvas.add(obj); 
                });
                clonedObj.setCoords();
            } else {
                clonedObj.set('id', WhiteboardController.generateUUID());
                WhiteboardController.canvas.add(clonedObj);
            }
            WhiteboardController.canvas.setActiveObject(clonedObj);
            WhiteboardController.canvas.requestRenderAll();
            WhiteboardController.saveHistory();
        }, ['customType', 'isSpinning', 'spinSpeed', 'conveyorSpeed', 'apertureDegree']);
    },

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
            if (!obj.customType) {
                obj.set('customType', type);
            }
            
            obj.set('id', WhiteboardController.generateUUID());

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