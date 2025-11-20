// public/assets/js/drag-controller.js
import { closeAllModules, isAppAnimating } from './main-controller.js';

export function initDragController() {
    // Usamos el módulo de opciones
    const moduleSelector = '[data-module="moduleOptions"]';
    const menuContentSelector = '.menu-content';
    
    const module = document.querySelector(moduleSelector);
    if (!module) return; // Si no existe, salimos

    const menuContent = module.querySelector(menuContentSelector);
    const dragHandle = module.querySelector('.drag-handle');

    // Si no hay menú o handle, no inicializamos drag
    if (!menuContent || !dragHandle) return;

    let initialY, startY, currentY, isDragging = false, animationFrameId;

    // Listeners para Mouse y Touch
    dragHandle.addEventListener('mousedown', startDrag);
    dragHandle.addEventListener('touchstart', startDrag, { passive: false });

    function startDrag(e) {
        // Solo permitir en móvil y si no está animando
        if (window.innerWidth > 468 || isAppAnimating()) return;
        
        // Evitar scroll nativo del navegador
        if (e.cancelable) e.preventDefault();
        
        isDragging = true;
        
        // Detectar coordenada Y inicial (Mouse vs Touch)
        if (e.type === 'touchstart') {
            initialY = e.touches[0].pageY;
        } else {
            initialY = e.pageY;
        }

        cancelAnimationFrame(animationFrameId);

        // Obtener la transformación actual para empezar desde ahí
        const currentTransform = window.getComputedStyle(menuContent).transform;
        
        // Parsear matriz de transformación (matrix(a, b, c, d, tx, ty))
        if (currentTransform === 'none') {
            startY = 0;
        } else {
            try {
                const matrix = new DOMMatrix(currentTransform);
                startY = matrix.m42; // m42 es la traducción en Y
            } catch (err) {
                startY = 0;
            }
        }

        // Quitar transición CSS para movimiento instantáneo
        menuContent.style.transition = 'none';

        // Añadir listeners globales para el movimiento
        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('mouseup', endDrag);
        document.addEventListener('touchend', endDrag);
    }

    function drag(e) {
        if (!isDragging) return;
        if (e.cancelable) e.preventDefault();

        // Obtener Y actual
        if (e.type === 'touchmove') {
            currentY = e.touches[0].pageY;
        } else {
            currentY = e.pageY;
        }
        
        const movedY = currentY - initialY;
        let newTransformY = startY + movedY;

        animationFrameId = requestAnimationFrame(() => {
            // Resistencia elástica si el usuario intenta subir el menú (valores negativos)
            if (newTransformY < 0) {
                newTransformY = newTransformY / 4;
            }
            
            menuContent.style.transform = `translateY(${newTransformY}px)`;
        });
    }

    function endDrag() {
        if (!isDragging) return;
        isDragging = false;
        cancelAnimationFrame(animationFrameId);

        // Limpiar listeners
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('touchmove', drag);
        document.removeEventListener('mouseup', endDrag);
        document.removeEventListener('touchend', endDrag);

        const menuHeight = menuContent.offsetHeight;
        // Si se arrastra más del 25% de la altura, cerrar
        const dragThreshold = menuHeight * 0.25; 
        
        // Obtener posición final real
        const finalTransform = window.getComputedStyle(menuContent).transform;
        let finalY = 0;
        try {
            const matrix = new DOMMatrix(finalTransform);
            finalY = matrix.m42;
        } catch (err) {}

        // Restaurar transición para animación suave
        
        // CASO 1: Fue un tap o movimiento muy pequeño -> Regresar a 0
        if (Math.abs(finalY - startY) < 5 && finalY < dragThreshold) {
            menuContent.style.transition = 'transform 0.3s ease-out';
            menuContent.style.transform = 'translateY(0)';
            
            // Limpiar estilo inline después de la transición
            setTimeout(() => { 
                menuContent.style.removeProperty('transition');
                menuContent.style.removeProperty('transform');
            }, 300);
            return;
        }

        // CASO 2: Arrastre significativo
        menuContent.style.transition = 'transform 0.2s ease-out';

        if (finalY > dragThreshold) {
            // Cerrar (Bajar)
            menuContent.style.transform = 'translateY(100%)';

            menuContent.addEventListener('transitionend', () => {
                // Llamar a la lógica principal para cerrar el estado
                closeAllModules(); 
                // Limpiar estilos
                menuContent.removeAttribute('style');
            }, { once: true });
        } else {
            // Cancelar (Rebotar arriba)
            menuContent.style.transform = 'translateY(0)';
            
            menuContent.addEventListener('transitionend', () => {
                menuContent.removeAttribute('style');
            }, { once: true });
        }
    }
}