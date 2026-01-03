/**
 * public/assets/js/modules/app/whiteboard-physics.js
 * Motor de física y cálculos geométricos (Matter.js)
 */

export const WhiteboardPhysics = {
    engine: null,
    world: null,
    runner: null,
    bodiesMap: new Map(),
    
    // Estado interno de la física
    running: false,
    globalEnabled: false,
    canvasRef: null, // Referencia al canvas de Fabric para renderizar

    init: (fabricCanvas) => {
        if (!window.Matter) { console.error("Matter.js no cargado"); return; }
        
        console.log("WhiteboardPhysics: Iniciando motor...");
        WhiteboardPhysics.canvasRef = fabricCanvas;

        const Engine = Matter.Engine, World = Matter.World, Runner = Matter.Runner;
        const engine = Engine.create();
        const world = engine.world;
        
        WhiteboardPhysics.engine = engine;
        WhiteboardPhysics.world = world;
        
        const runner = Runner.create();
        WhiteboardPhysics.runner = runner;
        
        Runner.run(runner, engine);
        WhiteboardPhysics.running = true;

        const updateLoop = () => {
            if (!WhiteboardPhysics.running) return;
            
            WhiteboardPhysics.bodiesMap.forEach((body, obj) => {
                if (!body.isStatic) {
                    obj.left = body.position.x;
                    obj.top = body.position.y;
                    obj.angle = body.angle * (180 / Math.PI);
                    obj.setCoords(); 
                }
            });
            
            if (WhiteboardPhysics.canvasRef) {
                WhiteboardPhysics.canvasRef.requestRenderAll();
            }
            requestAnimationFrame(updateLoop);
        };
        requestAnimationFrame(updateLoop);
    },

    toggleGlobal: (enable) => {
        WhiteboardPhysics.globalEnabled = enable;
        
        if (enable) {
            WhiteboardPhysics.bodiesMap.forEach(body => {
                Matter.Body.setStatic(body, false);
                Matter.Sleeping.set(body, false);
            });
        } else {
            WhiteboardPhysics.bodiesMap.forEach(body => {
                Matter.Body.setStatic(body, true);
            });
        }
    },

    activateForSelection: (activeObjects) => {
        if (!activeObjects || !activeObjects.length) return;

        // Si el motor no estaba corriendo (caso raro), reiniciarlo es responsabilidad del Controller,
        // pero aquí aseguramos que los cuerpos despierten.
        activeObjects.forEach(obj => {
            const body = WhiteboardPhysics.bodiesMap.get(obj);
            if (body) {
                Matter.Body.setStatic(body, false);
                Matter.Sleeping.set(body, false);
            }
        });
    },

    addBody: (obj) => {
        if (obj.type === 'selection' || obj.type === 'activeSelection') return;
        
        const Bodies = Matter.Bodies, World = Matter.World;
        const world = WhiteboardPhysics.world;
        if (!world) return;

        let body = null;
        const x = obj.left;
        const y = obj.top;
        const angle = fabric.util.degreesToRadians(obj.angle);
        const isStatic = !WhiteboardPhysics.globalEnabled;

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
                 body = WhiteboardPhysics.createHollowPolygon(obj, x, y, angle, options);
             } else {
                 body = WhiteboardPhysics.createSolidPolygon(obj, x, y, options);
             }
        }
        else if (obj.type === 'rect' && obj.fill === 'transparent') {
            body = WhiteboardPhysics.createHollowRect(obj, x, y, angle, options);
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
            // Fallback para paths complejos
            const w = obj.getScaledWidth();
            const h = obj.getScaledHeight();
            body = Bodies.rectangle(x, y, w, h, options);
        }

        if (body) {
            World.add(world, body);
            WhiteboardPhysics.bodiesMap.set(obj, body);
        }
    },

    removeBody: (obj) => {
        const body = WhiteboardPhysics.bodiesMap.get(obj);
        if (body && WhiteboardPhysics.world) {
            Matter.World.remove(WhiteboardPhysics.world, body);
            WhiteboardPhysics.bodiesMap.delete(obj);
        }
    },

    syncBodyPosition: (obj) => {
        const body = WhiteboardPhysics.bodiesMap.get(obj);
        if (body) {
            Matter.Body.setPosition(body, { x: obj.left, y: obj.top });
            Matter.Body.setVelocity(body, { x: 0, y: 0 }); 
        }
    },

    syncBodyRotation: (obj) => {
        const body = WhiteboardPhysics.bodiesMap.get(obj);
        if (body) {
            Matter.Body.setAngle(body, fabric.util.degreesToRadians(obj.angle));
            Matter.Body.setAngularVelocity(body, 0);
        }
    },

    rebuildWorld: (fabricObjects) => {
        if (WhiteboardPhysics.world) {
            Matter.World.clear(WhiteboardPhysics.world);
            Matter.Engine.clear(WhiteboardPhysics.engine);
            WhiteboardPhysics.bodiesMap.clear();
        }
        // Reinicializar motor limpio
        WhiteboardPhysics.init(WhiteboardPhysics.canvasRef);
        
        // Re-agregar todos los objetos
        if(fabricObjects) {
            fabricObjects.forEach(obj => WhiteboardPhysics.addBody(obj));
        }
    },

    // --- GENERADORES DE CUERPOS ---

    createSolidPolygon: (obj, x, y, options) => {
        const points = obj.points.map(p => ({
            x: p.x * obj.scaleX,
            y: p.y * obj.scaleY
        }));
        return Matter.Bodies.fromVertices(x, y, [points], options);
    },

    createHollowPolygon: (obj, x, y, angle, options) => {
        const parts = [];
        const points = obj.points; 
        const len = points.length;
        const thickness = Math.max(obj.strokeWidth * obj.scaleX || 10, 10);
        
        const scaledPoints = points.map(p => ({
            x: p.x * obj.scaleX,
            y: p.y * obj.scaleY
        }));

        for (let i = 0; i < len; i++) {
            const p1 = scaledPoints[i];
            const p2 = scaledPoints[(i + 1) % len];

            const dx = p2.x - p1.x;
            const dy = p2.y - p1.y;
            const length = Math.sqrt(dx * dx + dy * dy);
            const segmentAngle = Math.atan2(dy, dx);
            
            const midX = (p1.x + p2.x) / 2;
            const midY = (p1.y + p2.y) / 2;

            const wallX = x + midX;
            const wallY = y + midY;

            const wall = Matter.Bodies.rectangle(wallX, wallY, length, thickness, {
                angle: segmentAngle,
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
    }
};