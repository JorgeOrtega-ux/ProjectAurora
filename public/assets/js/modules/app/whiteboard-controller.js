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
        lastPosY: 0,
        clipboard: null,
        history: [],
        historyIndex: -1,
        historyLocked: false,
        
        // Estado Física
        physicsRunning: false, 
        physicsGlobalEnabled: false
    },
    
    physics: {
        engine: null,
        world: null,
        runner: null,
        bodiesMap: new Map(), 
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
        
        btnUndo: null,
        btnRedo: null,
        btnPhysicsAll: null,
        btnPhysicsSelected: null,

        drawer: null,
        drawerTitle: null,
        btnCloseDrawer: null,
        
        toolbar: null,
        btnScaleUp: null,
        btnScaleDown: null,
        btnDelete: null,
        btnColors: null,
        btnBorderOptions: null,
        sizeDisplay: null,

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
        
        WhiteboardController.elements.btnUndo = document.getElementById('wb-btn-undo');
        WhiteboardController.elements.btnRedo = document.getElementById('wb-btn-redo');
        WhiteboardController.elements.btnPhysicsAll = document.getElementById('wb-btn-physics-all');
        WhiteboardController.elements.btnPhysicsSelected = document.getElementById('wb-btn-physics-selected');
        
        WhiteboardController.elements.drawer = document.getElementById('wb-drawer');
        WhiteboardController.elements.drawerTitle = document.getElementById('wb-drawer-title');
        WhiteboardController.elements.btnCloseDrawer = document.getElementById('wb-close-drawer');

        WhiteboardController.elements.toolbar = document.getElementById('wb-top-toolbar');
        WhiteboardController.elements.btnScaleUp = document.getElementById('btn-scale-up');
        WhiteboardController.elements.btnScaleDown = document.getElementById('btn-scale-down');
        WhiteboardController.elements.btnDelete = document.getElementById('btn-delete-selection');
        WhiteboardController.elements.btnColors = document.getElementById('btn-open-colors');
        WhiteboardController.elements.btnBorderOptions = document.getElementById('btn-border-options');
        WhiteboardController.elements.sizeDisplay = document.getElementById('wb-size-display');

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
        
        WhiteboardController.saveHistory();
        
        // Inicializar motor (pausado)
        WhiteboardController.initPhysicsEngine();

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

        // Listeners
        WhiteboardController.canvas.on('object:added', (e) => {
            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
            WhiteboardController.addBodyToWorld(e.target);
        });
        
        WhiteboardController.canvas.on('object:removed', (e) => {
            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
            WhiteboardController.removeBodyFromWorld(e.target);
        });

        WhiteboardController.canvas.on('object:modified', () => {
            if (!WhiteboardController.state.historyLocked) WhiteboardController.saveHistory();
        });

        WhiteboardController.canvas.on('object:moving', (e) => {
            WhiteboardController.syncBodyPosition(e.target);
        });
        
        WhiteboardController.canvas.on('object:rotating', (e) => {
            WhiteboardController.syncBodyRotation(e.target);
        });
        
        WhiteboardController.canvas.on('object:scaling', (e) => {
            WhiteboardController.removeBodyFromWorld(e.target);
            WhiteboardController.addBodyToWorld(e.target);
        });
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
                borderPopover, inpBorderWidth, inpBorderColor, inpBorderRadius,
                btnUndo, btnRedo, btnPhysicsAll, btnPhysicsSelected } = WhiteboardController.elements;
        const canvas = WhiteboardController.canvas;

        const updateToolbar = () => {
            const activeObj = canvas.getActiveObject();
            WhiteboardController.toggleToolbar(!!activeObj);
            
            if (activeObj) {
                WhiteboardController.updateToolbarValues();
                WhiteboardController.syncPopoverValues(activeObj);
            } else {
                WhiteboardController.toggleBorderPopover(false);
            }
        };

        canvas.on('selection:created', updateToolbar);
        canvas.on('selection:updated', updateToolbar);
        canvas.on('selection:cleared', updateToolbar);
        canvas.on('object:modified', updateToolbar);
        canvas.on('object:scaling', updateToolbar);

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

        if (btnUndo) btnUndo.addEventListener('click', () => WhiteboardController.undo());
        if (btnRedo) btnRedo.addEventListener('click', () => WhiteboardController.redo());
        
        if (btnPhysicsAll) {
            btnPhysicsAll.addEventListener('click', () => {
                WhiteboardController.togglePhysicsGlobal();
            });
        }
        
        if (btnPhysicsSelected) {
            btnPhysicsSelected.addEventListener('click', () => {
                WhiteboardController.activatePhysicsForSelection();
            });
        }

        if (btnColors) {
            btnColors.addEventListener('click', (e) => {
                e.stopPropagation();
                WhiteboardController.openDrawer('drawer-colors', 'Colores');
                WhiteboardController.toggleBorderPopover(false); 
            });
        }

        if (btnBorderOptions) {
            btnBorderOptions.addEventListener('click', (e) => {
                e.stopPropagation();
                const isActive = borderPopover.classList.contains('active');
                WhiteboardController.toggleBorderPopover(!isActive);
                if (!isActive) WhiteboardController.closeDrawer();
            });
        }

        if (inpBorderWidth) {
            inpBorderWidth.addEventListener('input', (e) => {
                const val = parseInt(e.target.value, 10);
                WhiteboardController.elements.valBorderWidth.innerText = val;
                WhiteboardController.updateSelectionProp('strokeWidth', val);
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
                WhiteboardController.updateSelectionProp('rx', val);
                WhiteboardController.updateSelectionProp('ry', val);
            });
        }

        document.addEventListener('click', (e) => {
            if (borderPopover && borderPopover.classList.contains('active')) {
                if (!borderPopover.contains(e.target) && !btnBorderOptions.contains(e.target)) {
                    WhiteboardController.toggleBorderPopover(false);
                }
            }
        });

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

    // --- LÓGICA DE FÍSICA ---

    initPhysicsEngine: () => {
        if (!window.Matter) { console.error("Matter.js no cargado"); return; }
        
        const Engine = Matter.Engine, World = Matter.World, Runner = Matter.Runner;
        const engine = Engine.create();
        const world = engine.world;
        
        WhiteboardController.physics.engine = engine;
        WhiteboardController.physics.world = world;
        
        const runner = Runner.create();
        WhiteboardController.physics.runner = runner;
        
        Runner.run(runner, engine);
        WhiteboardController.state.physicsRunning = true;

        const updateLoop = () => {
            if (!WhiteboardController.state.physicsRunning) return;
            
            WhiteboardController.physics.bodiesMap.forEach((body, obj) => {
                if (!body.isStatic) {
                    obj.left = body.position.x;
                    obj.top = body.position.y;
                    obj.angle = body.angle * (180 / Math.PI);
                    obj.setCoords(); 
                }
            });
            
            WhiteboardController.canvas.requestRenderAll();
            requestAnimationFrame(updateLoop);
        };
        requestAnimationFrame(updateLoop);
    },

    togglePhysicsGlobal: () => {
        const { btnPhysicsAll } = WhiteboardController.elements;
        const enable = !WhiteboardController.state.physicsGlobalEnabled;
        WhiteboardController.state.physicsGlobalEnabled = enable;

        if (enable) {
            btnPhysicsAll.classList.add('active-state');
            WhiteboardController.physics.bodiesMap.forEach(body => {
                Matter.Body.setStatic(body, false);
                Matter.Sleeping.set(body, false);
            });
        } else {
            btnPhysicsAll.classList.remove('active-state');
            WhiteboardController.physics.bodiesMap.forEach(body => {
                Matter.Body.setStatic(body, true);
            });
        }
    },

    activatePhysicsForSelection: () => {
        const activeObjects = WhiteboardController.canvas.getActiveObjects();
        if (!activeObjects.length) return;

        if (!WhiteboardController.state.physicsRunning) WhiteboardController.initPhysicsEngine();

        activeObjects.forEach(obj => {
            const body = WhiteboardController.physics.bodiesMap.get(obj);
            if (body) {
                Matter.Body.setStatic(body, false);
                Matter.Sleeping.set(body, false);
            }
        });
        
        WhiteboardController.canvas.discardActiveObject();
        WhiteboardController.canvas.requestRenderAll();
    },

    addBodyToWorld: (obj) => {
        if (obj.type === 'selection' || obj.type === 'activeSelection') return;
        
        const Bodies = Matter.Bodies, World = Matter.World;
        const world = WhiteboardController.physics.world;
        if (!world) return;

        let body = null;
        const x = obj.left;
        const y = obj.top;
        const angle = fabric.util.degreesToRadians(obj.angle);
        const isStatic = !WhiteboardController.state.physicsGlobalEnabled;

        const options = {
            friction: 0.5,
            restitution: 0.6,
            angle: angle,
            isStatic: isStatic
        };

        // --- LÓGICA AVANZADA DE FORMAS ---
        
        if (obj.type === 'polygon' || obj.type === 'triangle') {
             // Caso Hueco (Paredes) o Sólido (Convexo)
             if (obj.fill === 'transparent') {
                 // Crear paredes siguiendo los vértices
                 body = WhiteboardController.createHollowPolygon(obj, x, y, angle, options);
             } else {
                 // Crear cuerpo sólido convexo siguiendo los vértices
                 body = WhiteboardController.createSolidPolygon(obj, x, y, options);
             }
        }
        else if (obj.type === 'rect' && obj.fill === 'transparent') {
            body = WhiteboardController.createHollowRect(obj, x, y, angle, options);
        }
        else if (obj.type === 'rect' || obj.type === 'image') {
            const w = obj.getScaledWidth();
            const h = obj.getScaledHeight();
            body = Bodies.rectangle(x, y, w, h, options);
        } 
        else if (obj.type === 'circle') {
            const r = obj.getScaledWidth() / 2;
            body = Bodies.circle(x, y, r, options);
        } 
        else {
            // Fallback para paths complejos (flechas, dibujos) -> Caja rectangular
            const w = obj.getScaledWidth();
            const h = obj.getScaledHeight();
            body = Bodies.rectangle(x, y, w, h, options);
        }

        if (body) {
            World.add(world, body);
            WhiteboardController.physics.bodiesMap.set(obj, body);
        }
    },

    // --- GENERADORES DE CUERPOS FÍSICOS ---

    // Crea un polígono sólido exacto usando los vértices reales de Fabric
    createSolidPolygon: (obj, x, y, options) => {
        // Fabric almacena puntos relativos al centro. Necesitamos escalarlos.
        const points = obj.points.map(p => ({
            x: p.x * obj.scaleX,
            y: p.y * obj.scaleY
        }));
        
        // Matter.Bodies.fromVertices crea el cuerpo centrado en su propio centro de masa calculado.
        // x, y son las coordenadas donde queremos que aparezca en el mundo.
        return Matter.Bodies.fromVertices(x, y, [points], options);
    },

    // Crea un polígono hueco (paredes) recorriendo los vértices
    createHollowPolygon: (obj, x, y, angle, options) => {
        const parts = [];
        const points = obj.points; 
        const len = points.length;
        // Grosor de la pared
        const thickness = Math.max(obj.strokeWidth * obj.scaleX || 10, 10);
        
        // Calcular vértices reales en coordenadas relativas (con escala)
        const scaledPoints = points.map(p => ({
            x: p.x * obj.scaleX,
            y: p.y * obj.scaleY
        }));

        for (let i = 0; i < len; i++) {
            const p1 = scaledPoints[i];
            const p2 = scaledPoints[(i + 1) % len]; // Conectar último con primero

            // Datos del segmento
            const dx = p2.x - p1.x;
            const dy = p2.y - p1.y;
            const length = Math.sqrt(dx * dx + dy * dy);
            const segmentAngle = Math.atan2(dy, dx);
            
            // Centro del segmento relativo al centro del objeto
            const midX = (p1.x + p2.x) / 2;
            const midY = (p1.y + p2.y) / 2;

            // Crear rectángulo para este lado
            // Nota: x e y del objeto ya se pasan en 'create', las partes son relativas
            // Pero Matter.Body.create parts requiere coordenadas absolutas iniciales si no se agrupan bien.
            // Truco: Creamos el cuerpo en (0,0) relativo y luego Matter lo compone.
            
            // Sin embargo, para simplificar con rotation, crearemos las partes en su posición "mundial"
            // asumiendo rotación 0 del objeto padre, y luego el 'options.angle' rotará todo el grupo.
            
            // Coordenada absoluta de la pared asumiendo que el objeto está en (x,y) con rotación 0
            const wallX = x + midX;
            const wallY = y + midY;

            const wall = Matter.Bodies.rectangle(wallX, wallY, length, thickness, {
                angle: segmentAngle, // Ángulo local de la pared
                ...options
            });
            parts.push(wall);
        }

        return Matter.Body.create({
            parts: parts,
            ...options
        });
    },

    createHollowRect: (obj, x, y, angle, options) => {
        const w = obj.getScaledWidth();
        const h = obj.getScaledHeight();
        const t = Math.max(obj.strokeWidth * obj.scaleX || 10, 10); 

        const top = Matter.Bodies.rectangle(x, y - h/2, w, t, options);
        const bottom = Matter.Bodies.rectangle(x, y + h/2, w, t, options);
        const left = Matter.Bodies.rectangle(x - w/2, y, t, h, options);
        const right = Matter.Bodies.rectangle(x + w/2, y, t, h, options);

        return Matter.Body.create({
            parts: [top, bottom, left, right],
            ...options
        });
    },

    removeBodyFromWorld: (obj) => {
        const body = WhiteboardController.physics.bodiesMap.get(obj);
        if (body && WhiteboardController.physics.world) {
            Matter.World.remove(WhiteboardController.physics.world, body);
            WhiteboardController.physics.bodiesMap.delete(obj);
        }
    },

    syncBodyPosition: (obj) => {
        const body = WhiteboardController.physics.bodiesMap.get(obj);
        if (body) {
            Matter.Body.setPosition(body, { x: obj.left, y: obj.top });
            Matter.Body.setVelocity(body, { x: 0, y: 0 }); 
        }
    },

    syncBodyRotation: (obj) => {
        const body = WhiteboardController.physics.bodiesMap.get(obj);
        if (body) {
            Matter.Body.setAngle(body, fabric.util.degreesToRadians(obj.angle));
            Matter.Body.setAngularVelocity(body, 0);
        }
    },

    // --- UTILS ---

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
                WhiteboardController.rebuildPhysicsWorld();
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
                WhiteboardController.rebuildPhysicsWorld();
            });
        }
    },

    rebuildPhysicsWorld: () => {
        if (WhiteboardController.physics.world) {
            Matter.World.clear(WhiteboardController.physics.world);
            Matter.Engine.clear(WhiteboardController.physics.engine);
            WhiteboardController.physics.bodiesMap.clear();
        }
        WhiteboardController.initPhysicsEngine();
        const objects = WhiteboardController.canvas.getObjects();
        objects.forEach(obj => WhiteboardController.addBodyToWorld(obj));
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

    bindSidebarEvents: () => {
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

    setColorToSelection: (color) => {
        const canvas = WhiteboardController.canvas;
        if (!canvas) return;
        const activeObj = canvas.getActiveObject();
        if (!activeObj) return; 

        const applyColor = (obj) => {
            // Si elige transparente, se vuelve hueco
            if (color === 'transparent') {
                obj.set('fill', 'transparent');
                // Forzar borde para que no desaparezca visualmente
                if (!obj.stroke || obj.stroke === 'transparent' || obj.strokeWidth === 0) {
                    obj.set({ stroke: '#000000', strokeWidth: 2 });
                }
            } else {
                if (obj.type === 'path' && (!obj.fill || obj.fill === 'transparent' || obj.fill === '')) {
                     obj.set('stroke', color);
                } else {
                    obj.set('fill', color);
                }
            }
            // Importante: Si cambia el "relleno", cambia la física. Reconstruir cuerpo.
            WhiteboardController.removeBodyFromWorld(obj);
            WhiteboardController.addBodyToWorld(obj);
        };

        if (activeObj.type === 'activeSelection') {
            activeObj.getObjects().forEach(obj => applyColor(obj));
        } else {
            applyColor(activeObj);
        }
        
        WhiteboardController.syncPopoverValues(activeObj);
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

    toggleToolbar: (show) => {
        const { toolbar } = WhiteboardController.elements;
        if (!toolbar) return;
        if (show) toolbar.classList.add('active');
        else {
            toolbar.classList.remove('active');
            WhiteboardController.toggleBorderPopover(false); 
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

    syncPopoverValues: (obj) => {
        const { inpBorderWidth, valBorderWidth, inpBorderColor, inpBorderRadius, valBorderRadius, rowBorderRadius } = WhiteboardController.elements;
        
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
        WhiteboardController.saveHistory(); 
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