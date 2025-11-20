// public/assets/js/drag-controller.js
import { closeAllModules, isAppAnimating } from './main-controller.js';

export function initDragController() {
    const moduleSelector = '[data-module="moduleOptions"]';
    const menuContentSelector = '.menu-content';
    const dragZoneSelector = '.pill-container'; // Zona de agarre

    const module = document.querySelector(moduleSelector);
    if (!module) return;

    const menuContent = module.querySelector(menuContentSelector);
    const dragZone = module.querySelector(dragZoneSelector);

    if (!menuContent || !dragZone) return;

    let initialY, startY, currentY, isDragging = false, animationFrameId;

    dragZone.addEventListener('mousedown', startDrag);
    dragZone.addEventListener('touchstart', startDrag, { passive: false });

    function startDrag(e) {
        if (window.innerWidth > 468 || isAppAnimating()) return;
        
        isDragging = true;
        
        if (e.type === 'touchstart') {
            initialY = e.touches[0].pageY;
        } else {
            initialY = e.pageY;
        }

        const currentTransform = window.getComputedStyle(menuContent).transform;
        
        if (currentTransform === 'none') {
            startY = 0;
        } else {
            try {
                const matrix = new DOMMatrix(currentTransform);
                startY = matrix.m42; 
            } catch (err) {
                startY = 0;
            }
        }

        menuContent.style.transition = 'none';

        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('mouseup', endDrag);
        document.addEventListener('touchend', endDrag);
    }

    function drag(e) {
        if (!isDragging) return;
        if (e.cancelable) e.preventDefault(); 

        if (e.type === 'touchmove') {
            currentY = e.touches[0].pageY;
        } else {
            currentY = e.pageY;
        }
        
        const movedY = currentY - initialY;
        let newTransformY = startY + movedY;

        if (newTransformY < 0) {
            newTransformY = newTransformY / 4;
        }

        if (animationFrameId) cancelAnimationFrame(animationFrameId);

        animationFrameId = requestAnimationFrame(() => {
            menuContent.style.transform = `translateY(${newTransformY}px)`;
        });
    }

    function endDrag() {
        if (!isDragging) return;
        isDragging = false;
        if (animationFrameId) cancelAnimationFrame(animationFrameId);

        document.removeEventListener('mousemove', drag);
        document.removeEventListener('touchmove', drag);
        document.removeEventListener('mouseup', endDrag);
        document.removeEventListener('touchend', endDrag);

        const menuHeight = menuContent.offsetHeight;
        const dragThreshold = menuHeight * 0.25;
        
        const finalTransform = window.getComputedStyle(menuContent).transform;
        let finalY = 0;
        try {
            const matrix = new DOMMatrix(finalTransform);
            finalY = matrix.m42;
        } catch (err) {}

        menuContent.style.transition = 'transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1)';

        if (finalY > dragThreshold) {
            // 1. Deslizamos hasta abajo visualmente
            menuContent.style.transform = 'translateY(100%)';
            
            menuContent.addEventListener('transitionend', () => {
                // 2. [CLAVE] Llamamos con 'false' para saltar la animación CSS que causa el POP
                closeAllModules(null, false); 
            }, { once: true });

        } else {
            // Restaurar
            menuContent.style.transform = 'translateY(0)';
            
            menuContent.addEventListener('transitionend', () => {
                menuContent.style.transform = ''; 
                menuContent.style.transition = '';
            }, { once: true });
        }
    }
}