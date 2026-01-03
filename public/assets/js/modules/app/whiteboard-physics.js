/**
 * public/assets/js/modules/app/whiteboard-physics.js
 * Motor de física y cálculos geométricos (Matter.js)
 */

export const WhiteboardPhysics = {
    engine: null,
    world: null,
    runner: null,
    bodiesMap: new Map(),
    constraintsMap: new Map(), // Nuevo: Mapa para guardar restricciones (constraints)
    
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
        
        const Bodies = Matter.Bodies, World = Matter.World, Constraint = Matter.Constraint;
        const world = WhiteboardPhysics.world;
        if (!world) return;

        let body = null;
        const x = obj.left;
        const y = obj.top;
        const angle = fabric.util.degreesToRadians(obj.angle);
        // Respetar flag isStatic si viene del objeto (ej. base del sube y baja)
        const isStatic = obj.isStatic !== undefined ? obj.isStatic : !WhiteboardPhysics.globalEnabled;

        const options = {
            friction: 0.5,
            restitution: 0.6,
            angle: angle,
            isStatic: isStatic
        };

        // --- LÓGICA DE FORMAS ---

        // CASO ESPECIAL: Círculo Hueco (Arc)
        if (obj.customType === 'circle-cut') {
            body = WhiteboardPhysics.createArcBody(obj, x, y, angle, options);
        }
        // CASO: Línea
        else if (obj.type === 'line') {
            body = WhiteboardPhysics.createLineBody(obj, options);
        }
        else if (obj.type === 'polygon' || obj.type === 'triangle') {
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

            // --- LÓGICA ESPECIAL: SUBE Y BAJA (Beam) ---
            // Si es la barra del sube y baja, agregamos un Constraint (pivote) al centro
            if (obj.customType === 'seesaw-beam') {
                const constraint = Constraint.create({
                    bodyA: body,
                    pointB: { x: x, y: y }, // Clavar al punto del mundo donde fue creado
                    stiffness: 1,
                    length: 0
                });
                World.add(world, constraint);
                WhiteboardPhysics.constraintsMap.set(obj, constraint);
            }
        }
    },

    removeBody: (obj) => {
        const world = WhiteboardPhysics.world;
        if (!world) return;

        // Eliminar Constraints asociados primero
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
            WhiteboardPhysics.constraintsMap.clear();
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

    createLineBody: (obj, options) => {
        // Calcular centro, longitud y ángulo de la línea
        const p1 = { x: obj.x1, y: obj.y1 };
        const p2 = { x: obj.x2, y: obj.y2 };
        
        // Fabric ubica la línea basándose en left/top, necesitamos el punto central absoluto
        const center = obj.getCenterPoint(); 
        
        const dx = p2.x - p1.x;
        const dy = p2.y - p1.y;
        const length = Math.sqrt(dx*dx + dy*dy);
        const strokeWidth = obj.strokeWidth || 4;
        
        // El ángulo de la línea se deriva de sus puntos más la rotación del objeto
        // Simplificación: creamos un rectángulo en el centro con el ángulo correcto
        // Nota: Fabric maneja angle separado de x1/y1 coords en transformaciones
        
        // Calculamos ángulo base de la línea + rotación del objeto
        const baseAngle = Math.atan2(dy, dx);
        const totalAngle = baseAngle + fabric.util.degreesToRadians(obj.angle);

        return Matter.Bodies.rectangle(center.x, center.y, length, strokeWidth, {
            ...options,
            angle: totalAngle
        });
    },

    // Generar cuerpo para el Anillo Abierto
    createArcBody: (obj, x, y, rotation, options) => {
        const parts = [];
        
        // Propiedades de Fabric
        const r = obj.radius * obj.scaleX;
        
        // Convertir grados de Fabric a Radianes para Física
        const start = fabric.util.degreesToRadians(obj.startAngle);
        const end = fabric.util.degreesToRadians(obj.endAngle);
        
        const stroke = (obj.strokeWidth * obj.scaleX) || 10;
        // Ajuste: El radio físico debe ser el centro del trazo
        // Fabric dibuja el stroke centrado en el borde del radio definido.
        const effectiveR = r; 
        
        let totalAngle = end - start;
        if (totalAngle < 0) totalAngle += Math.PI * 2;
        
        // --- MEJORA DE RESOLUCIÓN ---
        // Aumentamos la densidad de segmentos para suavizar la curva física.
        // Factor base 10 (antes 4) y consideramos el tamaño del radio para círculos grandes.
        const density = Math.max(10, r / 5); 
        const segments = Math.max(10, Math.floor(totalAngle * (density / Math.PI))); 

        const step = totalAngle / segments;

        for (let i = 0; i < segments; i++) {
            const a1 = start + i * step;
            const a2 = start + (i + 1) * step;
            const midAngle = (a1 + a2) / 2;

            const cx = effectiveR * Math.cos(midAngle);
            const cy = effectiveR * Math.sin(midAngle);

            // Longitud de la cuerda (ligeramente aumentada para solapamiento y evitar grietas)
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