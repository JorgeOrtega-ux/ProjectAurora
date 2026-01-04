/**
 * public/assets/js/modules/app/whiteboard-physics.js
 * Motor de física y cálculos geométricos (Matter.js)
 */

export const WhiteboardPhysics = {
    engine: null,
    world: null,
    runner: null,
    bodiesMap: new Map(),
    constraintsMap: new Map(),
    
    // Estado interno de la física
    running: false,
    globalEnabled: false,
    canvasRef: null, 

    init: (fabricCanvas) => {
        if (!window.Matter) { console.error("Matter.js no cargado"); return; }
        
        console.log("WhiteboardPhysics: Iniciando motor...");
        WhiteboardPhysics.canvasRef = fabricCanvas;

        const Engine = Matter.Engine, World = Matter.World, Runner = Matter.Runner, Events = Matter.Events;
        const engine = Engine.create();
        const world = engine.world;
        
        WhiteboardPhysics.engine = engine;
        WhiteboardPhysics.world = world;
        
        const runner = Runner.create();
        WhiteboardPhysics.runner = runner;

        // --- LÓGICA DE CINTA TRANSPORTADORA (CONVEYOR) ---
        Events.on(engine, 'beforeUpdate', function(event) {
            
            const bodies = Matter.Composite.allBodies(world);
            const conveyors = bodies.filter(b => b.plugin && b.plugin.isConveyor);

            if (conveyors.length === 0) return;

            conveyors.forEach(conveyor => {
                const speed = conveyor.plugin.speed || 0;
                if (speed === 0) return;

                const collisions = Matter.Query.collides(conveyor, bodies);

                collisions.forEach(collision => {
                    const otherBody = collision.bodyA === conveyor ? collision.bodyB : collision.bodyA;
                    
                    if (otherBody.isStatic) return; 

                    const angle = conveyor.angle;
                    const dirX = Math.cos(angle);
                    const dirY = Math.sin(angle);

                    Matter.Body.setVelocity(otherBody, {
                        x: otherBody.velocity.x * 0.9 + (dirX * speed) * 0.1,
                        y: otherBody.velocity.y * 0.9 + (dirY * speed) * 0.1
                    });

                    Matter.Body.translate(otherBody, {
                        x: dirX * speed * 0.05,
                        y: dirY * speed * 0.05
                    });
                });
            });
        });
        
        Runner.run(runner, engine);
        WhiteboardPhysics.running = true;

        // --- LOOP PRINCIPAL (Sincronización Física -> Visual) ---
        const updateLoop = () => {
            if (!WhiteboardPhysics.running) return;
            
            WhiteboardPhysics.bodiesMap.forEach((body, obj) => {
                if (!body.isStatic) {
                    // Obtener posición física
                    let posX = body.position.x;
                    let posY = body.position.y;
                    const angle = body.angle;

                    // Si el cuerpo tiene un offset visual (ej. Triángulo), aplicarlo inversamente
                    if (body.plugin.fabricOffset) {
                        const offset = body.plugin.fabricOffset;
                        // Rotar el vector de offset según el ángulo del cuerpo
                        const cos = Math.cos(angle);
                        const sin = Math.sin(angle);
                        
                        // Rotación de vector (x, y)
                        const rotX = offset.x * cos - offset.y * sin;
                        const rotY = offset.x * sin + offset.y * cos;

                        // Restar el offset para encontrar el centro visual de Fabric
                        posX -= rotX;
                        posY -= rotY;
                    }

                    obj.left = posX;
                    obj.top = posY;
                    obj.angle = angle * (180 / Math.PI);
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
                // Si es conveyor, mantener estático aunque la física esté global
                const shouldBeStatic = body.plugin && (body.plugin.isConveyor || body.plugin.forceStatic);
                
                if (!shouldBeStatic) {
                    Matter.Body.setStatic(body, false);
                    Matter.Sleeping.set(body, false);
                }
            });
        } else {
            WhiteboardPhysics.bodiesMap.forEach(body => {
                Matter.Body.setStatic(body, true);
            });
        }
    },

   activateForSelection: (activeObjects) => {
        if (!activeObjects || !activeObjects.length) return;

        activeObjects.forEach(obj => {
            const body = WhiteboardPhysics.bodiesMap.get(obj);
            if (body) {
                if (obj.customType === 'conveyor' || obj.customType === 'circle-cut' || obj.isStatic) return;

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
        
        const isStatic = obj.isStatic !== undefined ? obj.isStatic : !WhiteboardPhysics.globalEnabled;

        const options = {
            friction: 0.5,
            restitution: 0.6,
            angle: angle,
            isStatic: isStatic,
            plugin: {
                fabricOffset: null // Almacenará el desfase del centro de masa
            } 
        };

        // --- LÓGICA DE FORMAS ---

        if (obj.customType === 'conveyor') {
            options.friction = 1; 
            options.plugin.isConveyor = true;
            
            const isActive = (obj.isSpinning !== undefined) ? obj.isSpinning : true;
            const speedVal = obj.conveyorSpeed || 2;
            options.plugin.speed = isActive ? speedVal : 0;
            options.plugin.forceStatic = true; 
            options.isStatic = true; 
            
            const w = obj.getScaledWidth();
            const h = obj.getScaledHeight();
            body = Bodies.rectangle(x, y, w, h, options);
        }
        else if (obj.customType === 'circle-cut') {
            options.isStatic = true;          
            options.plugin.forceStatic = true; 
            body = WhiteboardPhysics.createArcBody(obj, x, y, angle, options);
        }
        else if (obj.type === 'line') {
            body = WhiteboardPhysics.createLineBody(obj, options);
        }
        else if (obj.type === 'polygon' || obj.type === 'triangle') {
             
             let polyPoints = obj.points;
             
             // CORRECCIÓN TRIÁNGULO: Definición manual y cálculo de offset
             if (obj.type === 'triangle') {
                 const w = obj.width;
                 const h = obj.height;
                 
                 // Puntos relativos al centro visual (0,0)
                 polyPoints = [
                     { x: 0, y: -h / 2 },
                     { x: -w / 2, y: h / 2 },
                     { x: w / 2, y: h / 2 }
                 ];

                 // CÁLCULO DEL OFFSET (Centro de Masa)
                 // El centroide de un triángulo está a 1/3 de la altura desde la base, 
                 // o a h/6 del centro geométrico hacia abajo.
                 const scaleY = obj.scaleY || 1;
                 const scaledHeight = h * scaleY;
                 const centroidOffset = scaledHeight / 6;

                 // Guardamos este offset para aplicarlo en sincronización
                 options.plugin.fabricOffset = { x: 0, y: centroidOffset };
             }

             if (obj.fill === 'transparent') {
                 body = WhiteboardPhysics.createHollowPolygon(obj, x, y, angle, options, polyPoints);
             } else {
                 body = WhiteboardPhysics.createSolidPolygon(obj, x, y, options, polyPoints);
             }

             // Si es un triángulo, el cuerpo creado por Matter estará centrado en su centro de masa.
             // Pero nosotros le pasamos 'x, y' (centro visual).
             // Por lo tanto, el cuerpo físico nace desplazado hacia arriba visualmente (el centro de masa se pone en el centro visual).
             // Necesitamos mover el cuerpo físico a su posición real (centro de masa real).
             if (options.plugin.fabricOffset) {
                 // Rotar offset para posicionamiento inicial
                 const cos = Math.cos(angle);
                 const sin = Math.sin(angle);
                 const offY = options.plugin.fabricOffset.y;
                 
                 // Desplazamiento local (0, offY) rotado
                 const dx = -offY * sin; 
                 const dy = offY * cos;

                 Matter.Body.setPosition(body, { 
                     x: body.position.x + dx, 
                     y: body.position.y + dy 
                 });
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
            const w = obj.getScaledWidth();
            const h = obj.getScaledHeight();
            body = Bodies.rectangle(x, y, w, h, options);
        }

        if (body) {
            if (obj.isStatic) {
                body.plugin.forceStatic = true;
            }

            World.add(world, body);
            WhiteboardPhysics.bodiesMap.set(obj, body);
        }
    },

    removeBody: (obj) => {
        const world = WhiteboardPhysics.world;
        if (!world) return;

        if (WhiteboardPhysics.constraintsMap.has(obj)) {
            Matter.World.remove(world, WhiteboardPhysics.constraintsMap.get(obj));
            WhiteboardPhysics.constraintsMap.delete(obj);
        }

        const body = WhiteboardPhysics.bodiesMap.get(obj);
        if (body) {
            Matter.World.remove(world, body);
            WhiteboardPhysics.bodiesMap.delete(obj);
        }
    },

    // --- Sincronización Visual -> Física (Al arrastrar) ---
    syncBodyPosition: (obj) => {
        const body = WhiteboardPhysics.bodiesMap.get(obj);
        if (body) {
            let targetX = obj.left;
            let targetY = obj.top;

            // Si hay offset (Triángulos), ajustar la posición destino del cuerpo
            if (body.plugin.fabricOffset) {
                const angle = body.angle;
                const cos = Math.cos(angle);
                const sin = Math.sin(angle);
                const offset = body.plugin.fabricOffset;

                // Rotar el offset para saber dónde debe estar el centro de masa
                const rotX = offset.x * cos - offset.y * sin;
                const rotY = offset.x * sin + offset.y * cos;

                targetX += rotX;
                targetY += rotY;
            }

            Matter.Body.setPosition(body, { x: targetX, y: targetY });
            Matter.Body.setVelocity(body, { x: 0, y: 0 }); 
        }
    },

    syncBodyRotation: (obj) => {
        const body = WhiteboardPhysics.bodiesMap.get(obj);
        if (body) {
            const rads = fabric.util.degreesToRadians(obj.angle);
            Matter.Body.setAngle(body, rads);
            Matter.Body.setAngularVelocity(body, 0);

            // Al rotar, la posición del centro de masa cambia respecto al centro visual
            // Necesitamos re-sincronizar la posición también
            WhiteboardPhysics.syncBodyPosition(obj);
        }
    },

    rebuildWorld: (fabricObjects) => {
        if (WhiteboardPhysics.world) {
            Matter.World.clear(WhiteboardPhysics.world);
            Matter.Engine.clear(WhiteboardPhysics.engine);
            WhiteboardPhysics.bodiesMap.clear();
            WhiteboardPhysics.constraintsMap.clear();
        }
        WhiteboardPhysics.init(WhiteboardPhysics.canvasRef);
        
        if(fabricObjects) {
            fabricObjects.forEach(obj => WhiteboardPhysics.addBody(obj));
        }
    },

    // --- GENERADORES DE CUERPOS ---

    createSolidPolygon: (obj, x, y, options, pointsOverride) => {
        const sourcePoints = pointsOverride || obj.points;
        const points = sourcePoints.map(p => ({
            x: p.x * obj.scaleX,
            y: p.y * obj.scaleY
        }));
        // Matter calcula el centroide automáticamente y centra el cuerpo allí
        return Matter.Bodies.fromVertices(x, y, [points], options);
    },

    createLineBody: (obj, options) => {
        const p1 = { x: obj.x1, y: obj.y1 };
        const p2 = { x: obj.x2, y: obj.y2 };
        const center = obj.getCenterPoint(); 
        
        const dx = p2.x - p1.x;
        const dy = p2.y - p1.y;
        const length = Math.sqrt(dx*dx + dy*dy);
        const strokeWidth = obj.strokeWidth || 4;
        
        const baseAngle = Math.atan2(dy, dx);
        const totalAngle = baseAngle + fabric.util.degreesToRadians(obj.angle);

        return Matter.Bodies.rectangle(center.x, center.y, length, strokeWidth, {
            ...options,
            angle: totalAngle
        });
    },

    createArcBody: (obj, x, y, rotation, options) => {
        const parts = [];
        const r = obj.radius * obj.scaleX;
        const start = fabric.util.degreesToRadians(obj.startAngle);
        const end = fabric.util.degreesToRadians(obj.endAngle);
        const stroke = (obj.strokeWidth * obj.scaleX) || 10;
        const effectiveR = r; 
        
        let totalAngle = end - start;
        if (totalAngle < 0) totalAngle += Math.PI * 2;
        
        const density = Math.max(10, r / 5); 
        const segments = Math.max(10, Math.floor(totalAngle * (density / Math.PI))); 
        const step = totalAngle / segments;

        for (let i = 0; i < segments; i++) {
            const a1 = start + i * step;
            const a2 = start + (i + 1) * step;
            const midAngle = (a1 + a2) / 2;

            const cx = effectiveR * Math.cos(midAngle);
            const cy = effectiveR * Math.sin(midAngle);
            const arcLen = (effectiveR * step) + 2; 

            const cosRot = Math.cos(rotation);
            const sinRot = Math.sin(rotation);
            const rx = cx * cosRot - cy * sinRot;
            const ry = cx * sinRot + cy * cosRot;

            const partX = x + rx;
            const partY = y + ry;
            const partRotation = midAngle + rotation + (Math.PI / 2); 

            const part = Matter.Bodies.rectangle(partX, partY, arcLen, stroke, {
                angle: partRotation,
                ...options
            });
            parts.push(part);
        }

        return Matter.Body.create({
            parts: parts,
            ...options
        });
    },

    createHollowPolygon: (obj, x, y, angle, options, pointsOverride) => {
        const parts = [];
        const points = pointsOverride || obj.points; 
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

        // Corrección para polígonos huecos: El centro de masa puede cambiar ligeramente 
        // dependiendo de la distribución, pero usualmente para regulares coincide con el centro.
        // Si hay problemas con huecos irregulares, se necesitaría lógica similar al triángulo.
        
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